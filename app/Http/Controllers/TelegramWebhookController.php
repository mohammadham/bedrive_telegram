<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Common\Core\BaseController;

class TelegramWebhookController extends BaseController
{
    /**
     * Handle incoming webhook from Telegram
     */
    public function handle(Request $request): JsonResponse
    {
        // Log incoming update
        Log::info('Telegram webhook received', [
            'payload' => $request->all(),
        ]);

        try {
            $update = $request->all();

            // Get update type
            $updateType = $this->getUpdateType($update);

            // Handle different update types
            switch ($updateType) {
                case 'message':
                    $this->handleMessage($update['message']);
                    break;
                case 'channel_post':
                    $this->handleChannelPost($update['channel_post']);
                    break;
                case 'edited_channel_post':
                    $this->handleEditedChannelPost($update['edited_channel_post']);
                    break;
                default:
                    Log::info('Unhandled update type', ['type' => $updateType]);
            }

            // Telegram expects 200 OK response
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Webhook handling error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still return 200 to Telegram to avoid retries
            return response()->json(['ok' => true]);
        }
    }

    /**
     * Get update type
     */
    protected function getUpdateType(array $update): ?string
    {
        if (isset($update['message'])) {
            return 'message';
        }
        if (isset($update['channel_post'])) {
            return 'channel_post';
        }
        if (isset($update['edited_channel_post'])) {
            return 'edited_channel_post';
        }
        return null;
    }

    /**
     * Handle regular message
     */
    protected function handleMessage(array $message): void
    {
        Log::info('Message received', [
            'message_id' => $message['message_id'] ?? null,
            'chat' => $message['chat']['id'] ?? null,
            'text' => $message['text'] ?? null,
        ]);

        // Here you can add custom logic for handling messages
        // For storage system, we don't need to process incoming messages
        // Webhook is mainly for keeping the bot active
    }

    /**
     * Handle channel post
     */
    protected function handleChannelPost(array $post): void
    {
        Log::info('Channel post received', [
            'message_id' => $post['message_id'] ?? null,
            'chat' => $post['chat']['id'] ?? null,
        ]);

        // Here you can add custom logic for handling channel posts
        // For example, tracking uploaded files, etc.
    }

    /**
     * Handle edited channel post
     */
    protected function handleEditedChannelPost(array $post): void
    {
        Log::info('Edited channel post received', [
            'message_id' => $post['message_id'] ?? null,
            'chat' => $post['chat']['id'] ?? null,
        ]);
    }
}
