<?php

namespace Common\Billing\Gateways\Zarinpal;

use Common\Billing\Models\Price;
use Common\Billing\Subscription;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ZarinpalController extends BaseController
{
    public function __construct(
        protected Request $request,
        protected Subscription $subscription,
        protected Zarinpal $zarinpal,
    ) {
        $this->middleware('auth');
    }

    /**
     * Create payment request and return payment URL
     */
    public function createPaymentRequest(): JsonResponse
    {
        $data = $this->validate($this->request, [
            'price_id' => 'required|integer|exists:prices,id',
        ]);

        $price = Price::with('product')->findOrFail($data['price_id']);
        $user = Auth::user();

        // Validate currency
        if (strtoupper($price->currency) !== 'IRR') {
            return $this->error(
                'ZarinPal only supports IRR currency',
                [],
                422
            );
        }

        try {
            $paymentData = $this->zarinpal->subscriptions->createPaymentRequest(
                $user,
                $price,
                $price->product
            );

            return $this->success($paymentData);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [], 500);
        }
    }

    /**
     * Verify payment and store subscription details
     */
    public function verifyAndStoreSubscription(): Response|JsonResponse
    {
        $data = $this->validate($this->request, [
            'authority' => 'required|string',
            'price_id' => 'required|integer|exists:prices,id',
        ]);

        $price = Price::findOrFail($data['price_id']);

        try {
            $this->zarinpal->subscriptions->verifyAndSync(
                $data['authority'],
                Auth::id(),
                $price
            );

            return $this->success();
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [], 500);
        }
    }
}
