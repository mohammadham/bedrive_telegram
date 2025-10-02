<?php

namespace Common\Files\Telegram\Exceptions;

/**
 * Exception thrown when file upload fails
 */
class TelegramUploadException extends TelegramException
{
    public static function fileTooLarge(int $fileSize, int $maxSize): self
    {
        return new self(
            "File size ({$fileSize} bytes) exceeds maximum allowed size ({$maxSize} bytes)",
            413,
            null,
            ['file_size' => $fileSize, 'max_size' => $maxSize]
        );
    }

    public static function invalidFile(string $reason = 'Invalid file'): self
    {
        return new self($reason, 400);
    }

    public static function channelNotAccessible(string $channelId): self
    {
        return new self(
            "Cannot access channel: {$channelId}. Make sure bot is admin.",
            403,
            null,
            ['channel_id' => $channelId]
        );
    }

    public static function uploadFailed(string $reason, array $context = []): self
    {
        return new self("Upload failed: {$reason}", 500, null, $context);
    }
}