<?php

namespace App\Models;

use Common\Core\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Telegram File Metadata Model
 * 
 * Stores Telegram-specific information for files uploaded via Telegram driver
 * 
 * @property int $id
 * @property int $file_entry_id
 * @property string|null $telegram_file_id
 * @property string|null $telegram_file_unique_id
 * @property int|null $message_id
 * @property string|null $channel_id
 * @property string $upload_method
 * @property int|null $original_file_size
 * @property string|null $original_mime_type
 * @property string|null $telegram_mime_type
 * @property string|null $session_file
 * @property string $telegram_file_type
 * @property string $upload_status
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $uploaded_at
 * @property \Carbon\Carbon|null $last_accessed_at
 * @property array|null $metadata
 * @property-read FileEntry $fileEntry
 */
class TelegramFileMetadata extends BaseModel
{
    use SoftDeletes;

    protected $table = 'telegram_file_metadata';

    protected $fillable = [
        'file_entry_id',
        'telegram_file_id',
        'telegram_file_unique_id',
        'message_id',
        'channel_id',
        'upload_method',
        'original_file_size',
        'original_mime_type',
        'telegram_mime_type',
        'session_file',
        'telegram_file_type',
        'upload_status',
        'error_message',
        'uploaded_at',
        'last_accessed_at',
        'metadata',
    ];

    protected $casts = [
        'id' => 'integer',
        'file_entry_id' => 'integer',
        'message_id' => 'integer',
        'original_file_size' => 'integer',
        'metadata' => 'array',
        'uploaded_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    protected $dates = [
        'uploaded_at',
        'last_accessed_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Relationship to FileEntry
     */
    public function fileEntry(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class, 'file_entry_id');
    }

    /**
     * Check if file was uploaded via Bot API
     */
    public function isUploadedViaBot(): bool
    {
        return $this->upload_method === 'bot';
    }

    /**
     * Check if file was uploaded via User Account (MTProto)
     */
    public function isUploadedViaUserAccount(): bool
    {
        return $this->upload_method === 'user';
    }

    /**
     * Check if upload is completed
     */
    public function isUploadCompleted(): bool
    {
        return $this->upload_status === 'completed';
    }

    /**
     * Check if upload failed
     */
    public function isUploadFailed(): bool
    {
        return $this->upload_status === 'failed';
    }

    /**
     * Mark upload as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'upload_status' => 'completed',
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Mark upload as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'upload_status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Update last accessed timestamp
     */
    public function touchLastAccessed(): void
    {
        $this->update([
            'last_accessed_at' => now(),
        ]);
    }

    /**
     * Get file size in human-readable format
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->original_file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->original_file_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Determine upload method based on file size
     * 
     * @param int $fileSize File size in bytes
     * @return string 'bot' or 'user'
     */
    public static function determineUploadMethod(int $fileSize): string
    {
        // 50MB in bytes
        $botApiLimit = 50 * 1024 * 1024;
        
        return $fileSize <= $botApiLimit ? 'bot' : 'user';
    }

    /**
     * Get metadata value by key
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value by key
     */
    public function setMetadataValue(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->update(['metadata' => $metadata]);
    }
     /**
     * Get filterable fields for this model
     */
    public static function filterableFields(): array
    {
        return [
            'id',
            'file_entry_id',
            'message_id',
            'channel_id',
            'upload_method',
            'telegram_file_type',
            'upload_status',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * Convert model to normalized array for API responses
     */
    public function toNormalizedArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->fileEntry->name ?? 'Unknown',
            'description' => $this->telegram_file_type,
            'image' => null,
            'model_type' => 'telegram_file_metadata',
        ];
    }

    /**
     * Convert model to searchable array for indexing
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'file_entry_id' => $this->file_entry_id,
            'telegram_file_id' => $this->telegram_file_id,
            'message_id' => $this->message_id,
            'channel_id' => $this->channel_id,
            'upload_method' => $this->upload_method,
            'telegram_file_type' => $this->telegram_file_type,
            'upload_status' => $this->upload_status,
            'created_at' => $this->created_at->timestamp ?? '_null',
            'updated_at' => $this->updated_at->timestamp ?? '_null',
            'deleted_at' => $this->deleted_at->timestamp ?? '_null',
        ];
    }

    /**
     * Get model type attribute
     */
    public static function getModelTypeAttribute(): string
    {
        return 'telegram_file_metadata';
    }

}
