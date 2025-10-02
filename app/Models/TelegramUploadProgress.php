<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Phase 8.2: Telegram Upload Progress Model
 * 
 * مدل برای tracking progress آپلود فایل‌ها
 */
class TelegramUploadProgress extends Model
{
    protected $table = 'telegram_upload_progress';

    protected $fillable = [
        'session_id',
        'user_id',
        'url',
        'filename',
        'total_size',
        'downloaded_bytes',
        'uploaded_bytes',
        'download_speed',
        'upload_speed',
        'download_eta',
        'upload_eta',
        'status',
        'error_message',
        'metadata',
        'file_entry_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_size' => 'integer',
        'downloaded_bytes' => 'integer',
        'uploaded_bytes' => 'integer',
        'download_speed' => 'decimal:2',
        'upload_speed' => 'decimal:2',
        'download_eta' => 'integer',
        'upload_eta' => 'integer',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationship با User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship با FileEntry
     */
    public function fileEntry(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class);
    }

    /**
     * ایجاد session ID یکتا
     */
    public static function generateSessionId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * شروع tracking
     */
    public function start(): void
    {
        $this->update([
            'status' => 'downloading',
            'started_at' => now(),
        ]);
    }

    /**
     * به‌روزرسانی download progress
     */
    public function updateDownloadProgress(int $bytes, ?float $speed = null, ?int $eta = null): void
    {
        $this->update([
            'downloaded_bytes' => $bytes,
            'download_speed' => $speed,
            'download_eta' => $eta,
            'status' => 'downloading',
        ]);
    }

    /**
     * شروع upload به تلگرام
     */
    public function startUpload(): void
    {
        $this->update([
            'status' => 'uploading',
        ]);
    }

    /**
     * به‌روزرسانی upload progress
     */
    public function updateUploadProgress(int $bytes, ?float $speed = null, ?int $eta = null): void
    {
        $this->update([
            'uploaded_bytes' => $bytes,
            'upload_speed' => $speed,
            'upload_eta' => $eta,
            'status' => 'uploading',
        ]);
    }

    /**
     * علامت‌گذاری به عنوان completed
     */
    public function markAsCompleted(?int $fileEntryId = null): void
    {
        $this->update([
            'status' => 'completed',
            'file_entry_id' => $fileEntryId,
            'completed_at' => now(),
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
            'completed_at' => now(),
        ]);
    }

    /**
     * علامت‌گذاری به عنوان cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
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
     * محاسبه درصد کلی
     */
    public function getOverallPercentageAttribute(): float
    {
        // Download = 50%, Upload = 50%
        $downloadPercent = $this->download_percentage / 2;
        $uploadPercent = $this->upload_percentage / 2;

        return $downloadPercent + $uploadPercent;
    }

    /**
     * چک کردن وضعیت
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDownloading(): bool
    {
        return $this->status === 'downloading';
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

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'downloading', 'uploading']);
    }

    /**
     * فرمت کردن حجم فایل
     */
    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->total_size);
    }

    /**
     * فرمت کردن سرعت
     */
    public function getFormattedDownloadSpeedAttribute(): string
    {
        return $this->formatSpeed($this->download_speed);
    }

    public function getFormattedUploadSpeedAttribute(): string
    {
        return $this->formatSpeed($this->upload_speed);
    }

    /**
     * Helper: فرمت byte
     */
    protected function formatBytes(?int $bytes): string
    {
        if ($bytes === null) return 'N/A';
        if ($bytes == 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log(1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Helper: فرمت speed
     */
    protected function formatSpeed(?float $speed): string
    {
        if ($speed === null) return 'N/A';
        return $this->formatBytes((int)$speed) . '/s';
    }

    /**
     * Scope: فقط موارد in progress
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['pending', 'downloading', 'uploading']);
    }

    /**
     * Scope: برای یک user خاص
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * حذف موارد قدیمی (بیش از 24 ساعت)
     */
    public static function cleanupOld(): int
    {
        return static::where('created_at', '<', now()->subHours(24))
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->delete();
    }
}