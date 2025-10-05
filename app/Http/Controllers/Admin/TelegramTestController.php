<?php

namespace App\Http\Controllers\Admin;

use Common\Core\BaseController;
use Common\Files\Telegram\TelegramBotClient;
use Common\Files\Telegram\TelegramUserClient;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramTestController extends BaseController
{
    /**
     * Test Bot Token and Channel access
     */
    public function testBot(Request $request): JsonResponse
    {
        $request->validate([
            'bot_token' => 'required|string',
            'channel_id' => 'required|string',
        ]);

        try {
            $botClient = new TelegramBotClient($request->bot_token);
            
            // Test 1: Get bot info
            $botInfo = $botClient->getMe();
            
            // Test 2: Check webhook status
            $webhookInfo = $botClient->getWebhookInfo();
            $webhookStatus = [
                'configured' => !empty($webhookInfo['url']),
                'url' => $webhookInfo['url'] ?? null,
                'pending_updates' => $webhookInfo['pending_update_count'] ?? 0,
                'last_error' => $webhookInfo['last_error_message'] ?? null,
            ];

            // Test 3: Set webhook if not configured or requested
            $webhookUrl = config('app.url') . '/api/telegram/webhook';
            if (empty($webhookInfo['url']) || $request->boolean('force_webhook')) {
                try {
                    $botClient->setWebhook($webhookUrl, [
                        'allowed_updates' => ['message', 'channel_post'],
                    ]);
                    $webhookStatus['configured'] = true;
                    $webhookStatus['url'] = $webhookUrl;
                    $webhookStatus['just_set'] = true;
                } catch (Exception $e) {
                    $webhookStatus['error'] = 'Failed to set webhook: ' . $e->getMessage();
                }
            }
            
            // Test 4: Send a test message to channel (keep it as proof)
            $testMessage = "✅ **Bot Connection Test Successful!**\n\n";
            $testMessage .= "🤖 Bot: @{$botInfo['username']}\n";
            $testMessage .= "📱 Bot Name: {$botInfo['first_name']}\n";
            $testMessage .= "🆔 Channel: `{$request->channel_id}`\n";
            $testMessage .= "⏰ Time: " . now()->toDateTimeString() . "\n\n";
            $testMessage .= "✓ Bot has permission to post messages\n";
            $testMessage .= "✓ Webhook configured: " . ($webhookStatus['configured'] ? 'Yes' : 'No') . "\n";
            $testMessage .= "✓ System is ready for file uploads";
            
            $result = $botClient->sendMessage(
                $request->channel_id,
                $testMessage,
                ['parse_mode' => 'Markdown']
            );
            
            if (!$result || !isset($result['message_id'])) {
                throw new Exception('Could not send message to channel. Make sure bot is admin with post permissions.');
            }
            //    // Test 5: Delete the test message
            // try {
            //     $botClient->deleteFile($request->channel_id, $result['message_id']);
            // } catch (Exception $e) {
            //     // Deletion failed - bot might not have delete permissions
            //     return response()->json([
            //     'success' => false,
            //     'message' => 'Bot connection failed: ' . $e->getMessage(),
            //     'error' => $this->classifyBotError($e->getMessage()),
            // ], 422);
            // }
            $testMessageId = $result['message_id'];

            return response()->json([
                'success' => true,
                'message' => 'Bot connection successful! A test message has been posted to your channel.',
                'data' => [
                    'bot_username' => $botInfo['username'] ?? 'Unknown',
                    'bot_name' => $botInfo['first_name'] ?? 'Unknown',
                    'channel_id' => $request->channel_id,
                    'test_message_sent' => true,
                    'test_message_id' => $testMessageId,
                    'test_message_link' => $this->getChannelMessageLink($request->channel_id, $testMessageId),
                    'webhook' => $webhookStatus,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bot connection failed: ' . $e->getMessage(),
                'error' => $this->classifyBotError($e->getMessage()),
            ], 422);
        }
    }

    /**
     * Test User Account and create session
     */
    public function testUser(Request $request): JsonResponse
    {
        $request->validate([
            'api_id' => 'required|integer',
            'api_hash' => 'required|string',
            'phone' => 'required|string',
            'session_file' => 'nullable|file|mimes:json',
        ]);

        try {
            // Check if session file uploaded
            $sessionPath = null;
            if ($request->hasFile('session_file')) {
                $file = $request->file('session_file');
                $sessionPath = storage_path('app/telegram_sessions/uploaded_session.json');
                
                // Create directory if not exists
                if (!is_dir(dirname($sessionPath))) {
                    mkdir(dirname($sessionPath), 0755, true);
                }
                
                $file->move(dirname($sessionPath), 'uploaded_session.json');
            }

            $userClient = new TelegramUserClient(
                (int) $request->api_id,
                $request->api_hash,
                $request->phone,
                $sessionPath
            );

            // Test connection
            $status = $userClient->getAuthorizationState();
            
            if ($status['is_authorized']) {
                return response()->json([
                    'success' => true,
                    'message' => 'User account connected successfully! Ready for large file uploads.',
                    'data' => [
                        'phone' => $request->phone,
                        'is_authorized' => true,
                        'session_created' => true,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User account not authorized. Please complete login process.',
                    'data' => [
                        'phone' => $request->phone,
                        'is_authorized' => false,
                        'needs_login' => true,
                        'status' => $status,
                    ],
                ], 422);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User account connection failed: ' . $e->getMessage(),
                'error' => $this->classifyUserError($e->getMessage()),
            ], 422);
        }
    }

    /**
     * Login to User Account (create session)
     */
    public function loginUser(Request $request): JsonResponse
    {
        $request->validate([
            'api_id' => 'required|integer',
            'api_hash' => 'required|string',
            'phone' => 'required|string',
        ]);

        try {
            $userClient = new TelegramUserClient(
                (int) $request->api_id,
                $request->api_hash,
                $request->phone
            );

            // Start login process
            $result = $userClient->startLogin();

            if ($result['needs_code']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent to your Telegram account.',
                    'data' => [
                        'needs_code' => true,
                        'phone_code_hash' => $result['phone_code_hash'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login initiated. Please check your Telegram for verification code.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify code and complete login
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'api_id' => 'required|integer',
            'api_hash' => 'required|string',
            'phone' => 'required|string',
            'code' => 'required|string',
            'phone_code_hash' => 'nullable|string',
        ]);

        try {
            $userClient = new TelegramUserClient(
                (int) $request->api_id,
                $request->api_hash,
                $request->phone
            );

            $result = $userClient->verifyCode($request->code, $request->phone_code_hash);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Login successful! User account is now active.',
                    'data' => [
                        'is_authorized' => true,
                        'session_file' => $result['session_file'] ?? null,
                    ],
                ]);
            }

            // Check if 2FA password is required
            if ($result['needs_password'] ?? false) {
                return response()->json([
                    'success' => false,
                    'needs_password' => true,
                    'message' => $result['message'] ?? 'Two-factor authentication password required.',
                    'data' => [
                        'is_authorized' => false,
                    ],
                ], 200); // Return 200 but with needs_password flag
            }

            throw new Exception('Verification failed');
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete 2FA login with password
     */
    public function complete2FA(Request $request): JsonResponse
    {
        $request->validate([
            'api_id' => 'required|integer',
            'api_hash' => 'required|string',
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $userClient = new TelegramUserClient(
                (int) $request->api_id,
                $request->api_hash,
                $request->phone
            );

            $result = $userClient->complete2FA($request->password);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Login successful with 2FA! User account is now active.',
                    'data' => [
                        'is_authorized' => true,
                        'session_file' => $result['session_file'] ?? null,
                    ],
                ]);
            }

            throw new Exception('2FA verification failed');
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '2FA verification failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(Request $request): JsonResponse
    {
        $request->validate([
            'bot_token' => 'required|string',
        ]);

        try {
            $botClient = new TelegramBotClient($request->bot_token);
            $webhookInfo = $botClient->getWebhookInfo();

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $webhookInfo['url'] ?? '',
                    'has_custom_certificate' => $webhookInfo['has_custom_certificate'] ?? false,
                    'pending_update_count' => $webhookInfo['pending_update_count'] ?? 0,
                    'last_error_date' => $webhookInfo['last_error_date'] ?? null,
                    'last_error_message' => $webhookInfo['last_error_message'] ?? null,
                    'is_configured' => !empty($webhookInfo['url']),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get webhook info: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Set webhook
     */
    public function setWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'bot_token' => 'required|string',
            'webhook_url' => 'nullable|url',
        ]);

        try {
            $botClient = new TelegramBotClient($request->bot_token);
            
            // Use provided URL or default
            $webhookUrl = $request->webhook_url ?? config('app.url') . '/api/telegram/webhook';
            
            $botClient->setWebhook($webhookUrl, [
                'allowed_updates' => ['message', 'channel_post', 'edited_channel_post'],
                'drop_pending_updates' => $request->boolean('drop_pending_updates'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook set successfully!',
                'data' => [
                    'webhook_url' => $webhookUrl,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set webhook: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'bot_token' => 'required|string',
        ]);

        try {
            $botClient = new TelegramBotClient($request->bot_token);
            $botClient->deleteWebhook($request->boolean('drop_pending_updates'));

            return response()->json([
                'success' => true,
                'message' => 'Webhook deleted successfully!',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete webhook: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Download session file
     */
    public function downloadSession(Request $request): JsonResponse
    {
        $request->validate([
            'api_id' => 'required|integer',
            'phone' => 'required|string',
        ]);

        $sessionPath = storage_path("app/telegram_sessions/{$request->api_id}_{$request->phone}.session");

        if (!file_exists($sessionPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Session file not found. Please login first.',
            ], 404);
        }

        return response()->download($sessionPath, 'telegram_session.json');
    }

    /**
     * Get channel message link
     */
    protected function getChannelMessageLink(string $channelId, int $messageId): ?string
    {
        // Remove '-100' prefix if exists
        $cleanChannelId = str_replace('-100', '', $channelId);
        
        // Public channel format: https://t.me/channel_username/message_id
        // Private channel: We can't create a direct link without username
        
        return "Channel message ID: {$messageId}";
    }

    /**
     * Classify bot errors
     */
    protected function classifyBotError(string $message): array
    {
        if (str_contains(strtolower($message), 'token')) {
            return [
                'type' => 'invalid_token',
                'suggestion' => 'Check that your bot token is correct. Get it from @BotFather.',
            ];
        }

        if (str_contains(strtolower($message), 'chat not found')) {
            return [
                'type' => 'invalid_channel',
                'suggestion' => 'Channel ID is incorrect or bot is not a member of the channel.',
            ];
        }

        if (str_contains(strtolower($message), 'not enough rights') || str_contains(strtolower($message), 'permission')) {
            return [
                'type' => 'insufficient_permissions',
                'suggestion' => 'Make sure bot is admin with "Post Messages" and "Delete Messages" permissions.',
            ];
        }

        return [
            'type' => 'unknown',
            'suggestion' => 'Please check your configuration and try again.',
        ];
    }

    /**
     * Classify user errors
     */
    protected function classifyUserError(string $message): array
    {
        if (str_contains(strtolower($message), 'api_id') || str_contains(strtolower($message), 'api_hash')) {
            return [
                'type' => 'invalid_credentials',
                'suggestion' => 'Check your API ID and API Hash from my.telegram.org',
            ];
        }

        if (str_contains(strtolower($message), 'phone')) {
            return [
                'type' => 'invalid_phone',
                'suggestion' => 'Phone number should include country code (e.g., +989123456789)',
            ];
        }

        if (str_contains(strtolower($message), 'session')) {
            return [
                'type' => 'session_error',
                'suggestion' => 'Try creating a new session or uploading a valid session file.',
            ];
        }

        return [
            'type' => 'unknown',
            'suggestion' => 'Please check your configuration and try again.',
        ];
    }
}
