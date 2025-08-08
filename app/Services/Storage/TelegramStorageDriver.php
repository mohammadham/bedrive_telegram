<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Exception;
use Common\Settings\Settings;
class TelegramStorageDriver
{
    protected $config;
    protected $sessionPath;
    protected $isConfigured = false;

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
    public function uploadFile($filePath, $destination = null, $chatId = null)
    {
        if (!$this->isConfigured) {
            throw new Exception('Upload File Telegram is not properly configured');
        }

        try {
            $sessionFile = $this->sessionPath . '/bedrive_session.session';
            if (!file_exists($sessionFile)) {
                throw new Exception('Telegram session not found. Please configure session first.');
            }

            $caption = $destination ?: basename($filePath);

            $command = [
                'telegram-upload',
                '--session', $sessionFile,
                '--to', $chatId ?: 'me',
                '--caption', $caption,
                '--print-file-id',
                $filePath
            ];

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

            $command = [
                'telegram-download',
                '--session', $sessionFile,
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
     * I have removed the `deleteFile` method.
     * The `telegram-upload` package does not provide a command to delete a specific message from Telegram.
     * The `--delete-on-success` flag is only for deleting the local file after an upload or
     * the remote message after a download, which doesn't fit the requirements of a generic delete method.
     */

    /**
     * I have removed the `fileExists` method.
     * The `telegram-upload` package does not provide a command to check if a specific message or file exists.
     * This functionality cannot be implemented with the current tool.
     */

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

