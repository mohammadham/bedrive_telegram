<?php

namespace Common\Billing\Gateways\Zarinpal;

use Common\Billing\GatewayException;
use Common\Billing\Models\Price;
use Common\Billing\Subscription;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ZarinpalWebhookController extends Controller
{
    use InteractsWithZarinpalRestApi;

    public function __construct(
        protected Subscription $subscription,
        protected Zarinpal $zarinpal,
    ) {
    }

    /**
     * Handle ZarinPal callback
     * Note: ZarinPal uses GET callback, not webhook
     */
    public function handleCallback(Request $request): Response
    {
        $authority = $request->get('Authority');
        $status = $request->get('Status');

        // Status OK means payment was successful
        if ($status !== 'OK') {
            Log::warning('ZarinPal payment failed', [
                'authority' => $authority,
                'status' => $status,
            ]);

            return response('Payment cancelled or failed', 200);
        }

        // Payment verification should be done by the frontend
        // calling the verifyAndStoreSubscription endpoint
        return response('Payment successful, please verify', 200);
    }
}
