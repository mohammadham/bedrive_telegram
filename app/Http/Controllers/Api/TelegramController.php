<?php

namespace App\Http\Controllers\Api;

use Common\Core\BaseController;
use App\Services\Storage\TelegramStorageDriver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Common\Settings\Settings;
use Illuminate\Support\Facades\Http;

class TelegramController extends BaseController
{
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get Telegram configuration status
     */
    public function status(): JsonResponse
    {
        try {
            $config = [
                'api_id' => $this->settings->get('storage_telegram_api_id'),
                'api_hash' => $this->settings->get('storage_telegram_api_hash'),
                'phone' => $this->settings->get('storage_telegram_phone'),
            ];

            $driver = new TelegramStorageDriver($config);
            $status = $driver->getStatus();

            return response()->json([
                'success' => true,
                'status' => $status,
                'configured' => !empty($config['api_id']) && !empty($config['api_hash']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting Telegram status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Telegram status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Install telegram-upload package
     */
    public function install(): JsonResponse
    {
        try {
            $config = [
                'api_id' => $this->settings->get('storage_telegram_api_id'),
                'api_hash' => $this->settings->get('storage_telegram_api_hash'),
                'phone' => $this->settings->get('storage_telegram_phone'),
            ];

            $driver = new TelegramStorageDriver($config);
            $result = $driver->installTelegramUpload();

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'telegram-upload installed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to install telegram-upload'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error installing telegram-upload: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to install telegram-upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configure Telegram session
     */
    public function configureSession(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $config = [
                'api_id' => $this->settings->get('storage_telegram_api_id'),
                'api_hash' => $this->settings->get('storage_telegram_api_hash'),
                'phone' => $this->settings->get('storage_telegram_phone'),
            ];

            if (empty($config['api_id']) || empty($config['api_hash'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telegram API credentials not configured'
                ], 400);
            }

            $driver = new TelegramStorageDriver($config);
            $phone = $request->input('phone') ?: $config['phone'];

            $result = $driver->configureSession($phone);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Telegram session configured successfully. Please check your phone for a confirmation code.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to configure Telegram session'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error configuring Telegram session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure Telegram session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Telegram upload
     */
    public function testUpload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'chat_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $config = [
                'api_id' => $this->settings->get('storage_telegram_api_id'),
                'api_hash' => $this->settings->get('storage_telegram_api_hash'),
                'phone' => $this->settings->get('storage_telegram_phone'),
            ];

            $driver = new TelegramStorageDriver($config);
            $chatId = $request->input('chat_id') ?: $this->settings->get('storage_telegram_chat_id');

            // Create a test file
            $testFile = storage_path('app/telegram_test_' . time() . '.txt');
            file_put_contents($testFile, 'Test file uploaded from BeDrive at ' . now());

            $result = $driver->uploadFile($testFile, 'test_upload.txt', $chatId);

            // Clean up test file
            if (file_exists($testFile)) {
                unlink($testFile);
            }

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test upload successful',
                    'file_id' => $result['file_id']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Test upload failed'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error testing Telegram upload: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Test upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function configureBot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $token = $request->input('token');
        $webhookUrl = url('/api/webhooks/telegram');

        try {
            $response = Http::get("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl,
            ]);

            if ($response->successful() && $response->json('ok')) {
                $this->settings->save([
                    'telegram_bot_token' => $token,
                    'telegram_webhook_set' => true,
                ]);

                return response()->json(['success' => true, 'message' => 'Webhook set successfully']);
            } else {
                $this->settings->save(['telegram_webhook_set' => false]);
                $description = $response->json('description') ?: 'Could not communicate with Telegram API.';
                return response()->json(['success' => false, 'message' => $description], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error setting Telegram webhook: ' . $e->getMessage());
            $this->settings->save(['telegram_webhook_set' => false]);
            return response()->json(['success' => false, 'message' => 'Failed to set webhook', 'error' => $e->getMessage()], 500);
        }
    }
}

