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
    protected $chatId;

    public function __construct(array $tempSettings = [])
    {
        if (!empty($tempSettings)) {
            $this->botToken = $tempSettings['bot_token'] ?? null;
            $this->configPath = $tempSettings['config_path'] ?? null;
            $this->chatId = $tempSettings['chat_id'] ?? null;
        }
        $this->loadSettingsFromDatabase();
    }

    protected function loadSettingsFromDatabase()
    {
        try {
            $settings = app(Settings::class);
            if (empty($this->botToken)) {
                $this->botToken = $settings->get('telegram.telegram_bot_token');
            }
            if (empty($this->configPath)) {
                $this->configPath = $settings->get('telegram.storage_telegram_config_path');
            }
            if (empty($this->chatId)) {
                $this->chatId = $settings->get('telegram.storage_telegram_chat_id', 'me');
            }
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


    /**
     * Upload a file to Telegram using telegram-upload.
     * Supports all documented options.
     *
     * @param string $filePath
     * @param array $options
     *   Supported keys:
     *     - to: destination chat/user (default: 'me')
     *     - caption: file caption
     *     - delete_on_success: bool
     *     - print_file_id: bool
     *     - force_file: bool
     *     - forward: array|string (can be multiple)
     *     - directories: 'fail'|'recursive'
     *     - large_files: 'fail'|'split'
     *     - no_thumbnail: bool
     *     - thumbnail_file: string
     *     - proxy: string
     *     - album: bool
     *     - interactive: bool
     *     - sort: bool
     * @return array
     * @throws Exception
     */
    public function uploadFile($filePath, $options = [])
    {
        $args = [];
        // --to
        $args[] = '--to';
        $args[] = $options['to'] ?? 'me';
        // --caption
        if (isset($options['caption'])) {
            $args[] = '--caption';
            $args[] = $options['caption'];
        } else {
            $args[] = '--caption';
            $args[] = basename($filePath);
        }
        // --delete-on-success
        if (!empty($options['delete_on_success'])) {
            $args[] = '--delete-on-success';
        }
        // --print-file-id
        if (!isset($options['print_file_id']) || $options['print_file_id']) {
            $args[] = '--print-file-id';
        }
        // --force-file
        if (!empty($options['force_file'])) {
            $args[] = '--force-file';
        }
        // --forward (can be array or string)
        if (!empty($options['forward'])) {
            $forwards = is_array($options['forward']) ? $options['forward'] : [$options['forward']];
            foreach ($forwards as $fwd) {
                $args[] = '--forward';
                $args[] = $fwd;
            }
        }
        // --directories
        if (!empty($options['directories'])) {
            $args[] = '--directories';
            $args[] = $options['directories'];
        }
        // --large-files
        if (!empty($options['large_files'])) {
            $args[] = '--large-files';
            $args[] = $options['large_files'];
        }
        // --no-thumbnail
        if (!empty($options['no_thumbnail'])) {
            $args[] = '--no-thumbnail';
        }
        // --thumbnail-file
        if (!empty($options['thumbnail_file'])) {
            $args[] = '--thumbnail-file';
            $args[] = $options['thumbnail_file'];
        }
        // --proxy
        if (!empty($options['proxy'])) {
            $args[] = '--proxy';
            $args[] = $options['proxy'];
        }
        // --album
        if (!empty($options['album'])) {
            $args[] = '--album';
        }
        // --interactive
        if (!empty($options['interactive'])) {
            $args[] = '--interactive';
        }
        // --sort
        if (!empty($options['sort'])) {
            $args[] = '--sort';
        }
        // فایل
        $args[] = $filePath;

        Log::info('Running command: ' . implode(' ', $this->buildCommand('telegram-upload', $args)));
        $command = $this->buildCommand('telegram-upload', $args);
        $result = Process::run($command);

        if ($result->successful()) {
            $fileId = trim($result->output());
            Log::info("File uploaded to Telegram: $filePath with ID: $fileId");
            return [
                'success' => true,
                'file_id' => $fileId,
                'path' => $options['caption'] ?? basename($filePath),
            ];
        } else {
            $error = $result->errorOutput();
            Log::error("Failed to upload file to Telegram: $error");
            $parsedError = $this->parseErrorMessage($error);
            throw new Exception("Upload failed: $parsedError");
        }
    }


    /**
     * Download files from Telegram using telegram-download.
     * Supports all documented options.
     *
     * @param string $destination Directory to save downloaded files
     * @param array $options
     *   Supported keys:
     *     - from: chat/user to download from (default: 'me')
     *     - delete_on_success: bool
     *     - proxy: string
     *     - split_files: 'keep'|'join'
     *     - interactive: bool
     * @return array List of downloaded files (absolute paths)
     * @throws Exception
     */
    public function downloadFile($destination, $options = [])
    {
        $tempDir = sys_get_temp_dir() . '/telegram_downloads_' . Str::random(8);
        if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new \RuntimeException("Could not create temp directory: $tempDir");
        }

        $args = [];
        // --from
        $args[] = '--from';
        $args[] = $options['from'] ?? 'me';
        // --delete-on-success
        if (!empty($options['delete_on_success'])) {
            $args[] = '--delete-on-success';
        }
        // --proxy
        if (!empty($options['proxy'])) {
            $args[] = '--proxy';
            $args[] = $options['proxy'];
        }
        // --split-files
        if (!empty($options['split_files'])) {
            $args[] = '--split-files';
            $args[] = $options['split_files'];
        }
        // --interactive
        if (!empty($options['interactive'])) {
            $args[] = '--interactive';
        }

        $command = $this->buildCommand('telegram-download', $args);
        Log::info('Running command: ' . implode(' ', $command));
        $result = Process::setWorkingDirectory($tempDir)->run($command);

        $downloadedFiles = [];
        if ($result->successful()) {
            $files = array_diff(scandir($tempDir), ['.', '..']);
            if (empty($files)) {
                throw new Exception('Downloaded file not found in temp directory.');
            }
            foreach ($files as $file) {
                $src = $tempDir . '/' . $file;
                $dst = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $file;
                rename($src, $dst);
                $downloadedFiles[] = $dst;
            }
            rmdir($tempDir);
            Log::info("Files downloaded from Telegram: " . implode(', ', $downloadedFiles));
            return $downloadedFiles;
        } else {
            // Clean up temp dir
            foreach (glob($tempDir . '/*') as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
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
        $chatId = $chatId ?: app(Settings::class)->get('telegram.storage_telegram_chat_id', 'me');

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
        $sourceChatId = app(Settings::class)->get('telegram.storage_telegram_chat_id');

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

    /**
     * Edit the caption of a message in Telegram using the Bot API.
     *
     * @param string $fileId The message ID to edit.
     * @param string $newCaption The new caption for the file.
     * @param string|null $chatId The chat ID where the message is located.
     * @return bool
     * @throws Exception
     */
    public function editMessageCaption($fileId, $newCaption, $chatId = null): bool
    {
        $token = $this->getBotToken();
        $chatId = $chatId ?: app(Settings::class)->get('telegram.storage_telegram_chat_id', 'me');

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/editMessageCaption", [
                'chat_id' => $chatId,
                'message_id' => $fileId,
                'caption' => $newCaption,
            ]);

            if ($response->successful() && $response->json('ok')) {
                Log::info("Caption edited for message {$fileId} in chat {$chatId}");
                return true;
            } else {
                $error = $response->json('description') ?: 'Failed to edit caption.';
                Log::error("Telegram edit caption error for message {$fileId}: {$error}");
                return false;
            }
        } catch (Exception $e) {
            Log::error("Error editing caption in Telegram: " . $e->getMessage());
            throw $e;
        }
    }

    public function fileExists($fileId, $chatId = null): bool
    {
        $token = $this->getBotToken();
        $chatId = $chatId ?: app(Settings::class)->get('telegram.storage_telegram_chat_id', 'me');

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
        if (empty(trim($error))) {
            return 'An unknown error occurred (empty error output).';
        }

        $lines = explode("\n", $error);
        foreach ($lines as $line) {
            if (str_contains($line, 'telethon.errors')) {
                return $line;
            }
        }
        // Return the full error if no specific telethon error is found
        return "An unknown error occurred. Full error: $error";
    }

    /**
     * Get configuration status
     */
    public function runHealthCheck(): array
    {
        $results = [];

        // 1. Check if telegram-upload is installed
        $packageCheck = Process::run('which telegram-upload');
        $results['package_check'] = [
            'success' => $packageCheck->successful(),
            'message' => $packageCheck->successful()
                ? 'The "telegram-upload" package is installed.'
                : 'The "telegram-upload" package is not installed on the server or is not in the system\'s PATH.',
        ];

        if (!$packageCheck->successful()) {
            return $results;
        }

        // 2. Try to upload a test file
        $testFile = tempnam(sys_get_temp_dir(), 'telegram_test_');
        file_put_contents($testFile, 'Health check from BeDrive at ' . now());
        $chatId = $this->chatId ?: 'me';
        Log::info('Using chat ID for health check: ' . $chatId);
        try {
            $uploadOptions = [
                'to' => $chatId,
                'caption' => 'health_check.txt',
            ];
            $uploadResult = $this->uploadFile($testFile, $uploadOptions);

            $results['upload_check'] = [
                'success' => true,
                'message' => 'Test file uploaded successfully.',
            ];

            // 3. Try to delete the test file
            try {
                $this->deleteFile($uploadResult['file_id'], $chatId);
                $results['delete_check'] = [
                    'success' => true,
                    'message' => 'Test file deleted successfully.',
                ];
            } catch (Exception $e) {
                $results['delete_check'] = [
                    'success' => false,
                    'message' => 'Failed to delete test file: ' . $e->getMessage(),
                ];
            }
        } catch (Exception $e) {
            $results['upload_check'] = [
                'success' => false,
                'message' => 'Failed to upload test file: ' . $e->getMessage(),
            ];
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }

        return $results;
    }
}

