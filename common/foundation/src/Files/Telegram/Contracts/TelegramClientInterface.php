<?php

namespace Common\Files\Telegram\Contracts;

/**
 * Interface for Telegram clients (Bot API and User Account)
 */
interface TelegramClientInterface
{
    /**
     * Upload a file to Telegram
     *
     * @param string $filePath Path to the file
     * @param string $channelId Channel ID to upload to
     * @param array $options Additional options (caption, filename, etc.)
     * @return array Upload result with file_id, message_id, etc.
     * @throws \Common\Files\Telegram\Exceptions\TelegramUploadException
     */
    public function uploadFile(
        string $filePath,
        string $channelId,
        array $options = []
    ): array;

    /**
     * Download a file from Telegram
     *
     * @param string $fileId Telegram file ID
     * @param string $savePath Path to save the downloaded file
     * @return bool Success status
     * @throws \Common\Files\Telegram\Exceptions\TelegramDownloadException
     */
    public function downloadFile(string $fileId, string $savePath): bool;

    /**
     * Delete a file from Telegram
     *
     * @param string $channelId Channel ID
     * @param int $messageId Message ID
     * @return bool Success status
     */
    public function deleteFile(string $channelId, int $messageId): bool;

    /**
     * Get file information
     *
     * @param string $fileId Telegram file ID
     * @return array File information
     */
    public function getFileInfo(string $fileId): array;

    /**
     * Check if client is authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool;

    /**
     * Get client type (bot or user)
     *
     * @return string
     */
    public function getClientType(): string;
}
