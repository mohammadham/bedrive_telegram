<?php

namespace Common\Files\Telegram\Exceptions;

/**
 * Exception thrown when file download fails
 */
class TelegramDownloadException extends TelegramException
{
    public static function fileNotFound(string $fileId): self
    {
        return new self(
            "File not found: {$fileId}",
            404,
            null,
            ['file_id' => $fileId]
        );
    }

    public static function downloadFailed(string $reason, array $context = []): self
    {
        return new self("Download failed: {$reason}", 500, null, $context);
    }
}