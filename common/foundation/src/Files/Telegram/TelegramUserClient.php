<?php

namespace Common\Files\Telegram;

use Common\Files\Telegram\Contracts\TelegramClientInterface;
use Common\Files\Telegram\Exceptions\TelegramAuthException;
use Common\Files\Telegram\Exceptions\TelegramConfigException;
use Common\Files\Telegram\Exceptions\TelegramDownloadException;
use Common\Files\Telegram\Exceptions\TelegramUploadException;
use Common\Files\Telegram\TelegramSessionManager;
use danog\MadelineProto\API;
use danog\MadelineProto\Exception as MadelineException;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\RemoteUrl;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
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
    protected string $phone;
    protected string $sessionFile;
    protected bool $authenticated = false;

    /**
     * Maximum file size for User Account (2GB)
     */
    public const MAX_FILE_SIZE = 2 * 1024 * 1024 * 1024; // 2GB in bytes

        public function __construct(
        ?int $apiId = null,
        ?string $apiHash = null,
        ?string $phone = null,
        ?string $sessionFile = null
    ) {
        $this->apiId = $apiId ?? (int) config('services.telegram.api_id');
        $this->apiHash = $apiHash ?? config('services.telegram.api_hash');
        $this->phone = $phone ?? config('services.telegram.phone');
        $this->sessionFile =
            $sessionFile ?? config('services.telegram.session_file');

        Log::info('TelegramUserClient constructor called', [
            'api_id' => $this->apiId,
            'phone' => $this->phone,
            'session_file' => $this->sessionFile,
        ]);

        // Validate configuration
        if (empty($this->apiId) || empty($this->apiHash)) {
            throw TelegramConfigException::missingConfig('api_id or api_hash');
        }

        if (empty($this->sessionFile)) {
            $this->sessionFile = storage_path('app/telegram/session.madeline');
        }

        try {
            Log::info('Initializing MadelineProto in constructor');
            $this->initializeMadelineProto();
            Log::info('MadelineProto initialized successfully in constructor');
        } catch (Exception $e) {
            Log::error('MadelineProto initialization failed in constructor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw TelegramAuthException::invalidCredentials($e->getMessage());
        }
    }

    /**
     * Initialize MadelineProto
     * Initialize MadelineProto using shared session manager
     * This ensures session persistence across HTTP requests
     */
    protected function initializeMadelineProto(): void
    {
        try {
            // // MadelineProto v8+ requires Settings object
            // $settings = new Settings;
            
            // // Set app info
            // $appInfo = new AppInfo;
            // $appInfo->setApiId($this->apiId);
            // $appInfo->setApiHash($this->apiHash);
            // $settings->setAppInfo($appInfo);
            
            // // Set logger
            // $logger = new LoggerSettings;
            // $logger->setType(\danog\MadelineProto\Logger::FILE_LOGGER);
            // $logger->setExtra(storage_path('logs/madelineproto.log'));
            // $logger->setLevel(\danog\MadelineProto\Logger::WARNING);
            // $settings->setLogger($logger);
            // // CRITICAL: Enable IPC mode for session persistence across HTTP requests
            // // This creates a long-running background process that maintains state
            // $settings->getIpc()->setSlow(false);
            
            // // Initialize with IPC server
            // // This will start a background server if not running, or connect to existing one
            // $this->MadelineProto = new API($this->sessionFile, $settings);

            // // Start the IPC server (non-blocking)
            // // This ensures session state persists between phoneLogin and completePhoneLogin
            // $this->MadelineProto->startAndLoop();
            // Use shared session manager to get/create MadelineProto instance
            // This ensures the SAME instance is used for phoneLogin and completePhoneLogin
            $sessionManager = TelegramSessionManager::getInstance();
            
            $this->MadelineProto = $sessionManager->getSession(
                $this->apiId,
                $this->apiHash,
                $this->phone,
                $this->sessionFile
            );
            // Check if already authorized
            try {
                $authorization = $this->MadelineProto->getAuthorization();
                $this->authenticated = ($authorization === API::LOGGED_IN);
                Log::info('Authorization state', [
                    'phone' => $this->phone,
                    'state' => $authorization,
                    'is_logged_in' => $this->authenticated,
                ]);
            } catch (Exception $e) {
                $this->authenticated = false;
                Log::warning('Could not get authorization state', [
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('MadelineProto initialized via session manager', [
                'session_file' => $this->sessionFile,
                'is_authenticated' => $this->authenticated,
            ]);
        } catch (Exception $e) {
            $this->authenticated = false;
            Log::error('MadelineProto initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            $result = $this->MadelineProto->messages->forwardMessages([
                'from_peer' => $fromChatId,
                'id' => [$messageId],
                'to_peer' => $toChatId,
            ]);

            Log::info('Message forwarded successfully (User Account)', [
                'from_chat' => $fromChatId,
                'to_chat' => $toChatId,
                'message_id' => $messageId,
            ]);

            return [
                'success' => true,
                'result' => $result,
            ];
        } catch (MadelineException $e) {
            Log::error('Failed to forward message (User Account)', [
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
     * Get authorization state
     *
     * @return array
     */
    public function getAuthorizationState(): array
    {
        try {
            if (!$this->MadelineProto) {
                return [
                    'is_authorized' => false,
                    'needs_login' => true,
                ];
            }

            $authorization = $this->MadelineProto->getAuthorization();
            
            return [
                'is_authorized' => $authorization === API::LOGGED_IN,
                'needs_login' => $authorization !== API::LOGGED_IN,
                'authorization_state' => $authorization,
            ];
        } catch (Exception $e) {
            Log::error('Failed to get authorization state', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'is_authorized' => false,
                'needs_login' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Start login process
     *
     * @return array
     * @throws TelegramAuthException
     */
    public function startLogin(): array
    {
        try {
            if (empty($this->phone)) {
                throw TelegramAuthException::invalidCredentials('Phone number is required');
            }

            // Check current authorization state
            $currentAuth = $this->MadelineProto->getAuthorization();
            
            // If already waiting for code, don't restart
            if ($currentAuth === API::WAITING_CODE) {
                Log::info('Already waiting for verification code', [
                    'phone' => $this->phone,
                ]);
                
                return [
                    'needs_code' => true,
                    'phone' => $this->phone,
                    'phone_code_hash' => null, // State is preserved in session
                ];
            }
            
            // If already logged in, no need to login again
            if ($currentAuth === API::LOGGED_IN) {
                return [
                    'already_logged_in' => true,
                    'phone' => $this->phone,
                ];
            }

            // Start phone login
            Log::info('Starting phone login', [
                'phone' => $this->phone,
                'session_file' => $this->sessionFile,
            ]);
            
            $result = $this->MadelineProto->phoneLogin($this->phone);

            Log::info('Phone login initiated successfully', [
                'phone' => $this->phone,
                'has_phone_code_hash' => isset($result['phone_code_hash']),
            ]);
            return [
                'needs_code' => true,
                'phone' => $this->phone,
                'phone_code_hash' => $result['phone_code_hash'] ?? null,
            ];
        } catch (MadelineException $e) {
            Log::error('Failed to start login', [
                'phone' => $this->phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw TelegramAuthException::invalidCredentials($e->getMessage());
        }
    }
    // /**
    //  * Verify code and complete login
    //  *
    //  * @param string $code
    //  * @return array
    //  * @throws TelegramAuthException
    //  */
    // public function verifyCode(string $code): array
    // {
    //     try {
    //         // Check current authorization state
    //         $currentAuth = $this->MadelineProto->getAuthorization();
            
    //         Log::info('Verifying code', [
    //             'phone' => $this->phone,
    //             'code_length' => strlen($code),
    //             'current_auth_state' => $currentAuth,
    //             'session_file' => $this->sessionFile,
    //         ]);
            
    //         // If not waiting for code, something went wrong
    //         if ($currentAuth !== API::WAITING_CODE && $currentAuth !== API::WAITING_PASSWORD) {
    //             Log::error('Not in correct state for verification', [
    //                 'current_state' => $currentAuth,
    //                 'expected_state' => 'WAITING_CODE or WAITING_PASSWORD',
    //             ]);
                
    //             throw new MadelineException("Invalid state for code verification. Current state: {$currentAuth}");
    //         }

    //         // Complete phone login with code
    //         $result = $this->MadelineProto->completePhoneLogin($code);

    //         Log::info('Code verification result', [
    //             'result_type' => $result['_'] ?? 'unknown',
    //             'has_user' => isset($result['user']),
    //         ]);

    //         // Check if 2FA is required
    //         if (isset($result['_']) && $result['_'] === 'account.password') {
    //             Log::info('2FA required for login');
                
    //             return [
    //                 'needs_password' => true,
    //                 'is_authorized' => false,
    //                 'hint' => $result['hint'] ?? '',
    //             ];
    //         }

    //         // Login successful
    //         $this->authenticated = true;

    //         Log::info('Login successful', [
    //             'phone' => $this->phone,
    //         ]);

    //         return [
    //             'is_authorized' => true,
    //             'session_file' => $this->sessionFile,
    //         ];
    //     } catch (MadelineException $e) {
    //         Log::error('Failed to verify code', [
    //             'phone' => $this->phone,
    //             'error' => $e->getMessage(),
    //             'code' => $e->getCode(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);
            
    //         throw TelegramAuthException::invalidCredentials('Login verification failed: ' . $e->getMessage());
    //     }
    // }

    /**
     * Verify code and complete login
     *
     * @param string $code
     * @param string|null $phoneCodeHash
     * @return array
     * @throws TelegramAuthException
     */
    public function verifyCode(string $code, ?string $phoneCodeHash = null): array
    {
        try {
            // Log current state before verification
            $currentAuth = $this->MadelineProto->getAuthorization();
            
            Log::info('Attempting code verification', [
                'phone' => $this->phone,
                'code_length' => strlen($code),
                'current_auth_state' => $currentAuth,
            ]);

            // If not in WAITING_CODE state, we need to restart login
            if ($currentAuth !== API::WAITING_CODE && $currentAuth !== API::WAITING_PASSWORD) {
                Log::warning('Not in WAITING_CODE state, attempting to restart login', [
                    'current_state' => $currentAuth,
                ]);
                
                // Try to restart login process
                try {
                    $this->MadelineProto->phoneLogin($this->phone);
                    Log::info('Login restarted, please resend code');
                    
                    throw TelegramAuthException::invalidCredentials(
                        'Session expired. Please request a new verification code.'
                    );
                } catch (Exception $restartEx) {
                    Log::error('Failed to restart login', [
                        'error' => $restartEx->getMessage(),
                    ]);
                }
            }

            // Complete phone login with code
            $result = $this->MadelineProto->completePhoneLogin($code);

            Log::info('Code verification completed', [
                'result' => $result,
            ]);

            // Check if logged in successfully
            if ($result === API::LOGGED_IN) {
                $this->authenticated = true;
                
                Log::info('Login successful', [
                    'phone' => $this->phone,
                ]);
                
                return [
                    'success' => true,
                    'is_authorized' => true,
                    'session_file' => $this->sessionFile,
                ];
            }

            // Check if 2FA password is required
            if ($result === API::WAITING_PASSWORD) {
                Log::info('2FA password required');
                
                return [
                    'success' => false,
                    'needs_password' => true,
                    'is_authorized' => false,
                    'message' => 'Two-factor authentication enabled. Please enter your cloud password.',
                ];
            }

            // Unknown result
            Log::warning('Unexpected verification result', [
                'result' => $result,
            ]);
            
            throw TelegramAuthException::invalidCredentials('Login verification failed');
        } catch (MadelineException $e) {
            Log::error('Failed to verify code', [
                'phone' => $this->phone,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            
            // Check if error message indicates 2FA is required
            if (str_contains(strtolower($e->getMessage()), 'password') || 
                str_contains(strtolower($e->getMessage()), '2fa')) {
                return [
                    'success' => false,
                    'needs_password' => true,
                    'is_authorized' => false,
                    'message' => 'Two-factor authentication enabled. Please enter your cloud password.',
                ];
            }

            // Check for AUTH_RESTART error
            if (str_contains($e->getMessage(), 'AUTH_RESTART')) {
                throw TelegramAuthException::invalidCredentials(
                    'Session expired. Please request a new verification code and try again.'
                );
            }
            
            throw TelegramAuthException::invalidCredentials(
                'Verification failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Complete login with 2FA password
     *
     * @param string $password
     * @return array
     * @throws TelegramAuthException
     */
    public function complete2FA(string $password): array
    {
        try {
            $result = $this->MadelineProto->complete2faLogin($password);

            if ($result === API::LOGGED_IN) {
                $this->authenticated = true;
                
                Log::info('Telegram user logged in with 2FA', [
                    'phone' => $this->phone,
                ]);
                
                return [
                    'success' => true,
                    'is_authorized' => true,
                    'session_file' => $this->sessionFile,
                ];
            }

            throw TelegramAuthException::invalidCredentials('2FA password verification failed');
        } catch (MadelineException $e) {
            Log::error('Failed to complete 2FA login', [
                'error' => $e->getMessage(),
            ]);
            
            throw TelegramAuthException::invalidCredentials('Invalid 2FA password: ' . $e->getMessage());
        }
    }
}
