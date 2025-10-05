<?php

namespace Common\Files\Telegram;

use Common\Files\Telegram\Contracts\TelegramClientInterface;
use Common\Files\Telegram\Exceptions\TelegramAuthException;
use Common\Files\Telegram\Exceptions\TelegramConfigException;
use Common\Files\Telegram\Exceptions\TelegramDownloadException;
use Common\Files\Telegram\Exceptions\TelegramUploadException;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;

/**
 * Telegram Bot API Client
 * Handles file operations using Bot API (files up to 50MB)
 */
class TelegramBotClient implements TelegramClientInterface
{
    protected Api $telegram;
    protected string $botToken;
    protected bool $authenticated = false;

    /**
     * Maximum file size for Bot API (50MB)
     */
    public const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB in bytes

    public function __construct(?string $botToken = null)
    {
        // Get bot token from parameter or config/env (not from database)
        $this->botToken = $botToken 
            ?? config('services.telegram.bot_token') ?? settings('storage_telegram_bot_token');

        if (empty($this->botToken)) {
            throw TelegramConfigException::missingConfig('bot_token');
        }

        try {
            $this->telegram = new Api($this->botToken);
            $this->authenticate();
        } catch (Exception $e) {
            Log::error('Telegram Bot API initialization failed', [
                'error' => $e->getMessage(),
            ]);
            throw TelegramAuthException::invalidToken();
        }
    }

    /**
     * Authenticate the bot
     */
    protected function authenticate(): void
    {
        try {
            $me = $this->telegram->getMe();
            $this->authenticated = true;

            Log::info('Telegram Bot authenticated', [
                'bot_id' => $me->getId(),
                'bot_username' => $me->getUsername(),
            ]);
        } catch (TelegramSDKException $e) {
            $this->authenticated = false;
            throw TelegramAuthException::invalidToken();
        }
    }

    /**
     * Upload a file to Telegram channel using Bot API
     *
     * @param string $filePath
     * @param string $channelId
     * @param array $options
     * @return array
     * @throws TelegramUploadException
     */
    public function uploadFile(
        string $filePath,
        string $channelId,
        array $options = []
    ): array {
        try {
            // Check file exists
            if (!file_exists($filePath)) {
                throw TelegramUploadException::invalidFile(
                    'File does not exist: ' . $filePath
                );
            }

            // Check file size
            $fileSize = filesize($filePath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                throw TelegramUploadException::fileTooLarge(
                    $fileSize,
                    self::MAX_FILE_SIZE
                );
            }

            // Prepare file upload
            $inputFile = InputFile::create($filePath, $options['filename'] ?? null);

            // Determine file type and upload accordingly
            $mimeType = mime_content_type($filePath);
            $response = $this->uploadByType($inputFile, $channelId, $mimeType, $options);

            // Extract result
            return [
                'success' => true,
                'file_id' => $response->getDocument()
                    ? $response->getDocument()->getFileId()
                    : ($response->getPhoto()
                        ? end($response->getPhoto())->getFileId()
                        : ($response->getVideo()
                            ? $response->getVideo()->getFileId()
                            : null)),
                'file_unique_id' => $response->getDocument()
                    ? $response->getDocument()->getFileUniqueId()
                    : null,
                'message_id' => $response->getMessageId(),
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_at' => now()->toDateTimeString(),
            ];
        } catch (TelegramSDKException $e) {
            Log::error('Telegram Bot upload failed', [
                'file' => $filePath,
                'channel' => $channelId,
                'error' => $e->getMessage(),
            ]);

            // Check if it's a channel access error
            if (str_contains($e->getMessage(), 'chat not found')) {
                throw TelegramUploadException::channelNotAccessible($channelId);
            }

            throw TelegramUploadException::uploadFailed($e->getMessage(), [
                'file_path' => $filePath,
                'channel_id' => $channelId,
            ]);
        } catch (TelegramUploadException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Unexpected error during Bot upload', [
                'error' => $e->getMessage(),
            ]);
            throw TelegramUploadException::uploadFailed($e->getMessage());
        }
    }

    /**
     * Upload file based on MIME type
     */
    protected function uploadByType(
        InputFile $inputFile,
        string $channelId,
        string $mimeType,
        array $options
    ) {
        $params = [
            'chat_id' => $channelId,
            'caption' => $options['caption'] ?? '',
        ];

        // Photo
        if (str_starts_with($mimeType, 'image/') && !str_contains($mimeType, 'gif')) {
            $params['photo'] = $inputFile;
            return $this->telegram->sendPhoto($params);
        }

        // Video
        if (str_starts_with($mimeType, 'video/')) {
            $params['video'] = $inputFile;
            return $this->telegram->sendVideo($params);
        }

        // Audio
        if (str_starts_with($mimeType, 'audio/')) {
            $params['audio'] = $inputFile;
            return $this->telegram->sendAudio($params);
        }

        // Document (default)
        $params['document'] = $inputFile;
        return $this->telegram->sendDocument($params);
    }

    /**
     * Download a file from Telegram
     *
     * @param string $fileId
     * @param string $savePath
     * @return bool
     * @throws TelegramDownloadException
     */
    public function downloadFile(string $fileId, string $savePath): bool
    {
        try {
            // Get file info
            $file = $this->telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            // Download file
            $fileContent = $this->telegram->downloadFile($filePath);

            // Save to disk
            $directory = dirname($savePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $result = file_put_contents($savePath, $fileContent);

            if ($result === false) {
                throw TelegramDownloadException::downloadFailed(
                    'Failed to save file to: ' . $savePath
                );
            }

            Log::info('File downloaded via Bot API', [
                'file_id' => $fileId,
                'save_path' => $savePath,
                'size' => $result,
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('Telegram Bot download failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'file not found')) {
                throw TelegramDownloadException::fileNotFound($fileId);
            }

            throw TelegramDownloadException::downloadFailed($e->getMessage(), [
                'file_id' => $fileId,
            ]);
        } catch (Exception $e) {
            throw TelegramDownloadException::downloadFailed($e->getMessage());
        }
    }

    /**
     * Delete a file from Telegram (delete message)
     *
     * @param string $channelId
     * @param int $messageId
     * @return bool
     */
    public function deleteFile(string $channelId, int $messageId): bool
    {
        try {
            $result = $this->telegram->deleteMessage([
                'chat_id' => $channelId,
                'message_id' => $messageId,
            ]);

            Log::info('Message deleted via Bot API', [
                'channel_id' => $channelId,
                'message_id' => $messageId,
            ]);

            return $result;
        } catch (TelegramSDKException $e) {
            Log::error('Failed to delete message via Bot API', [
                'channel_id' => $channelId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get file information
     *
     * @param string $fileId
     * @return array
     */
    public function getFileInfo(string $fileId): array
    {
        try {
            $file = $this->telegram->getFile(['file_id' => $fileId]);

            return [
                'file_id' => $file->getFileId(),
                'file_unique_id' => $file->getFileUniqueId(),
                'file_size' => $file->getFileSize(),
                'file_path' => $file->getFilePath(),
            ];
        } catch (TelegramSDKException $e) {
            Log::error('Failed to get file info', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if bot is authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * Get client type
     *
     * @return string
     */
    public function getClientType(): string
    {
        return 'bot';
    }

    /**
     * Get bot information
     *
     * @return array
     */
    public function getBotInfo(): array
    {
        try {
            $me = $this->telegram->getMe();
            return [
                'id' => $me->getId(),
                'username' => $me->getUsername(),
                'first_name' => $me->getFirstName(),
                'is_bot' => $me->getIsBot(),
            ];
        } catch (TelegramSDKException $e) {
            return [];
        }
    }

    /**
     * Forward a message to another chat
     *
     * @param string $fromChatId Source chat ID (channel)
     * @param int $messageId Message ID to forward
     * @param string $toChatId Target chat ID
     * @return array
     * @throws TelegramUploadException
     */
    public function forwardMessage(
        string $fromChatId,
        int $messageId,
        string $toChatId
    ): array {
        try {
            $result = $this->telegram->forwardMessage([
                'chat_id' => $toChatId,
                'from_chat_id' => $fromChatId,
                'message_id' => $messageId,
            ]);

            Log::info('Message forwarded successfully', [
                'from_chat' => $fromChatId,
                'to_chat' => $toChatId,
                'message_id' => $messageId,
                'new_message_id' => $result->getMessageId(),
            ]);

            return [
                'success' => true,
                'message_id' => $result->getMessageId(),
                'chat_id' => $result->getChat()->getId(),
                'date' => $result->getDate(),
            ];
        } catch (TelegramSDKException $e) {
            Log::error('Failed to forward message', [
                'from_chat' => $fromChatId,
                'to_chat' => $toChatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            throw TelegramUploadException::uploadFailed(
                'Failed to forward message: ' . $e->getMessage(),
                [
                    'from_chat' => $fromChatId,
                    'to_chat' => $toChatId,
                    'message_id' => $messageId,
                ]
            );
        }
    }

    /**
     * Get bot information
     *
     * @return array
     * @throws TelegramAuthException
     */
    public function getMe(): array
    {
        try {
            $me = $this->telegram->getMe();
            return [
                'id' => $me->getId(),
                'username' => $me->getUsername(),
                'first_name' => $me->getFirstName(),
                'is_bot' => true,
            ];
        } catch (TelegramSDKException $e) {
            Log::error('Failed to get bot info', [
                'error' => $e->getMessage(),
            ]);
            throw TelegramAuthException::invalidToken();
        }
    }

    /**
     * Send a text message to a channel
     *
     * @param string $chatId
     * @param string $text
     * @param array $options
     * @return array
     * @throws TelegramUploadException
     */
    public function sendMessage(
        string $chatId,
        string $text,
        array $options = []
    ): array {
        try {
            $params = array_merge(
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                ],
                $options
            );

            $message = $this->telegram->sendMessage($params);

            return [
                'message_id' => $message->getMessageId(),
                'chat_id' => $message->getChat()->getId(),
                'date' => $message->getDate(),
            ];
        } catch (TelegramSDKException $e) {
            Log::error('Failed to send message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            throw TelegramUploadException::uploadFailed(
                'Failed to send message: ' . $e->getMessage(),
                ['chat_id' => $chatId]
            );
        }
    }

    /**
     * Get webhook info
     *
     * @return array
     * @throws TelegramUploadException
     */
    public function getWebhookInfo(): array
    {
        try {
            $webhookInfo = $this->telegram->getWebhookInfo();

            return [
                'url' => $webhookInfo->getUrl() ?? '',
                'has_custom_certificate' => $webhookInfo->getHasCustomCertificate() ?? false,
                'pending_update_count' => $webhookInfo->getPendingUpdateCount() ?? 0,
                'last_error_date' => $webhookInfo->getLastErrorDate() ?? null,
                'last_error_message' => $webhookInfo->getLastErrorMessage() ?? null,
                'max_connections' => $webhookInfo->getMaxConnections() ?? null,
                'allowed_updates' => $webhookInfo->getAllowedUpdates() ?? [],
            ];
        } catch (TelegramSDKException $e) {
            Log::error('Failed to get webhook info', [
                'error' => $e->getMessage(),
            ]);

            throw TelegramUploadException::uploadFailed(
                'Failed to get webhook info: ' . $e->getMessage()
            );
        }
    }

    /**
     * Set webhook
     *
     * @param string $url
     * @param array $options
     * @return bool
     * @throws TelegramUploadException
     */
    public function setWebhook(string $url, array $options = []): bool
    {
        try {
            $params = array_merge(['url' => $url], $options);
            $result = $this->telegram->setWebhook($params);

            Log::info('Telegram webhook set', [
                'url' => $url,
                'result' => $result,
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('Failed to set webhook', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw TelegramUploadException::uploadFailed(
                'Failed to set webhook: ' . $e->getMessage(),
                ['url' => $url]
            );
        }
    }

    /**
     * Delete webhook
     *
     * @param bool $dropPendingUpdates
     * @return bool
     * @throws TelegramUploadException
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        try {
            $result = $this->telegram->deleteWebhook([
                'drop_pending_updates' => $dropPendingUpdates,
            ]);

            Log::info('Telegram webhook deleted', [
                'drop_pending_updates' => $dropPendingUpdates,
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('Failed to delete webhook', [
                'error' => $e->getMessage(),
            ]);

            throw TelegramUploadException::uploadFailed(
                'Failed to delete webhook: ' . $e->getMessage()
            );
        }
    }
}
