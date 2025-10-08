<?php

namespace Common\Files\Telegram;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Phase 8.2 Integration: URL Upload با Progress Tracking
 * 
 * Wrapper برای TelegramUrlUploadService که progress tracking را اضافه می‌کند
 */
class TelegramUrlUploadWithProgress
{
    protected TelegramUrlUploadService $uploadService;
    protected TelegramUploadProgressService $progressService;

    public function __construct()
    {
        $this->uploadService = new TelegramUrlUploadService();
        $this->progressService = new TelegramUploadProgressService();
    }

    /**
     * آپلود از URL با progress tracking
     */
    public function uploadFromUrl(
        string $url,
        array $fileData = [],
        array $telegramOptions = []
    ): array {
        $userId = $fileData['user_id'] ?? auth()->id();
        $filename = $fileData['name'] ?? $this->extractFilename($url);

        // ایجاد session برای tracking
        $progress = $this->progressService->createSession(
            $userId,
            $url,
            $filename,
            null // size بعداً update می‌شود
        );

        $sessionId = $progress->session_id;

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

            // آپلود به تلگرام
            $result = $this->uploadService->uploadFile(
                $tempPath,
                array_merge($fileData, ['name' => $filename])
            );

            // علامت‌گذاری به عنوان completed
            $this->progressService->markAsCompleted(
                $sessionId,
                $result['file_entry']->id ?? null
            );

            // پاک کردن فایل موقت
            @unlink($tempPath);

            return [
                'session_id' => $sessionId,
                'file_entry' => $result['file_entry'],
                'metadata' => $result['metadata'],
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
            // استخراج نام فایل برای temp path (بدون استفاده از URL که ممکن است special chars داشته باشد)
            $urlPath = parse_url($url, PHP_URL_PATH);
            $safeBasename = $urlPath ? preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($urlPath)) : 'file';
            $tempPath = sys_get_temp_dir() . '/' . uniqid('telegram_') . '_' . $safeBasename;
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
     * استخراج نام فایل از URL
     */
    protected function extractFilename(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path);
        
        if (empty($filename) || strpos($filename, '.') === false) {
            return 'file_' . time();
        }

        return $filename;
    }

    /**
     * دریافت progress service (برای استفاده خارجی)
     */
    public function getProgressService(): TelegramUploadProgressService
    {
        return $this->progressService;
    }
}
