<?php

namespace Common\Files\Telegram;

use App\Models\TelegramUploadProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8.2: Telegram Upload Progress Service
 * 
 * سرویس برای مدیریت progress آپلود فایل‌ها
 */
class TelegramUploadProgressService
{
    protected const CACHE_TTL = 5; // 5 seconds - برای real-time updates
    protected const CACHE_PREFIX = 'telegram_progress:';

    /**
     * ایجاد session جدید برای tracking
     */
    public function createSession(
        int $userId,
        string $url,
        string $filename,
        ?int $totalSize = null
    ): TelegramUploadProgress {
        $sessionId = TelegramUploadProgress::generateSessionId();

        $progress = TelegramUploadProgress::create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'url' => $url,
            'filename' => $filename,
            'total_size' => $totalSize,
            'status' => 'pending',
        ]);

        // Cache the session
        $this->cacheProgress($progress);

        Log::info('Telegram upload session created', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'url' => $url,
        ]);

        return $progress;
    }

    /**
     * دریافت progress با session ID
     */
    public function getProgress(string $sessionId): ?TelegramUploadProgress
    {
        // Try cache first
        $cached = $this->getCachedProgress($sessionId);
        if ($cached) {
            return $cached;
        }

        // Fallback to database
        $progress = TelegramUploadProgress::where('session_id', $sessionId)->first();
        
        if ($progress) {
            $this->cacheProgress($progress);
        }

        return $progress;
    }

    /**
     * دریافت progress برای user
     */
    public function getUserProgress(int $userId, int $limit = 10): array
    {
        return TelegramUploadProgress::forUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($progress) {
                return $this->formatProgressData($progress);
            })
            ->toArray();
    }

    /**
     * دریافت فقط موارد in progress
     */
    public function getActiveProgress(int $userId): array
    {
        return TelegramUploadProgress::forUser($userId)
            ->inProgress()
            ->get()
            ->map(function ($progress) {
                return $this->formatProgressData($progress);
            })
            ->toArray();
    }

    /**
     * شروع download
     */
    public function startDownload(string $sessionId): void
    {
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            throw new \Exception("Progress session not found: {$sessionId}");
        }

        // ✅ Clear cache before update
        $this->clearCache($sessionId);
        
        $progress->update(['status' => 'downloading']);
        $progress->refresh();
        
        // ✅ Cache after refresh
        $this->cacheProgress($progress);
        
        Log::info('✅ Download started and cached', [
            'session_id' => $sessionId,
            'status' => $progress->status,
        ]);
    }

    /**
     * به‌روزرسانی download progress
     */
    public function updateDownloadProgress(
        string $sessionId,
        int $downloadedBytes,
        ?float $speed = null,
        ?int $eta = null
    ): void {
        // ✅ Clear cache برای دریافت fresh data
        $this->clearCache($sessionId);
        
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            return;
        }

        $progress->updateDownloadProgress($downloadedBytes, $speed, $eta);
        $progress->refresh(); // ✅ Refresh after update
        $this->cacheProgress($progress);
    }

    /**
     * ذخیره مسیر فایل موقت
     */
    public function setTempPath(string $sessionId, string $tempPath): void
    {
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            return;
        }

        $progress->update(['temp_path' => $tempPath]);
        $this->clearCache($sessionId); // Force fresh data
        
        Log::info('✅ Temp path saved', [
            'session_id' => $sessionId,
            'temp_path' => $tempPath,
        ]);
    }


    /**
     * شروع upload
     */
    public function startUpload(string $sessionId): void
    {
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            throw new \Exception("Progress session not found: {$sessionId}");
        }

        $progress->startUpload();
        $progress->refresh(); // ✅ Refresh after update
        $this->cacheProgress($progress);
        
        Log::info('✅ Upload started and cached', [
            'session_id' => $sessionId,
            'status' => $progress->status,
        ]);
    }

    /**
     * به‌روزرسانی upload progress
     */
    public function updateUploadProgress(
        string $sessionId,
        int $uploadedBytes,
        ?float $speed = null,
        ?int $eta = null
    ): void {
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            return;
        }

        $progress->updateUploadProgress($uploadedBytes, $speed, $eta);
        $progress->refresh(); // ✅ Refresh after update
        $this->cacheProgress($progress);
    }

    /**
     * علامت‌گذاری به عنوان completed
     */
    public function markAsCompleted(string $sessionId, ?int $fileEntryId = null): void
    {
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            return;
        }

        $progress->markAsCompleted($fileEntryId);
        $this->cacheProgress($progress);

        Log::info('Telegram upload completed', [
            'session_id' => $sessionId,
            'file_entry_id' => $fileEntryId,
        ]);
    }

    /**
     * علامت‌گذاری به عنوان failed
     */
    public function markAsFailed(string $sessionId, string $error): void
    {
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            return;
        }

        $progress->markAsFailed($error);
        $this->cacheProgress($progress);

        Log::error('Telegram upload failed', [
            'session_id' => $sessionId,
            'error' => $error,
        ]);
    }

    /**
     * لغو آپلود
     */
    public function cancel(string $sessionId): array
    {
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            throw new \Exception("Progress session not found: {$sessionId}");
        }

        $progress->markAsCancelled();
        $progress->refresh(); // ✅ Refresh after update
        $this->clearCache($sessionId); // ✅ Clear cache to force fresh data
        
        Log::info('✅ Upload cancelled', [
            'session_id' => $sessionId,
            'status' => $progress->status,
        ]);

        return [
            'session_id' => $progress->session_id,
            'status' => $progress->status,
            'message' => 'Upload cancelled successfully',
        ];
    }

    /**
     * فرمت کردن داده progress برای API
     */
    protected function formatProgressData(TelegramUploadProgress $progress): array
    {
        return [
            'session_id' => $progress->session_id,
            'url' => $progress->url,
            'filename' => $progress->filename,
            'total_size' => $progress->total_size ?? 0,
            'formatted_size' => $progress->formatted_size ?? 'N/A',
            
            // Download
            'downloaded_bytes' => $progress->downloaded_bytes ?? 0,
            'download_speed' => $progress->download_speed,
            'download_eta' => $progress->download_eta,
            'download_percentage' => round($progress->download_percentage ?? 0, 2),
            'formatted_download_speed' => $progress->formatted_download_speed ?? 'N/A',
            
            // Upload
            'uploaded_bytes' => $progress->uploaded_bytes ?? 0,
            'upload_speed' => $progress->upload_speed,
            'upload_eta' => $progress->upload_eta,
            'upload_percentage' => round($progress->upload_percentage ?? 0, 2),
            'formatted_upload_speed' => $progress->formatted_upload_speed ?? 'N/A',
            
            // Overall
            'overall_percentage' => round($progress->overall_percentage ?? 0, 2),
            'status' => $progress->status,
            'error_message' => $progress->error_message,
            
            // Phase 8.4: Retry fields
            'retry_count' => $progress->retry_count ?? 0,
            'max_retries' => $progress->max_retries ?? 3,
            'is_retryable' => $progress->is_retryable ?? false,
            'retry_info' => $progress->retry_info ?? null,
            'next_retry_at' => $progress->next_retry_at?->toIso8601String(),
            
            // Timestamps
            'started_at' => $progress->started_at?->toIso8601String(),
            'completed_at' => $progress->completed_at?->toIso8601String(),
            'created_at' => $progress->created_at->toIso8601String(),
        ];
    }

    /**
     * Cache progress
     */
    protected function cacheProgress(TelegramUploadProgress $progress): void
    {
        $key = self::CACHE_PREFIX . $progress->session_id;
        Cache::put($key, $progress, self::CACHE_TTL);
    }

    /**
     * دریافت از cache
     */
    protected function getCachedProgress(string $sessionId): ?TelegramUploadProgress
    {
        $key = self::CACHE_PREFIX . $sessionId;
        return Cache::get($key);
    }

    /**
     * پاک کردن cache
     */
    protected function clearCache(string $sessionId): void
    {
        $key = self::CACHE_PREFIX . $sessionId;
        Cache::forget($key);
    }

    /**
     * پاکسازی موارد قدیمی
     */
    public function cleanup(): int
    {
        return TelegramUploadProgress::cleanupOld(); 
    }
}