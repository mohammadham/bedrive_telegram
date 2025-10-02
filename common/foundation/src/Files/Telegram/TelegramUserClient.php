<?php

namespace Common\Files\Telegram;

use Common\Files\Telegram\Contracts\TelegramClientInterface;
use Common\Files\Telegram\Exceptions\TelegramAuthException;
use Common\Files\Telegram\Exceptions\TelegramConfigException;
use Common\Files\Telegram\Exceptions\TelegramDownloadException;
use Common\Files\Telegram\Exceptions\TelegramUploadException;
use danog\MadelineProto\API;
use danog\MadelineProto\Exception as MadelineException;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\RemoteUrl;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Telegram User Account Client (MTProto)
 * Handles file operations using user account (files up to 2GB)
 */
class TelegramUserClient implements TelegramClientInterface
{
    protected ?API $MadelineProto = null;
    protected int $apiId;
    protected string $apiHash;
    protected string $sessionFile;
    protected bool $authenticated = false;

    /**
     * Maximum file size for User Account (2GB)
     */
    public const MAX_FILE_SIZE = 2 * 1024 * 1024 * 1024; // 2GB in bytes

    public function __construct(
        ?int $apiId = null,
        ?string $apiHash = null,
        ?string $sessionFile = null
    ) {
        $this->apiId = $apiId ?? (int) config('services.telegram.api_id');
        $this->apiHash = $apiHash ?? config('services.telegram.api_hash');
        $this->sessionFile =
            $sessionFile ?? config('services.telegram.session_file');

        // Validate configuration
        if (empty($this->apiId) || empty($this->apiHash)) {
            throw TelegramConfigException::missingConfig('api_id or api_hash');
        }

        if (empty($this->sessionFile)) {
            $this->sessionFile = storage_path('app/telegram/session.madeline');
        }

        // Ensure session directory exists
        $sessionDir = dirname($this->sessionFile);
        if (!is_dir($sessionDir)) {
            mkdir($sessionDir, 0755, true);
        }

        try {
            $this->initializeMadelineProto();
        } catch (Exception $e) {
            Log::error('MadelineProto initialization failed', [
                'error' => $e->getMessage(),
            ]);
            throw TelegramAuthException::invalidCredentials($e->getMessage());
        }
    }

    /**
     * Initialize MadelineProto
     */
    protected function initializeMadelineProto(): void
    {
        try {
            $settings = [
                'app_info' => [
                    'api_id' => $this->apiId,
                    'api_hash' => $this->apiHash,
                ],
                'logger' => [
                    'logger' => 3, // File logger
                    'logger_param' => storage_path('logs/madelineproto.log'),
                    'logger_level' => 3, // Warning level
                ],
                'serialization' => [
                    'serialization_interval' => 30,
                ],
                'upload' => [
                    'allow_automatic_upload' => true,
                ],
            ];

            $this->MadelineProto = new API($this->sessionFile, $settings);

            // Start and authenticate
            $this->MadelineProto->start();

            $this->authenticated = true;

            Log::info('MadelineProto initialized successfully', [
                'session_file' => $this->sessionFile,
            ]);
        } catch (MadelineException $e) {
            $this->authenticated = false;
            throw new Exception('Failed to initialize MadelineProto: ' . $e->getMessage());
        }
    }

    /**
     * Upload a file using User Account (MTProto)
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

            // Upload file
            $file = new LocalFile($filePath);

            // Send to channel
            $result = $this->MadelineProto->messages->sendMedia([
                'peer' => $channelId,
                'media' => [
                    '_' => 'inputMediaUploadedDocument',
                    'file' => $file,
                    'mime_type' => mime_content_type($filePath),
                    'attributes' => [
                        [
                            '_' => 'documentAttributeFilename',
                            'file_name' =>
                                $options['filename'] ?? basename($filePath),
                        ],
                    ],
                ],
                'message' => $options['caption'] ?? '',
            ]);

            // Extract message info
            $message = $result['updates'][0]['message'] ?? $result;
            $document = $message['media']['document'] ?? null;

            Log::info('File uploaded via User Account', [
                'file_path' => $filePath,
                'channel_id' => $channelId,
                'message_id' => $message['id'] ?? null,
            ]);

            return [
                'success' => true,
                'file_id' => $document['id'] ?? null,
                'file_unique_id' => null, // MTProto doesn't use unique_id like Bot API
                'message_id' => $message['id'] ?? null,
                'file_size' => $fileSize,
                'mime_type' => $document['mime_type'] ?? mime_content_type($filePath),
                'uploaded_at' => now()->toDateTimeString(),
                'document' => $document,
            ];
        } catch (MadelineException $e) {
            Log::error('MadelineProto upload failed', [
                'file' => $filePath,
                'channel' => $channelId,
                'error' => $e->getMessage(),
            ]);

            throw TelegramUploadException::uploadFailed($e->getMessage(), [
                'file_path' => $filePath,
                'channel_id' => $channelId,
            ]);
        } catch (TelegramUploadException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Unexpected error during User Account upload', [
                'error' => $e->getMessage(),
            ]);
            throw TelegramUploadException::uploadFailed($e->getMessage());
        }
    }

    /**
     * Download a file using User Account
     *
     * @param string $fileId This should be message data or file reference
     * @param string $savePath
     * @return bool
     * @throws TelegramDownloadException
     */
    public function downloadFile(string $fileId, string $savePath): bool
    {
        try {
            // For MadelineProto, we need to download from message
            // $fileId should contain channel and message info
            // Format: channel_id:message_id or document data

            // Parse fileId (assuming format: channel_id:message_id)
            if (str_contains($fileId, ':')) {
                [$channelId, $messageId] = explode(':', $fileId, 2);

                // Get message
                $messages = $this->MadelineProto->channels->getMessages([
                    'channel' => $channelId,
                    'id' => [(int) $messageId],
                ]);

                $message = $messages['messages'][0] ?? null;
                if (!$message || !isset($message['media']['document'])) {
                    throw TelegramDownloadException::fileNotFound($fileId);
                }

                $document = $message['media']['document'];
            } else {
                // Assume $fileId is direct document reference
                $document = json_decode($fileId, true);
            }

            // Ensure directory exists
            $directory = dirname($savePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Download file
            $downloadedPath = $this->MadelineProto->downloadToFile(
                $document,
                $savePath
            );

            Log::info('File downloaded via User Account', [
                'file_id' => $fileId,
                'save_path' => $savePath,
            ]);

            return file_exists($downloadedPath);
        } catch (MadelineException $e) {
            Log::error('MadelineProto download failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            throw TelegramDownloadException::downloadFailed($e->getMessage(), [
                'file_id' => $fileId,
            ]);
        } catch (Exception $e) {
            throw TelegramDownloadException::downloadFailed($e->getMessage());
        }
    }

    /**
     * Delete a message/file
     *
     * @param string $channelId
     * @param int $messageId
     * @return bool
     */
    public function deleteFile(string $channelId, int $messageId): bool
    {
        try {
            $result = $this->MadelineProto->channels->deleteMessages([
                'channel' => $channelId,
                'id' => [$messageId],
            ]);

            Log::info('Message deleted via User Account', [
                'channel_id' => $channelId,
                'message_id' => $messageId,
            ]);

            return true;
        } catch (MadelineException $e) {
            Log::error('Failed to delete message via User Account', [
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
            // Parse fileId to get channel and message
            if (str_contains($fileId, ':')) {
                [$channelId, $messageId] = explode(':', $fileId, 2);

                $messages = $this->MadelineProto->channels->getMessages([
                    'channel' => $channelId,
                    'id' => [(int) $messageId],
                ]);

                $message = $messages['messages'][0] ?? null;
                if (!$message) {
                    return [];
                }

                $document = $message['media']['document'] ?? null;
                if (!$document) {
                    return [];
                }

                return [
                    'file_id' => $document['id'] ?? null,
                    'file_size' => $document['size'] ?? null,
                    'mime_type' => $document['mime_type'] ?? null,
                    'message_id' => $message['id'],
                ];
            }

            return [];
        } catch (MadelineException $e) {
            Log::error('Failed to get file info', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if authenticated
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
        return 'user';
    }

    /**
     * Get current user info
     *
     * @return array
     */
    public function getUserInfo(): array
    {
        try {
            $me = $this->MadelineProto->getSelf();
            return [
                'id' => $me['id'] ?? null,
                'phone' => $me['phone'] ?? null,
                'username' => $me['username'] ?? null,
                'first_name' => $me['first_name'] ?? null,
                'last_name' => $me['last_name'] ?? null,
            ];
        } catch (MadelineException $e) {
            return [];
        }
    }

    /**
     * Logout and clear session
     */
    public function logout(): bool
    {
        try {
            $this->MadelineProto->logout();
            if (file_exists($this->sessionFile)) {
                unlink($this->sessionFile);
            }
            $this->authenticated = false;
            return true;
        } catch (Exception $e) {
            Log::error('Logout failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
