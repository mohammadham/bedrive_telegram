<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Exception;

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
        
        $this->checkConfiguration();
    }

    /**
     * Check if telegram-upload is properly configured
     */
    protected function checkConfiguration()
    {
        try {
            // Check if telegram-upload is installed
            $result = Process::run('which telegram-upload');
            if ($result->failed()) {
                Log::warning('telegram-upload is not installed');
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
            
            // Set environment variables for telegram-upload
            $env = [
                'TELEGRAM_API_ID' => $this->config['api_id'],
                'TELEGRAM_API_HASH' => $this->config['api_hash'],
                'TELEGRAM_SESSION' => $sessionFile,
            ];

            if ($phoneNumber) {
                $env['TELEGRAM_PHONE'] = $phoneNumber;
            }

            // Create a simple test upload to verify session
            $testFile = storage_path('app/telegram_test.txt');
            file_put_contents($testFile, 'Test file for Telegram configuration');

            $command = 'telegram-upload --session ' . $sessionFile . ' ' . $testFile;
            
            $result = Process::env($env)->run($command);
            
            // Clean up test file
            if (file_exists($testFile)) {
                unlink($testFile);
            }

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
            throw new Exception('Telegram is not properly configured');
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

            $command = 'telegram-upload --session ' . $sessionFile;
            
            if ($chatId) {
                $command .= ' --to ' . escapeshellarg($chatId);
            }
            
            $command .= ' ' . escapeshellarg($filePath);

            $result = Process::env($env)->run($command);

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
                Log::error('Failed to upload file to Telegram: ' . $result->errorOutput());
                throw new Exception('Upload failed: ' . $result->errorOutput());
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
            throw new Exception('Telegram is not properly configured');
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
        // Note: telegram-upload doesn't support direct file deletion
        // This would require custom implementation using Telethon directly
        Log::warning('File deletion from Telegram is not supported by telegram-upload package');
        return false;
    }

    /**
     * Check if file exists in Telegram
     */
    public function fileExists($fileId)
    {
        // This would require custom implementation
        // For now, we'll assume the file exists if we have a file_id
        return !empty($fileId);
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

