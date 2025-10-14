<?php

namespace App\Jobs;

use App\Models\FileEntry;
use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramUploadProgressService;
use Common\Files\Telegram\TelegramRetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Telegram URL Upload Job
 * 
 * فایل را در background دانلود و به تلگرام آپلود می‌کند
 * با Progress Tracking, Retry و Error Handling کامل
 */
class TelegramUrlUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $url;
    public array $fileData;
    public array $telegramOptions;
    public string $sessionId;

    /**
     * Timeout برای فایل‌های بزرگ (10 دقیقه)
     */
    public $timeout = 600;

    /**
     * سعی مجدد در صورت failure
     */
    public $tries = 3;

    /**
     * Create a new job instance
     */
    public function __construct(
        string $url,
        array $fileData,
        array $telegramOptions,
        string $sessionId
    ) {
        $this->url = $url;
        $this->fileData = $fileData;
        $this->telegramOptions = $telegramOptions;
        $this->sessionId = $sessionId;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $progressService = new TelegramUploadProgressService();
        $fileManager = app(TelegramFileManager::class);

        Log::info('Telegram URL upload job started', [
            'session_id' => $this->sessionId,
            'url' => $this->url,
            'user_id' => $this->fileData['user_id'] ?? null,
        ]);

        try {
            // شروع download
            $progressService->startDownload($this->sessionId);

            // دانلود فایل با progress tracking
            $tempPath = $this->downloadWithProgress($this->url, $this->sessionId, $progressService);

            if (!$tempPath || !file_exists($tempPath)) {
                throw new \Exception('Failed to download file from URL');
            }

            // به‌روزرسانی total_size
            $fileSize = filesize($tempPath);
            $progress = $progressService->getProgress($this->sessionId);
            $progress->update(['total_size' => $fileSize]);

            Log::info('Download completed successfully', [
                'temp_path' => $tempPath,
                'file_size' => $fileSize,
            ]);

            // شروع upload
            $progressService->startUpload($this->sessionId);

            // آپلود به تلگرام
            $filename = $this->fileData['name'] ?? $this->extractFilename($this->url);
            
            $uploadResult = $fileManager->uploadFile($tempPath, null, [
                'filename' => $filename,
                'caption' => $this->telegramOptions['caption'] ?? '',
            ]);

            Log::info('File uploaded to Telegram', [
                'file_id' => $uploadResult['file_id'] ?? null,
                'message_id' => $uploadResult['message_id'] ?? null,
            ]);

            // ایجاد FileEntry
            $mimeType = mime_content_type($tempPath) ?: 'application/octet-stream';
            $fileEntry = $this->createFileEntry(
                $uploadResult,
                $this->fileData,
                $filename,
                $fileSize,
                $mimeType
            );

            // علامت‌گذاری به عنوان completed
            $progressService->markAsCompleted(
                $this->sessionId,
                $fileEntry->id
            );

            Log::info('Telegram URL upload completed successfully', [
                'session_id' => $this->sessionId,
                'file_entry_id' => $fileEntry->id,
            ]);

            // پاک کردن فایل موقت
            @unlink($tempPath);

        } catch (\Exception $e) {
            Log::error('Telegram URL upload job failed', [
                'session_id' => $this->sessionId,
                'url' => $this->url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // علامت‌گذاری به عنوان failed
            $progressService->markAsFailed($this->sessionId, $e->getMessage());

            // Auto-Retry Logic
            $retryService = new TelegramRetryService();
            $progress = $progressService->getProgress($this->sessionId);
            
            if ($progress) {
                $isRetryable = $retryService->classifyError($e->getMessage());
                
                if ($isRetryable) {
                    $progress->update([
                        'is_retryable' => true,
                        'retry_phase' => 'download',
                    ]);
                    $retryService->scheduleRetry($progress);
                    
                    Log::info('Upload will be retried', [
                        'session_id' => $this->sessionId,
                        'retry_count' => $progress->retry_count,
                        'next_retry_at' => $progress->next_retry_at,
                    ]);
                }
            }

            // Re-throw برای Laravel queue failure handling
            throw $e;
        }
    }

    /**
     * دانلود فایل با progress callback
     */
    protected function downloadWithProgress(
        string $url,
        string $sessionId,
        TelegramUploadProgressService $progressService
    ): ?string {
        // نام فایل sanitized
        $filename = $this->sanitizeFilename($this->fileData['name'] ?? $this->extractFilename($url));
        
        // فایل موقت
        $tempPath = sys_get_temp_dir() . '/telegram_' . uniqid() . '_' . $filename;

        Log::info('Downloading file with progress', [
            'url' => $url,
            'temp_path' => $tempPath,
        ]);

        $fp = fopen($tempPath, 'w+');
        if (!$fp) {
            throw new \Exception('Failed to create temp file');
        }

        $lastUpdateTime = microtime(true);
        $downloadedBytes = 0;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function (
                $resource,
                $downloadSize,
                $downloaded,
                $uploadSize,
                $uploaded
            ) use (&$lastUpdateTime, &$downloadedBytes, $sessionId, $progressService) {
                $currentTime = microtime(true);
                
                // هر 0.5 ثانیه progress را update کن
                if ($currentTime - $lastUpdateTime >= 0.5 && $downloaded > 0) {
                    $timeDiff = $currentTime - $lastUpdateTime;
                    $bytesDiff = $downloaded - $downloadedBytes;
                    $speed = $timeDiff > 0 ? $bytesDiff / $timeDiff : 0;
                    
                    $progressService->updateDownloadProgress(
                        $sessionId,
                        $downloaded,
                        $downloadSize > 0 ? $downloadSize : null,
                        $speed
                    );
                    
                    $lastUpdateTime = $currentTime;
                    $downloadedBytes = $downloaded;
                }
            },
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        fclose($fp);

        if ($result === false || $httpCode >= 400 || $curlErrno !== 0) {
            @unlink($tempPath);
            
            Log::error('Download failed', [
                'url' => $url,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'curl_errno' => $curlErrno,
            ]);
            
            return null;
        }

        return $tempPath;
    }

    /**
     * ایجاد FileEntry در دیتابیس
     */
    protected function createFileEntry(
        array $uploadResult,
        array $fileData,
        string $filename,
        int $fileSize,
        string $mimeType
    ): FileEntry {
        // Sanitize filename
        $safeName = $this->sanitizeFilename($filename);
        
        $fileEntry = FileEntry::create([
            'name' => $safeName,
            'file_name' => $safeName,
            'mime' => $mimeType,
            'type' => $this->getFileType($mimeType),
            'file_size' => $fileSize,
            'user_id' => $fileData['user_id'] ?? null,
            'owner_id' => $fileData['owner_id'] ?? $fileData['user_id'] ?? null,
            'parent_id' => $fileData['parent_id'] ?? null,
            'disk_prefix' => 'telegram',
            'path' => 'telegram://' . ($uploadResult['file_id'] ?? ''),
            'public' => false,
        ]);

        // ایجاد Telegram metadata
        if (isset($uploadResult['file_id'])) {
            $fileEntry->telegramMetadata()->create([
                'telegram_file_id' => $uploadResult['file_id'],
                'message_id' => $uploadResult['message_id'] ?? null,
                'channel_id' => config('services.telegram.channel_id'),
                'upload_method' => $uploadResult['method'] ?? 'bot',
                'upload_status' => 'completed',
                'original_file_size' => $fileSize,
                'telegram_file_type' => $this->getTelegramFileType($mimeType),
            ]);
        }

        return $fileEntry;
    }

    /**
     * Sanitize filename برای امنیت و سازگاری
     */
    protected function sanitizeFilename(string $filename): string {
        // URL decode
        $filename = urldecode($filename);
        
        // حذف کاراکترهای کنترلی
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename);
        
        // جایگزینی کاراکترهای ناامن
        $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);
        
        // محدود کردن طول (255 کاراکتر)
        if (mb_strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = mb_substr(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - mb_strlen($extension) - 1);
            $filename = $basename . '.' . $extension;
        }
        
        // حذف فاصله‌های اضافی
        $filename = trim($filename);
        
        return $filename;
    }

    /**
     * استخراج نام فایل از URL
     */
    protected function extractFilename(string $url): string {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        $filename = basename($path);
        
        if (empty($filename) || $filename === '/') {
            $filename = 'telegram_file_' . time() . '.bin';
        }
        
        return $this->sanitizeFilename($filename);
    }

    /**
     * تشخیص نوع فایل
     */
    protected function getFileType(string $mimeType): string {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])) {
            return 'document';
        }
        
        return 'file';
    }

    /**
     * تشخیص نوع فایل برای تلگرام
     */
    protected function getTelegramFileType(string $mimeType): string {
        if (str_starts_with($mimeType, 'image/')) {
            return 'photo';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        
        return 'document';
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Telegram URL upload job permanently failed', [
            'session_id' => $this->sessionId,
            'url' => $this->url,
            'error' => $exception->getMessage(),
        ]);

        $progressService = new TelegramUploadProgressService();
        $progressService->markAsFailed(
            $this->sessionId,
            'Job failed after all retries: ' . $exception->getMessage()
        );
    }
}
