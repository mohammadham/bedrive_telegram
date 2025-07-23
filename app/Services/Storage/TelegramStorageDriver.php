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

            $command = [
                'telegram-upload',
                '--session',
                $sessionFile,
                '--to',
                escapeshellarg($chatId ?: 'me'),
                escapeshellarg($filePath)
            ];

            $result = Process::run($command);

            if ($result->successful()) {
                Log::info('File uploaded to Telegram successfully: ' . $filePath);

                // Parse output to get file info
                $output = $result->output();
                $fileId = $this->parseFileIdFromOutput($output);

                return [
                    'success' => true,
                    'file_id' => $fileId,
                    'path' => $destination ?: basename($filePath),
                    'output' => $output
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
     * Download file from Telegram
     */
    public function downloadFile($fileId, $destination)
    {
        if (!$this->isConfigured) {
            throw new Exception('Download File Telegram is not properly configured');
        }

        try {
            $sessionFile = $this->sessionPath . '/bedrive_session.session';

            if (!file_exists($sessionFile)) {
                throw new Exception('Telegram session not found. Please configure session first.');
            }

            $env = [
                'TELEGRAM_API_ID' => $this->config['api_id'],
                'TELEGRAM_API_HASH' => $this->config['api_hash'],
            ];

            $command = 'telegram-download --session ' . $sessionFile . ' --file-id ' . escapeshellarg($fileId) . ' --output ' . escapeshellarg($destination);

            $result = Process::env($env)->run($command);

            if ($result->successful()) {
                Log::info('File downloaded from Telegram successfully: ' . $fileId);
                return true;
            } else {
                Log::error('Failed to download file from Telegram: ' . $result->errorOutput());
                throw new Exception('Download failed: ' . $result->errorOutput());
            }
        } catch (Exception $e) {
            Log::error('Error downloading file from Telegram: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete file from Telegram
     */
    public function deleteFile($fileId)
    {
        if (!$this->isConfigured) {
            throw new Exception('Delete File Telegram is not properly configured');
        }

        try {
            $sessionFile = $this->sessionPath . '/bedrive_session.session';

            if (!file_exists($sessionFile)) {
                throw new Exception('Telegram session not found. Please configure session first.');
            }

            $env = [
                'TELEGRAM_API_ID' => $this->config['api_id'],
                'TELEGRAM_API_HASH' => $this->config['api_hash'],
            ];

            $command = 'telegram-delete --session ' . $sessionFile . ' ' . escapeshellarg($fileId);

            $result = Process::env($env)->run($command);

            if ($result->successful()) {
                Log::info('File deleted from Telegram successfully: ' . $fileId);
                return true;
            } else {
                Log::error('Failed to delete file from Telegram: ' . $result->errorOutput());
                throw new Exception('Delete failed: ' . $result->errorOutput());
            }
        } catch (Exception $e) {
            Log::error('Error deleting file from Telegram: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if file exists in Telegram
     */
    public function fileExists($fileId)
    {
        if (!$this->isConfigured) {
            throw new Exception('File Exist Telegram is not properly configured');
        }

        try {
            $sessionFile = $this->sessionPath . '/bedrive_session.session';

            if (!file_exists($sessionFile)) {
                throw new Exception('Telegram session not found. Please configure session first.');
            }

            $env = [
                'TELEGRAM_API_ID' => $this->config['api_id'],
                'TELEGRAM_API_HASH' => $this->config['api_hash'],
            ];

            $command = 'telegram-get --session ' . $sessionFile . ' ' . escapeshellarg($fileId);

            $result = Process::env($env)->run($command);

            return $result->successful();
        } catch (Exception $e) {
            Log::error('Error checking if file exists in Telegram: ' . $e->getMessage());
            return false;
        }
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
     * Parse file ID from telegram-upload output
     */
    protected function parseFileIdFromOutput($output)
    {
        // telegram-upload typically outputs file information
        // We'll need to parse this to extract a usable file identifier
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            if (strpos($line, 'Message ID:') !== false) {
                return trim(str_replace('Message ID:', '', $line));
            }
            if (strpos($line, 'File ID:') !== false) {
                return trim(str_replace('File ID:', '', $line));
            }
        }

        // Fallback: generate a unique identifier
        return 'tg_' . Str::random(16) . '_' . time();
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

