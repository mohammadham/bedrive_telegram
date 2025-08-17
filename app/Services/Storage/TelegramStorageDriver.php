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
    protected $config;
    protected $sessionPath;
    protected $isConfigured = false;
    protected $botToken;

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->sessionPath = storage_path('app/telegram_sessions');

        // Create session directory if it doesn't exist
        if (!file_exists($this->sessionPath)) {
            mkdir($this->sessionPath, 0755, true);
        }
        // Load settings from the database
        $this->loadSettingsFromDatabase();
        $this->checkConfiguration();
    }
  /**
    * Load Telegram settings from the database
    */
   protected function loadSettingsFromDatabase()
   {
       try {
           $settings = app(Settings::class);
           $this->config['api_id'] = $settings->get('storage_telegram_api_id');
           $this->config['api_hash'] = $settings->get('storage_telegram_api_hash');
           $this->config['phone'] = $settings->get('storage_telegram_phone');
           $this->botToken = $settings->get('telegram_bot_token');
       } catch (Exception $e) {
           Log::error('Could not load Telegram settings from database: ' . $e->getMessage());
       }
   }
    /**
     * Check if telegram-upload is properly configured
     */
    protected function checkConfiguration()
    {
        try {
            // Check if telegram-upload is installed
            // Check if telegram-upload is installed and executable
            $telegramUploadPath = rtrim(shell_exec('which telegram-upload'));
            if (empty($telegramUploadPath) || !is_executable($telegramUploadPath)) {
                Log::warning('telegram-upload is not installed or not executable');
                return false;
            }

            // Check if we have required config
            if (empty($this->config['api_id']) || empty($this->config['api_hash'])) {
                Log::warning('Telegram API credentials not configured');
                return false;
            }

            $this->isConfigured = true;
            return true;
        } catch (Exception $e) {
            Log::error('Error checking telegram configuration: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if Telegram is properly configured.
     *
     * @return bool
     */
    public function isConfigured()
    {
        return $this->isConfigured;
    }

    /**
     * Install telegram-upload if not already installed
     */
    public function installTelegramUpload()
    {
        try {
            $result = Process::run('pip3 install telegram-upload');
            if ($result->successful()) {
                Log::info('telegram-upload installed successfully');
                return true;
            } else {
                Log::error('Failed to install telegram-upload: ' . $result->errorOutput());
                return false;
            }
        } catch (Exception $e) {
            Log::error('Error installing telegram-upload: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Configure telegram session
     */
    public function configureSession($phoneNumber = null)
    {
        if (!$this->isConfigured) {
            throw new Exception('Telegram configuration is incomplete');
        }

        try {
            $sessionFile = $this->sessionPath . '/bedrive_session.session';

          // Command to configure the session
          $command = [
            'telegram-upload',
            '--config-file',
            $sessionFile, // Using session file for config
            '--api-id',
            $this->config['api_id'],
            '--api-hash',
            $this->config['api_hash'],
        ];

        if ($phoneNumber) {
            $command[] = '--phone';
            $command[] = $phoneNumber;
        }

        // This command will initiate an interactive session if the phone number is not provided
        // or if 2FA is enabled. The user will need to enter the code in the terminal.
        $result = Process::run($command);

            if ($result->successful()) {
                Log::info('Telegram session configured successfully');
                return true;
            } else {
                Log::error('Failed to configure telegram session: ' . $result->errorOutput());
                return false;
            }
        } catch (Exception $e) {
            Log::error('Error configuring telegram session: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload file to Telegram
     *
     * @param string $filePath Absolute path to the file.
     * @param string|null $destination The path to store the file under in the registry.
     * @param string|null $chatId The chat to upload the file to.
     * @return array
     * @throws Exception
     *
     * I have modified this function to be compatible with the `telegram-upload` package.
     * - It now uses `--print-file-id` to reliably get the message ID.
     * - It adds a `--caption` with the file's destination path, which helps simulate a directory structure.
     * - The command is now built as an array to avoid shell argument injection issues.
     */
    public function uploadFile($filePath, $destination = null, $chatId = null, $forwardToChatId = null)
    {
        if (!$this->isConfigured) {
            throw new Exception('Upload File Telegram is not properly configured');
        }

        try {
            $configFile = $this->sessionPath . '/telegram-upload.json';
            // Assuming the session file itself can be used as the config for session details
            // This is based on the provided docs.
            // A more robust solution would be to manage a separate config file.
            if (!file_exists($this->sessionPath . '/bedrive_session.session')) {
                throw new Exception('Telegram session not found. Please configure session first.');
            }
            // Let's create a minimal config file if it doesn't exist
            if (!file_exists($configFile)) {
                file_put_contents($configFile, json_encode([
                    'api_id' => $this->config['api_id'],
                    'api_hash' => $this->config['api_hash'],
                    'session' => $this->sessionPath . '/bedrive_session.session'
                ]));
            }


            $caption = $destination ?: basename($filePath);

            $command = [
                'telegram-upload',
                '--config', $configFile,
                '--to', $chatId ?: 'me',
                '--caption', $caption,
                '--print-file-id',
            ];

            if ($forwardToChatId) {
                $command[] = '--forward';
                $command[] = $forwardToChatId;
            }

            $command[] = $filePath;

            $result = Process::run($command);

            if ($result->successful()) {
                $fileId = trim($result->output());
                Log::info('File uploaded to Telegram successfully: ' . $filePath . ' with ID: ' . $fileId);
                return [
                    'success' => true,
                    'file_id' => $fileId,
                    'path' => $destination ?: basename($filePath),
                    'output' => $result->output()
                ];
            } else {
                $error = $result->errorOutput();
                Log::error('Failed to upload file to Telegram: ' . $error);
                throw new Exception('Upload failed: ' . $this->parseErrorMessage($error));
            }
        } catch (Exception $e) {
            Log::error('Error uploading file to Telegram: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download file from Telegram.
     *
     * @param string $fileId The message ID of the file to download.
     * @param string $destination The absolute path to save the downloaded file.
     * @param string|null $chatId The chat to download from.
     * @return bool
     * @throws Exception
     *
     * I have rewritten this function to align with `telegram-download`'s capabilities.
     * - The `telegram-download` command does not support downloading a specific file by its ID.
     *   It downloads the latest files from a chat. This implementation assumes the desired file
     *   is the most recent one in the specified chat. This is a significant limitation.
     * - It now uses the `--from` parameter as specified in the documentation.
     * - The downloaded file is moved to the requested destination path.
     */
    public function downloadFile($fileId, $destination, $chatId = null)
    {
        if (!$this->isConfigured) {
            throw new Exception('Download File Telegram is not properly configured');
        }

        try {
            $sessionFile = $this->sessionPath . '/bedrive_session.session';
            if (!file_exists($sessionFile)) {
                throw new Exception('Telegram session not found. Please configure session first.');
            }

            // `telegram-download` downloads to the current directory.
            // We need to run it in a temporary directory to avoid filename conflicts
            // and then move the file to the desired destination.
            $tempDir = sys_get_temp_dir() . '/telegram_downloads_' . Str::random(8);
            if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
            }

            $configFile = $this->sessionPath . '/telegram-upload.json';
            if (!file_exists($configFile)) {
                 throw new Exception('Telegram config file not found. Please upload a file first to generate it.');
            }

            $command = [
                'telegram-download',
                '--config', $configFile,
                '--from', $chatId ?: 'me',
            ];

            $result = Process::setWorkingDirectory($tempDir)->run($command);

            if ($result->successful()) {
                // Find the downloaded file. We assume it's the first and only file.
                $files = scandir($tempDir);
                $downloadedFile = null;
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $downloadedFile = $tempDir . '/' . $file;
                        break;
                    }
                }

                if ($downloadedFile) {
                    rename($downloadedFile, $destination);
                    // Clean up the temp directory
                    rmdir($tempDir);
                    Log::info('File downloaded from Telegram successfully: ' . $fileId);
                    return true;
                } else {
                    throw new Exception('Downloaded file not found in temp directory.');
                }
            } else {
                // Clean up the temp directory
                rmdir($tempDir);
                $error = $result->errorOutput();
                Log::error('Failed to download file from Telegram: ' . $error);
                throw new Exception('Download failed: ' . $this->parseErrorMessage($error));
            }
        } catch (Exception $e) {
            Log::error('Error downloading file from Telegram: ' . $e->getMessage());
            throw $e;
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
    public function getStatus()
    {
        return [
            'configured' => $this->isConfigured,
            'session_exists' => file_exists($this->sessionPath . '/bedrive_session.session'),
            'telegram_upload_installed' => $this->isTelegramUploadInstalled(),
        ];
    }

    /**
     * Check if telegram-upload is installed
     */
    protected function isTelegramUploadInstalled()
    {
        try {
            $result = Process::run('which telegram-upload');
            return $result->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}

