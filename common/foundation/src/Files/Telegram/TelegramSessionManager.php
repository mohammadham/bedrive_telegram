<?php

namespace Common\Files\Telegram;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Singleton manager for MadelineProto instances
 * Ensures session persistence across multiple HTTP requests
 */
class TelegramSessionManager
{
    protected static ?self $instance = null;
    protected array $sessions = [];

    private function __construct()
    {
        // Private constructor for singleton
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get or create MadelineProto instance with session persistence
     *
     * @param int $apiId
     * @param string $apiHash
     * @param string $phone
     * @param string $sessionFile
     * @return API
     */
    public function getSession(
        int $apiId,
        string $apiHash,
        string $phone,
        string $sessionFile
    ): API {
        // Create a unique key for this session
        $sessionKey = md5($apiId . $apiHash . $phone . $sessionFile);

        // Return existing session if available
        if (isset($this->sessions[$sessionKey])) {
            Log::info('Reusing existing MadelineProto session', [
                'session_key' => $sessionKey,
                'phone' => $phone,
            ]);
            return $this->sessions[$sessionKey];
        }

        // Create new session
        Log::info('Creating new MadelineProto session', [
            'session_key' => $sessionKey,
            'phone' => $phone,
            'session_file' => $sessionFile,
        ]);

        try {
            // Ensure session directory exists
            $sessionDir = dirname($sessionFile);
            if (!is_dir($sessionDir)) {
                mkdir($sessionDir, 0755, true);
            }

            // Configure MadelineProto settings
            $settings = new Settings;
            
            // App info
            $appInfo = new AppInfo;
            $appInfo->setApiId($apiId);
            $appInfo->setApiHash($apiHash);
            $settings->setAppInfo($appInfo);
            
            // Logger
            $logger = new LoggerSettings;
            $logger->setType(\danog\MadelineProto\Logger::FILE_LOGGER);
            $logger->setExtra(storage_path('logs/madelineproto.log'));
            $logger->setLevel(\danog\MadelineProto\Logger::NOTICE);
            $settings->setLogger($logger);

            // CRITICAL: Enable IPC for session persistence
            $settings->getIpc()->setSlow(false);

            // Create API instance with IPC server
            $api = new API($sessionFile, $settings);

            // Start the IPC server in background (non-blocking)
            // This keeps the session alive between HTTP requests
            $api->start();

            // Store in cache
            $this->sessions[$sessionKey] = $api;

            Log::info('MadelineProto session created successfully', [
                'session_key' => $sessionKey,
            ]);

            return $api;
        } catch (Exception $e) {
            Log::error('Failed to create MadelineProto session', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Clear a specific session
     *
     * @param string $sessionFile
     * @return void
     */
    public function clearSession(string $sessionFile): void
    {
        foreach ($this->sessions as $key => $session) {
            // Remove from cache if it matches
            if (str_contains($key, md5($sessionFile))) {
                unset($this->sessions[$key]);
                
                Log::info('Session cleared from cache', [
                    'session_file' => $sessionFile,
                ]);
            }
        }

        // Also delete the session file
        if (file_exists($sessionFile)) {
            // Remove the session directory
            $sessionDir = $sessionFile;
            if (is_dir($sessionDir)) {
                $this->deleteDirectory($sessionDir);
            } elseif (file_exists($sessionFile)) {
                unlink($sessionFile);
            }
        }
    }

    /**
     * Clear all sessions
     *
     * @return void
     */
    public function clearAllSessions(): void
    {
        $this->sessions = [];
        Log::info('All sessions cleared from cache');
    }

    /**
     * Recursively delete a directory
     *
     * @param string $dir
     * @return void
     */
    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
