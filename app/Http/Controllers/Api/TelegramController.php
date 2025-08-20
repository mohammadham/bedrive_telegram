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

    public function test(): JsonResponse
    {
        try {
            $driver = new TelegramStorageDriver();
            $result = $driver->runHealthCheck();
            return response()->json(['result' => $result]);
        } catch (\Exception $e) {
            Log::error('Error testing Telegram connection: ' . $e->getMessage());
            return response()->json(['result' => ['package_check' => ['success' => false, 'message' => 'A critical error occurred: ' . $e->getMessage()]]], 500);
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
                    'telegram.telegram_bot_token' => $token,
                    'telegram.telegram_webhook_set' => true,
                ]);

                return response()->json(['success' => true, 'message' => 'Webhook set successfully']);
            } else {
                $this->settings->save(['telegram.telegram_webhook_set' => false]);
                $description = $response->json('description') ?: 'Could not communicate with Telegram API.';
                return response()->json(['success' => false, 'message' => $description], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error setting Telegram webhook: ' . $e->getMessage());
            $this->settings->save(['telegram.telegram_webhook_set' => false]);
            return response()->json(['success' => false, 'message' => 'Failed to set webhook', 'error' => $e->getMessage()], 500);
        }
    }
}

