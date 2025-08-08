<?php

namespace App\Http\Controllers\Webhooks;

use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends BaseController
{
    /**
     * Handle incoming Telegram webhook.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        Log::info('Telegram webhook received: ' . $request->getContent());

        // Acknowledge the webhook to prevent Telegram from resending
        return response('OK', 200);
    }
}
