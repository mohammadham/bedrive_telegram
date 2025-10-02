<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Phase 8.3: Telegram Upload Session Model
 * 
 * مدل برای مدیریت resumable upload sessions
 */
class TelegramUploadSession extends Model
{
    protected $table = 'telegram_upload_sessions';

    protected $fillable = [
        'session_id',
        'user_id',
        'progress_id',
        'url',
        'filename',
        'total_size',
        'temp_path',
        'chunk_size',
        'total_chunks',
        'completed_chunks',
        'chunks_map',
        'downloaded_bytes',
        'uploaded_bytes',
        'telegram_file_id',
        'status',
        'error_message',
        'metadata',
        'is_resumable',
        'last_activity_at',
        'paused_at',
        'resumed_at',
    ];

    protected $casts = [
        'total_size' => 'integer',
        'chunk_size' => 'integer',
        'total_chunks' => 'integer',
        'completed_chunks' => 'integer',
        'chunks_map' => 'array',
        'downloaded_bytes' => 'integer',
        'uploaded_bytes' => 'integer',
        'metadata' => 'array',
        'is_resumable' => 'boolean',
        'last_activity_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
    ];

    /**
     * Relationship با User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship با TelegramUploadProgress
     */
    public function progress(): BelongsTo
    {
        return $this->belongsTo(TelegramUploadProgress::class, 'progress_id');
    }

    /**
     * ایجاد session ID یکتا
     */
    public static function generateSessionId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Initialize session با محاسبه chunks
     */
    public function initialize(int $totalSize, int $chunkSize = 5242880): void
    {
        $totalChunks = ceil($totalSize / $chunkSize);
        
        $this->update([
            'total_size' => $totalSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'chunks_map' => array_fill(0, $totalChunks, false), // false = not completed
            'status' => 'initialized',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * علامت‌گذاری chunk به عنوان completed
     */
    public function markChunkCompleted(int $chunkIndex, int $bytesDownloaded): void
    {
        $chunksMap = $this->chunks_map ?? [];
        $chunksMap[$chunkIndex] = true;

        $this->update([
            'chunks_map' => $chunksMap,
            'completed_chunks' => array_sum($chunksMap),
            'downloaded_bytes' => $this->downloaded_bytes + $bytesDownloaded,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * دریافت chunk بعدی که باید download شود
     */
    public function getNextChunkIndex(): ?int
    {
        $chunksMap = $this->chunks_map ?? [];
        
        foreach ($chunksMap as $index => $completed) {
            if (!$completed) {
                return $index;
            }
        }
        
        return null; // همه chunks تکمیل شده
    }

    /**
     * دریافت range برای chunk
     */
    public function getChunkRange(int $chunkIndex): array
    {
        $start = $chunkIndex * $this->chunk_size;
        $end = min($start + $this->chunk_size - 1, $this->total_size - 1);

        return [
            'start' => $start,
            'end' => $end,
            'size' => $end - $start + 1,
        ];
    }

    /**
     * محاسبه درصد download
     */
    public function getDownloadPercentageAttribute(): float
    {
        if (!$this->total_size || $this->total_size == 0) {
            return 0;
        }

        return min(100, ($this->downloaded_bytes / $this->total_size) * 100);
    }

    /**
     * محاسبه درصد upload
     */
    public function getUploadPercentageAttribute(): float
    {
        if (!$this->total_size || $this->total_size == 0) {
            return 0;
        }

        return min(100, ($this->uploaded_bytes / $this->total_size) * 100);
    }

    /**
     * Pause session
     */
    public function pause(): void
    {
        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);
    }

    /**
     * Resume session
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'downloading',
            'resumed_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * شروع download
     */
    public function startDownload(): void
    {
        $this->update([
            'status' => 'downloading',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * علامت‌گذاری download به عنوان completed
     */
    public function markDownloadCompleted(): void
    {
        $this->update([
            'status' => 'downloaded',
            'completed_chunks' => $this->total_chunks,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * شروع upload به تلگرام
     */
    public function startUpload(): void
    {
        $this->update([
            'status' => 'uploading',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * علامت‌گذاری به عنوان completed
     */
    public function markAsCompleted(string $telegramFileId): void
    {
        $this->update([
            'status' => 'completed',
            'telegram_file_id' => $telegramFileId,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * علامت‌گذاری به عنوان failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * علامت‌گذاری به عنوان cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * علامت‌گذاری به عنوان non-resumable
     */
    public function markAsNonResumable(string $reason = ''): void
    {
        $this->update([
            'is_resumable' => false,
            'error_message' => $reason ?: $this->error_message,
        ]);
    }

    /**
     * آیا می‌توان resume کرد؟
     */
    public function canResume(): bool
    {
        return $this->is_resumable 
            && in_array($this->status, ['paused', 'downloading', 'failed'])
            && $this->completed_chunks < $this->total_chunks;
    }

    /**
     * آیا download کامل شده؟
     */
    public function isDownloadComplete(): bool
    {
        return $this->completed_chunks >= $this->total_chunks;
    }

    /**
     * چک کردن وضعیت‌ها
     */
    public function isInitialized(): bool
    {
        return $this->status === 'initialized';
    }

    public function isDownloading(): bool
    {
        return $this->status === 'downloading';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isUploading(): bool
    {
        return $this->status === 'uploading';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Scope: موارد قابل resume
     */
    public function scopeResumable($query)
    {
        return $query->where('is_resumable', true)
            ->whereIn('status', ['paused', 'downloading', 'failed'])
            ->whereRaw('completed_chunks < total_chunks');
    }

    /**
     * Scope: موارد stale (بیش از 1 ساعت بدون activity)
     */
    public function scopeStale($query)
    {
        return $query->where('last_activity_at', '<', now()->subHour())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * حذف فایل موقت
     */
    public function cleanupTempFile(): void
    {
        if ($this->temp_path && file_exists($this->temp_path)) {
            @unlink($this->temp_path);
        }
    }

    /**
     * Cleanup موارد قدیمی (بیش از 24 ساعت)
     */
    public static function cleanupOld(): int
    {
        $sessions = static::where('created_at', '<', now()->subHours(24))
            ->whereIn('status', ['completed', 'cancelled', 'failed'])
            ->get();

        foreach ($sessions as $session) {
            $session->cleanupTempFile();
        }

        return $sessions->each->delete()->count();
    }
}
