<?php

namespace Common\Billing\Gateways\Zarinpal;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait InteractsWithZarinpalRestApi
{
    public function zarinpal(): PendingRequest
    {
        $baseUrl = settings('billing.zarinpal_test_mode')
            ? 'https://sandbox.zarinpal.com/pg/v4'
            : 'https://payment.zarinpal.com/pg/v4';

        $merchantId = config('services.zarinpal.merchant_id');

        return Http::timeout(30)
            ->acceptJson()
            ->contentType('application/json')
            ->baseUrl($baseUrl)
            ->withHeaders([
                'Accept' => 'application/json',
            ]);
    }

    protected function getMerchantId(): string
    {
        return config('services.zarinpal.merchant_id');
    }

    protected function getCallbackUrl(string $productId, string $priceId): string
    {
        $baseUrl = config('app.url');
        return "{$baseUrl}/checkout/{$productId}/{$priceId}/zarinpal/done";
    }

    protected function getPaymentUrl(string $authority): string
    {
        $baseUrl = settings('billing.zarinpal_test_mode')
            ? 'https://sandbox.zarinpal.com/pg/StartPay'
            : 'https://www.zarinpal.com/pg/StartPay';

        return "{$baseUrl}/{$authority}";
    }
}
