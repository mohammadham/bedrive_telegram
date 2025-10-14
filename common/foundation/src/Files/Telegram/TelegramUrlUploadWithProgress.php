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
            // شروع download
            $this->progressService->startDownload($sessionId);

            // دانلود فایل با progress tracking
            $tempPath = $this->downloadWithProgress($url, $sessionId);

            if (!$tempPath) {
                throw new \Exception('Failed to download file from URL');
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

            // پاک کردن فایل موقت
            @unlink($tempPath);

            return [
                'session_id' => $sessionId,
                'file_entry' => $fileEntry,
                'metadata' => $fileEntry->telegramMetadata,
            ];

        } catch (\Exception $e) {
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
            $tempPath = sys_get_temp_dir() . '/' . uniqid('telegram_') . $extension;
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
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_SSL_VERIFYPEER => false, // برای سرورهای با SSL مشکل‌دار
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_BUFFERSIZE => 8192,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => function($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($sessionId, &$lastUpdate, $startTime) {
                    if ($downloaded == 0) return 0;

                    $now = microtime(true);
                    
                    // Update هر 0.5 ثانیه
                    if ($now - $lastUpdate >= 0.5) {
                        $elapsed = $now - $startTime;
                        $speed = $elapsed > 0 ? $downloaded / $elapsed : 0;
                        $eta = $speed > 0 && $downloadSize > 0 
                            ? ($downloadSize - $downloaded) / $speed 
                            : null;

                        try {
                            $this->progressService->updateDownloadProgress(
                                $sessionId,
                                (int)$downloaded,
                                $speed,
                                $eta ? (int)$eta : null
                            );
                        } catch (\Exception $e) {
                            Log::warning('Progress update failed', ['error' => $e->getMessage()]);
                        }

                        $lastUpdate = $now;
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
     * استخراج نام فایل از URL با حفظ کاراکترهای اصلی
     */
    protected function extractFilename(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path);
        
        // Decode برای نمایش صحیح نام فایل
        if ($filename) {
            $filename = urldecode($filename);
        }
        
        if (empty($filename) || strpos($filename, '.') === false) {
            return 'file_' . time();
        }

        return $filename;
    }

    /**
     * ایجاد FileEntry با sanitize کردن نام فایل
     */
    protected function createFileEntry(
        array $uploadResult,
        array $metadata,
        string $fileName,
        int $fileSize,
        string $mimeType
    ): FileEntry {
        // Sanitize filename برای جلوگیری از مشکلات encoding
        // حفظ extension ولی پاکسازی کاراکترهای مشکل‌ساز
        $pathInfo = pathinfo($fileName);
        $baseName = $pathInfo['filename'] ?? 'file';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        
        // پاکسازی نام بدون تغییر کاراکترهای معمولی
        // فقط کاراکترهایی که در filesystem مشکل ایجاد می‌کنند را حذف می‌کنیم
        $safeName = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/', '_', $baseName);
        $safeFileName = $safeName . $extension;
        
        // Create FileEntry WITHOUT path (برای تلگرام نیازی به path نیست)
        $fileEntry = FileEntry::create([
            'name' => $metadata['name'] ?? $safeFileName, // نام نمایشی
            'file_name' => $safeFileName, // نام فایل واقعی
            'mime' => $mimeType,
            'file_size' => $fileSize,
            'user_id' => $metadata['user_id'] ?? null,
            'owner_id' => $metadata['owner_id'] ?? $metadata['user_id'] ?? null,
            'parent_id' => $metadata['parent_id'] ?? null,
            'disk_prefix' => 'telegram',
            'type' => $this->determineFileType($mimeType),
            // path را اصلاً set نمی‌کنیم - تلگرام از message_id استفاده می‌کند
        ]);

        // Create Telegram metadata
        $telegramMetadata = TelegramMetadataHelper::createMetadata($fileEntry, [
            'file_id' => $uploadResult['file_id'],
            'message_id' => $uploadResult['message_id'],
            'channel_id' => $uploadResult['channel_id'],
            'upload_method' => $uploadResult['upload_method'],
            'upload_status' => 'completed',
            'uploaded_at' => now(),
        ]);

        return $fileEntry->load('telegramMetadata');
    }

    /**
     * تعیین نوع فایل از MIME type
     */
    protected function determineFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }
        if (str_contains($mimeType, 'text/')) {
            return 'text';
        }
        return 'file';
    }

    /**
     * دریافت progress service (برای استفاده خارجی)
     */
    public function getProgressService(): TelegramUploadProgressService
    {
        return $this->progressService;
    }
}
