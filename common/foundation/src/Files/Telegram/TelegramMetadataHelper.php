<?php

namespace Common\Files\Telegram;

use App\Models\FileEntry;
use App\Models\TelegramFileMetadata;

/**
 * Helper class for managing Telegram file metadata
 */
class TelegramMetadataHelper
{
    /**
     * File size threshold for Bot API (50MB)
     */
    public const BOT_API_SIZE_LIMIT = 50 * 1024 * 1024; // 50MB in bytes

    /**
     * File size threshold for User Account (2GB)
     */
    public const USER_ACCOUNT_SIZE_LIMIT = 2 * 1024 * 1024 * 1024; // 2GB in bytes

    /**
     * Create metadata for a file entry
     *
     * @param FileEntry $fileEntry
     * @param array $telegramData
     * @return TelegramFileMetadata
     */
    public static function createMetadata(
        FileEntry $fileEntry,
        array $telegramData
    ): TelegramFileMetadata {
        return TelegramFileMetadata::create([
            'file_entry_id' => $fileEntry->id,
            'telegram_file_id' => $telegramData['file_id'] ?? null,
            'telegram_file_unique_id' => $telegramData['file_unique_id'] ?? null,
            'message_id' => $telegramData['message_id'] ?? null,
            'channel_id' => $telegramData['channel_id'] ?? null,
            'upload_method' => $telegramData['upload_method'] ?? self::determineUploadMethod($fileEntry->file_size),
            'original_file_size' => $fileEntry->file_size,
            'original_mime_type' => $fileEntry->mime,
            'telegram_mime_type' => $telegramData['mime_type'] ?? $fileEntry->mime,
            'session_file' => $telegramData['session_file'] ?? null,
            'telegram_file_type' => $telegramData['file_type'] ?? self::determineTelegramFileType($fileEntry->mime),
            'upload_status' => $telegramData['upload_status'] ?? 'pending',
            'uploaded_at' => $telegramData['uploaded_at'] ?? null,
            'metadata' => $telegramData['metadata'] ?? null,
        ]);
    }

    /**
     * Update metadata for a file entry
     *
     * @param TelegramFileMetadata $metadata
     * @param array $updates
     * @return TelegramFileMetadata
     */
    public static function updateMetadata(
        TelegramFileMetadata $metadata,
        array $updates
    ): TelegramFileMetadata {
        $metadata->update($updates);
        return $metadata->fresh();
    }

    /**
     * Determine upload method based on file size
     *
     * @param int $fileSize File size in bytes
     * @return string 'bot' or 'user'
     */
    public static function determineUploadMethod(int $fileSize): string
    {
        return $fileSize <= self::BOT_API_SIZE_LIMIT ? 'bot' : 'user';
    }

    /**
     * Determine Telegram file type based on MIME type
     *
     * @param string|null $mimeType
     * @return string
     */
    public static function determineTelegramFileType(?string $mimeType): string
    {
        if (!$mimeType) {
            return 'document';
        }

        // Photo types
        if (str_starts_with($mimeType, 'image/')) {
            return 'photo';
        }

        // Video types
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        // Audio types
        if (str_starts_with($mimeType, 'audio/')) {
            // Voice messages are typically audio/ogg
            if (str_contains($mimeType, 'ogg')) {
                return 'voice';
            }
            return 'audio';
        }

        // Animation (GIF)
        if ($mimeType === 'image/gif') {
            return 'animation';
        }

        // Default to document
        return 'document';
    }

    /**
     * Check if file size is within Bot API limits
     *
     * @param int $fileSize
     * @return bool
     */
    public static function isWithinBotApiLimit(int $fileSize): bool
    {
        return $fileSize <= self::BOT_API_SIZE_LIMIT;
    }

    /**
     * Check if file size is within User Account limits
     *
     * @param int $fileSize
     * @return bool
     */
    public static function isWithinUserAccountLimit(int $fileSize): bool
    {
        return $fileSize <= self::USER_ACCOUNT_SIZE_LIMIT;
    }

    /**
     * Check if file can be uploaded via Telegram
     *
     * @param int $fileSize
     * @return array [canUpload, method, reason]
     */
    public static function canUploadFile(int $fileSize): array
    {
        if ($fileSize <= self::BOT_API_SIZE_LIMIT) {
            return [
                'can_upload' => true,
                'method' => 'bot',
                'reason' => 'File size is within Bot API limits (≤ 50MB)',
            ];
        }

        if ($fileSize <= self::USER_ACCOUNT_SIZE_LIMIT) {
            return [
                'can_upload' => true,
                'method' => 'user',
                'reason' => 'File size requires User Account upload (50MB - 2GB)',
            ];
        }

        return [
            'can_upload' => false,
            'method' => null,
            'reason' => 'File size exceeds Telegram limits (> 2GB)',
        ];
    }

    /**
     * Get metadata by file entry
     *
     * @param FileEntry $fileEntry
     * @return TelegramFileMetadata|null
     */
    public static function getMetadata(FileEntry $fileEntry): ?TelegramFileMetadata
    {
        return $fileEntry->telegramMetadata;
    }

    /**
     * Check if file entry has Telegram metadata
     *
     * @param FileEntry $fileEntry
     * @return bool
     */
    public static function hasMetadata(FileEntry $fileEntry): bool
    {
        return $fileEntry->telegramMetadata()->exists();
    }

    /**
     * Delete metadata for a file entry
     *
     * @param FileEntry $fileEntry
     * @return bool
     */
    public static function deleteMetadata(FileEntry $fileEntry): bool
    {
        if (self::hasMetadata($fileEntry)) {
            return $fileEntry->telegramMetadata()->delete();
        }
        return false;
    }

    /**
     * Format file size to human-readable format
     *
     * @param int $bytes
     * @return string
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get statistics about Telegram uploads
     *
     * @return array
     */
    public static function getUploadStatistics(): array
    {
        $totalUploads = TelegramFileMetadata::count();
        $botUploads = TelegramFileMetadata::where('upload_method', 'bot')->count();
        $userUploads = TelegramFileMetadata::where('upload_method', 'user')->count();
        $completedUploads = TelegramFileMetadata::where('upload_status', 'completed')->count();
        $failedUploads = TelegramFileMetadata::where('upload_status', 'failed')->count();
        $totalSize = TelegramFileMetadata::sum('original_file_size');

        return [
            'total_uploads' => $totalUploads,
            'bot_uploads' => $botUploads,
            'user_uploads' => $userUploads,
            'completed_uploads' => $completedUploads,
            'failed_uploads' => $failedUploads,
            'total_size' => $totalSize,
            'total_size_formatted' => self::formatFileSize($totalSize),
            'success_rate' => $totalUploads > 0 
                ? round(($completedUploads / $totalUploads) * 100, 2) . '%'
                : '0%',
        ];
    }
}
