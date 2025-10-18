<?php

namespace Common\Files\Telegram;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\FileEntry;

/**
 * Phase 8.2 Integration: URL Upload با Progress Tracking
 * 
 * Wrapper برای آپلود با progress tracking
 */
class TelegramUrlUploadWithProgress
{
    protected TelegramFileManager $fileManager;
    protected TelegramUploadProgressService $progressService;

    public function __construct()
    {
        $this->fileManager = app(TelegramFileManager::class);
        $this->progressService = new TelegramUploadProgressService();
    }

    /**
     * آپلود از URL با progress tracking
     */
    public function uploadFromUrl(
        string $url,
        array $fileData = [],
        array $telegramOptions = [],
        ?string $existingSessionId = null
    ): array {
        $userId = $fileData['user_id'] ?? auth()->id();
        $filename = $fileData['name'] ?? $this->extractFilename($url);
        $tempPath = null; // برای cleanup در finally block

        // استفاده از session موجود یا ایجاد جدید
        if ($existingSessionId) {
            $sessionId = $existingSessionId;
            $progress = \App\Models\TelegramUploadProgress::where('session_id', $sessionId)->firstOrFail();
        } else {
            // ایجاد session برای tracking
            $progress = $this->progressService->createSession(
                $userId,
                $url,
                $filename,
                null // size بعداً update می‌شود
            );
            $sessionId = $progress->session_id;
        }

        try {
            // ✅ چک کردن وضعیت cancelled قبل از شروع
            $progress->refresh();
            if ($progress->isCancelled()) {
                Log::info('Upload was cancelled before download started', ['session_id' => $sessionId]);
                throw new \Exception('Upload cancelled by user');
            }

            // شروع download
            $this->progressService->startDownload($sessionId);

            // دانلود فایل با progress tracking
            $tempPath = $this->downloadWithProgress($url, $sessionId);

            if (!$tempPath) {
                throw new \Exception('Failed to download file from URL');
            }

            // ✅ چک کردن وضعیت cancelled بعد از دانلود
            $progress->refresh();
            if ($progress->isCancelled()) {
                Log::info('Upload was cancelled after download', ['session_id' => $sessionId]);
                throw new \Exception('Upload cancelled by user');
            }

            // به‌روزرسانی total_size
            $fileSize = filesize($tempPath);
            $progress->update(['total_size' => $fileSize]);

            // شروع upload
            $this->progressService->startUpload($sessionId);

            // آپلود به تلگرام با TelegramFileManager
            $uploadResult = $this->fileManager->uploadFile($tempPath, null, [
                'filename' => $filename,
                'caption' => $fileData['caption'] ?? '',
            ]);

            // ✅ چک کردن وضعیت cancelled بعد از آپلود
            $progress->refresh();
            if ($progress->isCancelled()) {
                Log::info('Upload was cancelled after telegram upload', ['session_id' => $sessionId]);
                // حتی اگر در تلگرام آپلود شده، FileEntry نمی‌سازیم
                throw new \Exception('Upload cancelled by user');
            }

            // ایجاد FileEntry
            $mimeType = mime_content_type($tempPath) ?: 'application/octet-stream';
            $fileEntry = $this->createFileEntry(
                $uploadResult,
                $fileData,
                $filename,
                $fileSize,
                $mimeType
            );

            // علامت‌گذاری به عنوان completed
            $this->progressService->markAsCompleted(
                $sessionId,
                $fileEntry->id
            );

            return [
                'session_id' => $sessionId,
                'file_entry' => $fileEntry,
                'metadata' => $fileEntry->telegramMetadata,
            ];

        } catch (\Exception $e) {
            // ✅ اگر کنسل شده، نباید failed بشود
            $progress->refresh();
            if ($progress->isCancelled()) {
                Log::info('Upload cancelled, not marking as failed', ['session_id' => $sessionId]);
                throw $e; // Re-throw برای Laravel queue handling
            }

            // علامت‌گذاری به عنوان failed
            $this->progressService->markAsFailed($sessionId, $e->getMessage());

            // Phase 8.4: Auto-Retry Logic
            $retryService = new TelegramRetryService();
            $errorInfo = $retryService->classifyError($e);

            $progress = \App\Models\TelegramUploadProgress::where('session_id', $sessionId)->first();
            if ($progress) {
                if (!$errorInfo['is_retryable']) {
                    $progress->markAsNonRetryable($e->getMessage());
                } elseif ($progress->canRetry()) {
                    $progress->scheduleNextRetry();
                    
                    Log::info('Upload will be retried', [
                        'session_id' => $sessionId,
                        'retry_count' => $progress->retry_count,
                        'next_retry_at' => $progress->next_retry_at,
                    ]);
                }
            }

            Log::error('Telegram URL upload failed', [
                'session_id' => $sessionId,
                'url' => $url,
                'error' => $e->getMessage(),
                'is_retryable' => $errorInfo['is_retryable'],
            ]);

            throw $e;
        } finally {
            // ✅ FIX 1: حذف فایل موقت در هر صورت
            if ($tempPath && file_exists($tempPath)) {
                Log::info('Cleaning up temporary file', ['temp_path' => $tempPath]);
                @unlink($tempPath);
            }
        }
    }

    /**
     * دانلود فایل با progress tracking
     */
    protected function downloadWithProgress(string $url, string $sessionId): ?string
    {
        try {
            // استخراج نام فایل برای temp path
            // از hash استفاده می‌کنیم تا مشکل کاراکترهای خاص نداشته باشیم
            $urlPath = parse_url($url, PHP_URL_PATH);
            $extension = pathinfo($urlPath, PATHINFO_EXTENSION);
            $extension = $extension ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $extension) : '';
            
            // استفاده از مسیر مناسب برای temp files
            $tempDir = storage_path('app/telegram/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempPath = $tempDir . '/url_upload_' . uniqid() . $extension;
            $fp = fopen($tempPath, 'w+');

            $startTime = microtime(true);
            $lastUpdate = $startTime;
            $lastBytes = 0;

            Log::info('Downloading file with progress', [
                'url' => $url,
                'temp_path' => $tempPath,
            ]);

            // استفاده از cURL برای کنترل بیشتر و سازگاری بهتر
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 3600, // 1 ساعت برای دانلود
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_SSL_VERIFYPEER => false, // برای سرورهای با SSL مشکل‌دار
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_BUFFERSIZE => 8192,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => function($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($sessionId, &$lastUpdate, $startTime) {
                    if ($downloaded == 0) return 0;
                    if ($downloadSize > 0 && $downloaded > 0) {
                        $now = microtime(true);
                        
                        // ✅ چک کردن کنسل شدن در حین دانلود
                        try {
                            $progress = \App\Models\TelegramUploadProgress::where('session_id', $sessionId)->first();
                            if ($progress && $progress->isCancelled()) {
                                Log::info('Download cancelled by user during download', ['session_id' => $sessionId]);
                                return 1; // Return non-zero to abort cURL
                            }
                        } catch (\Exception $e) {
                            // Ignore database errors in progress function
                        }
                        
                        // Update هر 0.5 ثانیه
                        if ($now - $lastUpdate >= 0.5) {
                            $elapsed = $now - $startTime;
                            $speed = $elapsed > 0 ? $downloaded / $elapsed : 0;
                            $eta = $speed > 0 && $downloadSize > 0 
                                ? ($downloadSize - $downloaded) / $speed 
                                : null;

                            try {
                                $progressService = new TelegramUploadProgressService();
                                $progressService->updateDownloadProgress(
                                    $sessionId,
                                    (int)$downloaded,
                                    $speed,
                                    $eta ? (int)$eta : null
                                );
                            } catch (\Exception $e) {
                                Log::warning('Progress update failed', [
                                    'session_id' => $sessionId,
                                    'error' => $e->getMessage()
                                ]);
                            }

                            $lastUpdate = $now;
                        }
                    }
                    
                    return 0; // Continue download
                },
            ]);

            $success = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $curlErrno = curl_errno($ch);
            
            curl_close($ch);
            fclose($fp);

            // ✅ چک کردن آیا cURL به دلیل کنسل متوقف شده
            if ($curlErrno === CURLE_ABORTED_BY_CALLBACK) {
                Log::info('Download aborted by user', ['session_id' => $sessionId]);
                @unlink($tempPath);
                throw new \Exception('Download cancelled by user');
            }

            if (!$success || ($httpCode < 200 || $httpCode >= 300)) {
                Log::error('Download failed', [
                    'url' => $url,
                    'http_code' => $httpCode,
                    'curl_error' => $error,
                    'curl_errno' => $curlErrno,
                ]);
                @unlink($tempPath);
                return null;
            }

            // Verify file was downloaded
            if (!file_exists($tempPath) || filesize($tempPath) === 0) {
                Log::error('Downloaded file is empty', ['temp_path' => $tempPath]);
                @unlink($tempPath);
                return null;
            }

            Log::info('Download completed successfully', [
                'temp_path' => $tempPath,
                'file_size' => filesize($tempPath),
            ]);

            return $tempPath;

        } catch (\Exception $e) {
            Log::error('Download with progress failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if (isset($ch)) {
                curl_close($ch);
            }
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            
            return null;
        }
    }

    /**
     * استخراج نام فایل از URL
     */
    protected function extractFilename(string $url): string
    {
        // ✅ دکد کردن URL برای مدیریت درست کاراکترهای خاص
        $decodedUrl = rawurldecode($url);
        
        $path = parse_url($decodedUrl, PHP_URL_PATH);
        $filename = basename($path);

        // اگر نام فایل خالی است، از یک نام پیش‌فرض استفاده کن
        if (empty($filename) || $filename === '/') {
            $filename = 'telegram_upload_' . time();
        }

        // Sanitize filename برای امنیت
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.\x{0600}-\x{06FF}\s]/u', '_', $filename);
        $filename = trim($filename);

        // محدود کردن طول نام فایل
        if (mb_strlen($filename) > 200) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $filename = mb_substr($basename, 0, 190) . ($extension ? '.' . $extension : '');
        }

        return $filename;
    }

    /**
     * ایجاد FileEntry از نتیجه آپلود
     */
    protected function createFileEntry(
        array $uploadResult,
        array $fileData,
        string $filename,
        int $fileSize,
        string $mimeType
    ): FileEntry {
        // ✅ Sanitize filename قبل از ذخیره در FileEntry
        $sanitizedFilename = $this->sanitizeFilenameForFileEntry($filename);
        
        $fileEntry = FileEntry::create([
            'name' => $sanitizedFilename,
            'file_name' => $sanitizedFilename,
            'mime' => $mimeType,
            'file_size' => $fileSize,
            'type' => FileEntry::findType($mimeType),
            'extension' => pathinfo($sanitizedFilename, PATHINFO_EXTENSION),
            'user_id' => $fileData['user_id'] ?? auth()->id(),
            'parent_id' => $fileData['parent_id'] ?? null,
            'workspace_id' => $fileData['workspace_id'] ?? null,
            'public' => $fileData['public'] ?? false,
            'disk_prefix' => 'telegram',
            'path' => 'telegram/' . ($uploadResult['message_id'] ?? 'unknown'),
        ]);

        Log::info('FileEntry created', [
            'file_entry_id' => $fileEntry->id,
            'original_filename' => $filename,
            'sanitized_filename' => $sanitizedFilename,
        ]);

        return $fileEntry;
    }

    /**
     * ✅ Sanitize filename برای FileEntry (حذف کاراکترهای مشکل‌ساز)
     */
    protected function sanitizeFilenameForFileEntry(string $filename): string
    {
        // حذف کاراکترهای کنترلی و کاراکترهایی که باعث مشکل در base_convert می‌شوند
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename); // حذف control characters
        $sanitized = preg_replace('/[^\w\s\-\.\x{0600}-\x{06FF}]/u', '_', $sanitized); // فقط حروف، اعداد، فاصله، خط تیره، نقطه و فارسی
        $sanitized = preg_replace('/\s+/', '_', $sanitized); // فاصله‌ها را به underscore تبدیل کن
        $sanitized = preg_replace('/_+/', '_', $sanitized); // چند underscore متوالی را به یکی تبدیل کن
        $sanitized = trim($sanitized, '_'); // حذف underscore از ابتدا و انتها
        
        // اگر بعد از sanitize خالی شد، یک نام پیش‌فرض بده
        if (empty($sanitized)) {
            $sanitized = 'telegram_file_' . time();
        }
        
        return $sanitized;
    }
        /**
     * تشخیص نوع فایل بر اساس MIME type
     */
    protected function determineFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if (str_starts_with($mimeType, 'video/')) return 'video';
        if (str_starts_with($mimeType, 'audio/')) return 'audio';
        if (str_starts_with($mimeType, 'text/')) return 'text';
        if (str_contains($mimeType, 'pdf')) return 'pdf';
        if (str_contains($mimeType, 'archive') || str_contains($mimeType, 'zip')) return 'archive';
        
        return 'file';
    }
}
