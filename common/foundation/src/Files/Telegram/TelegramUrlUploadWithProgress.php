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

            Log::error('Telegram URL upload failed', [
                'session_id' => $sessionId,
                'url' => $url,
                'error' => $e->getMessage(),
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
            $tempPath = sys_get_temp_dir() . '/' . uniqid('telegram_') . '_' . basename(parse_url($url, PHP_URL_PATH));
            $fp = fopen($tempPath, 'w+');

            $startTime = microtime(true);
            $lastUpdate = $startTime;
            $lastBytes = 0;

            $response = Http::timeout(300)
                ->withOptions([
                    'sink' => $fp,
                    'progress' => function ($totalBytes, $downloadedBytes) use ($sessionId, &$lastUpdate, &$lastBytes, $startTime) {
                        if ($downloadedBytes == 0) return;

                        $now = microtime(true);
                        
                        // Update هر 0.5 ثانیه
                        if ($now - $lastUpdate >= 0.5) {
                            $elapsed = $now - $startTime;
                            $speed = $elapsed > 0 ? $downloadedBytes / $elapsed : 0;
                            $eta = $speed > 0 && $totalBytes > 0 
                                ? ($totalBytes - $downloadedBytes) / $speed 
                                : null;

                            $this->progressService->updateDownloadProgress(
                                $sessionId,
                                $downloadedBytes,
                                $speed,
                                $eta ? (int)$eta : null
                            );

                            $lastUpdate = $now;
                            $lastBytes = $downloadedBytes;
                        }
                    },
                ])
                ->get($url);

            fclose($fp);

            if (!$response->successful()) {
                @unlink($tempPath);
                return null;
            }

            return $tempPath;

        } catch (\Exception $e) {
            Log::error('Download with progress failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            
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
