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

            // Extract result (document or type-specific)
            $fileId = null;
            $fileUniqueId = null;
            
            // Try document first (primary method)
            if ($response->getDocument()) {
                $fileId = $response->getDocument()->getFileId();
                $fileUniqueId = $response->getDocument()->getFileUniqueId();
                
                Log::info('Document uploaded successfully', [
                    'file_id' => $fileId,
                    'file_unique_id' => $fileUniqueId,
                ]);
            }
            // Fallback: Photo (from fallback upload method)
            elseif ($response->getPhoto()) {
                $photos = $response->getPhoto();
                // Get largest photo (last element)
                if (method_exists($photos, 'last')) {
                    $photo = $photos->last();
                } elseif (is_array($photos)) {
                    $photo = end($photos);
                } else {
                    $photo = null;
                }
                
                if ($photo && method_exists($photo, 'getFileId')) {
                    $fileId = $photo->getFileId();
                    $fileUniqueId = $photo->getFileUniqueId();
                    Log::info('Photo uploaded (fallback method)', ['file_id' => $fileId]);
                }
            }
            // Fallback: Video
            elseif ($response->getVideo()) {
                $fileId = $response->getVideo()->getFileId();
                $fileUniqueId = $response->getVideo()->getFileUniqueId();
                Log::info('Video uploaded (fallback method)', ['file_id' => $fileId]);
            }
            // Fallback: Audio
            elseif ($response->getAudio()) {
                $fileId = $response->getAudio()->getFileId();
                $fileUniqueId = $response->getAudio()->getFileUniqueId();
                Log::info('Audio uploaded (fallback method)', ['file_id' => $fileId]);
            }
            else {
                Log::error('No file found in response', [
                    'response_type' => get_class($response),
                ]);
            }
            
            if (!$fileId) {
                Log::error('No file_id in Telegram response', [
                    'response_type' => get_class($response),
                    'has_document' => $response->getDocument() ? 'yes' : 'no',
                    'has_photo' => $response->getPhoto() ? 'yes' : 'no',
                    'has_video' => $response->getVideo() ? 'yes' : 'no',
                    'has_audio' => $response->getAudio() ? 'yes' : 'no',
                    'file' => $filePath,
                ]);
                throw TelegramUploadException::uploadFailed('No file_id in response');
            }
            
            Log::info('File uploaded to Telegram via Bot', [
                'file_id' => $fileId,
                'message_id' => $response->getMessageId(),
                'size' => $fileSize,
            ]);
            
            return [
                'success' => true,
                'file_id' => $fileId,
                'file_unique_id' => $fileUniqueId,
                'message_id' => $response->getMessageId(),
                'channel_id' => $channelId,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'upload_method' => 'bot',
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

        Log::info('Uploading file by type', [
            'mime_type' => $mimeType,
            'channel_id' => $channelId,
        ]);

        // PRIMARY METHOD: Upload as DOCUMENT to preserve original quality
        // Document type keeps the original file unchanged and easier to download
        try {
            $params['document'] = $inputFile;
            Log::info('Primary: Sending as document (preserves original quality)');
            $response = $this->telegram->sendDocument($params);
            return $response;
        } catch (TelegramSDKException $e) {
            Log::warning('Document upload failed, trying type-specific fallback', [
                'error' => $e->getMessage(),
                'mime_type' => $mimeType,
            ]);
        }

        // FALLBACK: Try type-specific upload methods
        // Photo
        if (str_starts_with($mimeType, 'image/') && !str_contains($mimeType, 'gif')) {
            $params['photo'] = $inputFile;
            Log::info('Fallback: Sending as photo');
            return $this->telegram->sendPhoto($params);
        }

        // Video
        if (str_starts_with($mimeType, 'video/')) {
            $params['video'] = $inputFile;
            Log::info('Fallback: Sending as video');
            return $this->telegram->sendVideo($params);
        }

        // Audio
        if (str_starts_with($mimeType, 'audio/')) {
            $params['audio'] = $inputFile;
            Log::info('Fallback: Sending as audio');
            return $this->telegram->sendAudio($params);
        }

        // Last resort: try document again
        $params['document'] = $inputFile;
        Log::info('Last resort: Sending as document');
        $response = $this->telegram->sendDocument($params);
        
        Log::info('Upload response received', [
            'response_class' => get_class($response),
            'message_id' => $response->getMessageId() ?? 'N/A',
        ]);
        
        return $response;
    }

    /**
     * Download file from Telegram
     *
     * @param string $fileId Telegram file ID
     * @param string $savePath Local path to save the file
     * @param array $fallbackData Optional: message_id & channel_id for fallback
     * @return bool
     * @throws TelegramDownloadException
     */
    public function downloadFile(string $fileId, string $savePath, array $fallbackData = []): bool
    {
        try {
            // Method 1: Try with file_id (direct approach)
            Log::info('Attempting download with file_id', ['file_id' => $fileId]);
            
            $file = $this->telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            // Ensure directory exists
            $directory = dirname($savePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Download file directly to path
            $downloadedPath = $this->telegram->downloadFile($filePath, $savePath);

            // Verify file was saved
            if (!file_exists($savePath)) {
                throw new \Exception('File was not saved to: ' . $savePath);
            }

            $fileSize = filesize($savePath);

            Log::info('File downloaded via Bot API (file_id method)', [
                'file_id' => $fileId,
                'save_path' => $savePath,
                'size' => $fileSize,
            ]);

            return true;
            
        } catch (\Exception $e) {
            Log::warning('file_id method failed, trying fallback', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'has_fallback' => !empty($fallbackData['message_id']),
            ]);

            // Method 2: Fallback - Get message and extract file_id
            if (!empty($fallbackData['message_id']) && !empty($fallbackData['channel_id'])) {
                return $this->downloadFileByMessage(
                    $fallbackData['message_id'],
                    $fallbackData['channel_id'],
                    $savePath
                );
            }

            // No fallback available
            throw TelegramDownloadException::downloadFailed(
                'Download failed and no fallback data available: ' . $e->getMessage(),
                ['file_id' => $fileId]
            );
        }
    }

    /**
     * Download file by fetching message first (fallback method)
     *
     * @param int $messageId
     * @param string $channelId
     * @param string $savePath
     * @return bool
     * @throws TelegramDownloadException
     */
    protected function downloadFileByMessage(int $messageId, string $channelId, string $savePath): bool
    {
        try {
            Log::info('Fallback: Downloading using message_id via forwardMessage trick', [
                'message_id' => $messageId,
                'channel_id' => $channelId,
            ]);

            // Solution: Forward message to self (Saved Messages) to get file object
            // Get bot info to get bot's chat id
            $me = $this->telegram->getMe();
            $botId = $me->getId();

            // IMPORTANT: We need to create a temporary "saved messages" channel
            // Actually, we'll use a different approach: copy message
            // Bot API limitation: we can't copyMessage or forwardMessage to get file_id easily
            
            // New approach: Use getChat to verify channel, then make assumption
            // That the file_id is stable and use stored one
            
            // Actually, let's try a different solution:
            // Some file_id formats are short-lived. We need to use copyMessage API
            // But that's not available in PHP SDK easily.
            
            // Best solution for now: Log detailed error and suggest User Account
            Log::error('Bot API cannot re-download with message_id alone', [
                'message_id' => $messageId,
                'channel_id' => $channelId,
                'reason' => 'file_id expired or invalid, Bot API has no getMessage method',
                'solution' => 'For reliable downloads, use User Account (MTProto) which supports getMessage',
            ]);
            
            throw TelegramDownloadException::downloadFailed(
                'Bot API cannot download file: file_id is invalid and Bot API cannot fetch message by ID. ' .
                'Solution: Use User Account credentials for reliable file access.',
                [
                    'message_id' => $messageId,
                    'channel_id' => $channelId,
                    'recommendation' => 'Configure User Account (API ID, API Hash, Phone) in Settings',
                ]
            );
            
        } catch (TelegramSDKException $e) {
            Log::error('Bot API fallback failed', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);
            
            throw TelegramDownloadException::downloadFailed(
                'Bot API download failed: ' . $e->getMessage(),
                ['message_id' => $messageId, 'channel_id' => $channelId]
            );
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
