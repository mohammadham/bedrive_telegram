<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Exception;
use Common\Settings\Settings;
use Illuminate\Support\Facades\Http;

class TelegramStorageDriver
{
    protected $botToken;
    protected $configPath;

    public function __construct()
    {
        $this->loadSettingsFromDatabase();
    }

    protected function loadSettingsFromDatabase()
    {
        try {
            $settings = app(Settings::class);
            $this->botToken = $settings->get('telegram_bot_token');
            $this->configPath = $settings->get('storage_telegram_config_path');
        } catch (Exception $e) {
            Log::error('Could not load Telegram settings from database: ' . $e->getMessage());
        }
    }

    protected function buildCommand(string $baseCommand, array $args = []): array
    {
        $command = [$baseCommand];
        if ($this->configPath && file_exists($this->configPath)) {
            $command[] = '--config';
            $command[] = $this->configPath;
        }
        return array_merge($command, $args);
    }

    public function uploadFile($filePath, $destination = null, $chatId = null, $forwardToChatId = null)
    {
        $caption = $destination ?: basename($filePath);
        $args = [
            '--to', $chatId ?: 'me',
            '--caption', $caption,
            '--print-file-id',
        ];

        if ($forwardToChatId) {
            $args[] = '--forward';
            $args[] = $forwardToChatId;
        }

        $args[] = $filePath;

        $command = $this->buildCommand('telegram-upload', $args);
        $result = Process::run($command);

        if ($result->successful()) {
            $fileId = trim($result->output());
            Log::info("File uploaded to Telegram: $filePath with ID: $fileId");
            return [
                'success' => true,
                'file_id' => $fileId,
                'path' => $destination ?: basename($filePath),
            ];
        } else {
            $error = $result->errorOutput();
            Log::error("Failed to upload file to Telegram: $error");
            throw new Exception("Upload failed: " . $this->parseErrorMessage($error));
        }
    }

    public function downloadFile($fileId, $destination, $chatId = null)
    {
        $tempDir = sys_get_temp_dir() . '/telegram_downloads_' . Str::random(8);
        if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new \RuntimeException("Could not create temp directory: $tempDir");
        }

        $args = ['--from', $chatId ?: 'me'];
        $command = $this->buildCommand('telegram-download', $args);
        $result = Process::setWorkingDirectory($tempDir)->run($command);

        if ($result->successful()) {
            $files = array_diff(scandir($tempDir), ['.', '..']);
            if (empty($files)) {
                throw new Exception('Downloaded file not found in temp directory.');
            }
            $downloadedFile = $tempDir . '/' . reset($files);
            rename($downloadedFile, $destination);
            rmdir($tempDir);
            Log::info("File downloaded from Telegram: $fileId");
            return true;
        } else {
            rmdir($tempDir);
            $error = $result->errorOutput();
            Log::error("Failed to download file from Telegram: $error");
            throw new Exception("Download failed: " . $this->parseErrorMessage($error));
        }
    }

    /**
     * Delete a file from Telegram using the Bot API.
     *
     * @param string $fileId The message ID to delete.
     * @param string|null $chatId The chat ID where the message is located.
     * @return bool
     * @throws Exception
     */
    public function deleteFile($fileId, $chatId = null): bool
    {
        $token = $this->getBotToken();
        $chatId = $chatId ?: app(Settings::class)->get('storage_telegram_chat_id', 'me');

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/deleteMessage", [
                'chat_id' => $chatId,
                'message_id' => $fileId,
            ]);

            if ($response->successful() && $response->json('ok')) {
                Log::info("File deleted from Telegram: message_id {$fileId}");
                return true;
            } else {
                $error = $response->json('description') ?: 'Failed to delete file from Telegram.';
                Log::error("Telegram delete error for message {$fileId}: {$error}");
                return false;
            }
        } catch (Exception $e) {
            Log::error("Error deleting file from Telegram: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a file exists in Telegram using the Bot API.
     *
     * @param string $fileId The message ID to check.
     * @param string|null $chatId The chat ID where the message is located.
     * @return bool
     * @throws Exception
     */
    /**
     * Forward a message from the main storage channel to another chat using the Bot API.
     *
     * @param string $fileId The message ID to forward.
     * @param string $targetChatId The chat to forward the message to.
     * @return bool
     * @throws Exception
     */
    public function forwardFile($fileId, $targetChatId): bool
    {
        $token = $this->getBotToken();
        $sourceChatId = app(Settings::class)->get('storage_telegram_chat_id');

        if (!$sourceChatId) {
            throw new Exception('Main storage chat ID is not configured.');
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/forwardMessage", [
                'chat_id' => $targetChatId,
                'from_chat_id' => $sourceChatId,
                'message_id' => $fileId,
            ]);

            if ($response->successful() && $response->json('ok')) {
                Log::info("File forwarded from {$sourceChatId} to {$targetChatId}: message_id {$fileId}");
                return true;
            } else {
                $error = $response->json('description') ?: 'Failed to forward file via bot.';
                Log::error("Telegram forward error for message {$fileId}: {$error}");
                return false;
            }
        } catch (Exception $e) {
            Log::error("Error forwarding file via bot: " . $e->getMessage());
            throw $e;
        }
    }

    public function fileExists($fileId, $chatId = null): bool
    {
        $token = $this->getBotToken();
        $chatId = $chatId ?: app(Settings::class)->get('storage_telegram_chat_id', 'me');

        try {
            // We can check for existence by trying to forward the message to the same chat.
            // If it succeeds, the message exists. If it fails, it likely doesn't.
            $response = Http::post("https://api.telegram.org/bot{$token}/forwardMessage", [
                'chat_id' => $chatId,
                'from_chat_id' => $chatId,
                'message_id' => $fileId,
            ]);

            // If the forward was successful, we should delete the forwarded message to avoid spam.
            if ($response->successful() && $response->json('ok')) {
                $forwardedMessageId = $response->json('result.message_id');
                $this->deleteFile($forwardedMessageId, $chatId);
                return true;
            }

            // Check for specific error message "message to forward not found"
            if (str_contains($response->body(), 'message to forward not found')) {
                return false;
            }

            // For other errors, we can assume it exists but something else went wrong.
            // Or we can return false. Let's be conservative and say it doesn't exist.
            return false;

        } catch (Exception $e) {
            Log::error("Error checking file existence in Telegram: " . $e->getMessage());
            return false; // In case of exception, assume it doesn't exist.
        }
    }

    /**
     * Get the configured bot token.
     *
     * @return string
     * @throws Exception
     */
    protected function getBotToken(): string
    {
        if (empty($this->botToken)) {
            throw new Exception('Telegram bot token is not configured.');
        }
        return $this->botToken;
    }

    /**
     * Get file URL (for Telegram, this would be a file_id or message link)
     */
    public function getFileUrl($fileId)
    {
        // Return a telegram file identifier
        return 'telegram://' . $fileId;
    }

    /**
     * Parse a more user-friendly error message from the command output.
     */
    protected function parseErrorMessage($error)
    {
        $lines = explode("\n", $error);
        foreach ($lines as $line) {
            if (str_contains($line, 'telethon.errors')) {
                return $line;
            }
        }
        return 'An unknown error occurred.';
    }

    /**
     * Get configuration status
     */
    public function runHealthCheck(): string
    {
        // 1. Check if telegram-upload is installed
        $result = Process::run('which telegram-upload');
        if (!$result->successful()) {
            return 'The "telegram-upload" package is not installed on the server or is not in the system\'s PATH.';
        }

        // 2. Try to upload a test file
        $testFile = tempnam(sys_get_temp_dir(), 'telegram_test_');
        file_put_contents($testFile, 'Health check from BeDrive at ' . now());
        $chatId = app(Settings::class)->get('storage_telegram_chat_id', 'me');

        try {
            $uploadResult = $this->uploadFile($testFile, 'health_check.txt', $chatId);
            unlink($testFile);

            if ($uploadResult['success']) {
                // 3. Try to delete the test file
                $this->deleteFile($uploadResult['file_id'], $chatId);
                return 'Connection successful. A test file was uploaded and deleted.';
            } else {
                 return 'Could not upload a test file. Check your config file path and permissions.';
            }
        } catch (Exception $e) {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            return "An error occurred during the test upload: " . $e->getMessage();
        }
    }
}

