<?php

namespace Common\Files\Telegram;

use Common\Files\Telegram\Contracts\TelegramClientInterface;
use Common\Files\Telegram\Exceptions\TelegramConfigException;
use Common\Files\Telegram\Exceptions\TelegramUploadException;
use Illuminate\Support\Facades\Log;

/**
 * Telegram File Manager
 * 
 * Manages file operations and automatically selects between
 * Bot API (< 50MB) and User Account (up to 2GB) based on file size
 */
class TelegramFileManager
{
    protected ?TelegramBotClient $botClient = null;
    protected ?TelegramUserClient $userClient = null;
    protected string $defaultChannelId;

    /**
     * File size threshold (50MB)
     */
    public const SIZE_THRESHOLD = 50 * 1024 * 1024;

    public function __construct(?string $channelId = null)
    {
        // Channel ID should come from config (loaded from .env)
        $this->defaultChannelId = $channelId 
            ?? config('services.telegram.channel_id')
            ?? '';

        if (empty($this->defaultChannelId)) {
            throw TelegramConfigException::missingConfig(
                'channel_id',
                'Please configure Telegram settings in Admin Panel → Settings → Uploading'
            );
        }

        // Initialize clients lazily
    }

    /**
     * Get or initialize Bot client
     */
    protected function getBotClient(): TelegramBotClient
    {
        if (!$this->botClient) {
            $this->botClient = new TelegramBotClient();
        }
        return $this->botClient;
    }

    /**
     * Get or initialize User client
     */
    protected function getUserClient(): TelegramUserClient
    {
        if (!$this->userClient) {
            $this->userClient = new TelegramUserClient();
        }
        return $this->userClient;
    }

    /**
     * Select appropriate client based on file size
     *
     * @param int $fileSize
     * @return TelegramClientInterface
     */
    protected function selectClient(int $fileSize): TelegramClientInterface
    {
        if ($fileSize <= self::SIZE_THRESHOLD) {
            Log::info('Selected Bot API client', ['file_size' => $fileSize]);
            return $this->getBotClient();
        }

        Log::info('Selected User Account client', ['file_size' => $fileSize]);
        return $this->getUserClient();
    }

    /**
     * Upload a file using the appropriate method
     *
     * @param string $filePath
     * @param string|null $channelId
     * @param array $options
     * @return array
     */
    public function uploadFile(
        string $filePath,
        ?string $channelId = null,
        array $options = []
    ): array {
        $channelId = $channelId ?? $this->defaultChannelId;

        // Check file exists
        if (!file_exists($filePath)) {
            throw TelegramUploadException::invalidFile(
                "File not found: {$filePath}"
            );
        }

        // Get file size
        $fileSize = filesize($filePath);

        // Check maximum limit (2GB)
        if ($fileSize > TelegramUserClient::MAX_FILE_SIZE) {
            throw TelegramUploadException::fileTooLarge(
                $fileSize,
                TelegramUserClient::MAX_FILE_SIZE
            );
        }

        // Select client and upload
        $client = $this->selectClient($fileSize);
        $result = $client->uploadFile($filePath, $channelId, $options);

        // Add client type to result
        $result['upload_method'] = $client->getClientType();
        $result['channel_id'] = $channelId;

        return $result;
    }

    /**
     * Download a file
     *
     * @param string $fileId File ID or reference (format: channel_id:message_id for user)
     * @param string $savePath
     * @param string $method 'bot' or 'user'
     * @return bool
     */
    public function downloadFile(
        string $fileId,
        string $savePath,
        string $method = 'bot'
    ): bool {
        $client =
            $method === 'bot' ? $this->getBotClient() : $this->getUserClient();
        return $client->downloadFile($fileId, $savePath);
    }

    /**
     * Delete a file
     *
     * @param string $channelId
     * @param int $messageId
     * @param string $method 'bot' or 'user'
     * @return bool
     */
    public function deleteFile(
        string $channelId,
        int $messageId,
        string $method = 'bot'
    ): bool {
        $client =
            $method === 'bot' ? $this->getBotClient() : $this->getUserClient();
        return $client->deleteFile($channelId, $messageId);
    }

    /**
     * Get file information
     *
     * @param string $fileId
     * @param string $method 'bot' or 'user'
     * @return array
     */
    public function getFileInfo(string $fileId, string $method = 'bot'): array
    {
        $client =
            $method === 'bot' ? $this->getBotClient() : $this->getUserClient();
        return $client->getFileInfo($fileId);
    }

    /**
     * Determine which method should be used for a file
     *
     * @param int $fileSize
     * @return string 'bot' or 'user'
     */
    public static function determineMethod(int $fileSize): string
    {
        return $fileSize <= self::SIZE_THRESHOLD ? 'bot' : 'user';
    }

    /**
     * Check if file can be uploaded
     *
     * @param int $fileSize
     * @return array
     */
    public static function canUpload(int $fileSize): array
    {
        if ($fileSize <= self::SIZE_THRESHOLD) {
            return [
                'can_upload' => true,
                'method' => 'bot',
                'reason' => 'File size is within Bot API limits (≤ 50MB)',
            ];
        }

        if ($fileSize <= TelegramUserClient::MAX_FILE_SIZE) {
            return [
                'can_upload' => true,
                'method' => 'user',
                'reason' =>
                    'File size requires User Account upload (50MB - 2GB)',
            ];
        }

        return [
            'can_upload' => false,
            'method' => null,
            'reason' => 'File size exceeds Telegram limits (> 2GB)',
        ];
    }

    /**
     * Test connection for both clients
     *
     * @return array
     */
    public function testConnections(): array
    {
        $results = [
            'bot' => null,
            'user' => null,
        ];

        // Test Bot API
        try {
            $botClient = $this->getBotClient();
            $results['bot'] = [
                'success' => $botClient->isAuthenticated(),
                'info' => $botClient->getBotInfo(),
            ];
        } catch (\Exception $e) {
            $results['bot'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Test User Account
        try {
            $userClient = $this->getUserClient();
            $results['user'] = [
                'success' => $userClient->isAuthenticated(),
                'info' => $userClient->getUserInfo(),
            ];
        } catch (\Exception $e) {
            $results['user'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Get default channel ID
     *
     * @return string
     */
    public function getDefaultChannelId(): string
    {
        return $this->defaultChannelId;
    }

    /**
     * Set default channel ID
     *
     * @param string $channelId
     */
    public function setDefaultChannelId(string $channelId): void
    {
        $this->defaultChannelId = $channelId;
    }
}
