<?php

namespace Common\Billing\Gateways\Zarinpal;

use App\Models\User;
use Common\Billing\GatewayException;
use Common\Billing\Invoices\Invoice;
use Common\Billing\Models\Price;
use Common\Billing\Models\Product;
use Common\Billing\Notifications\NewInvoiceAvailable;
use Common\Billing\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ZarinpalSubscriptions
{
    use InteractsWithZarinpalRestApi;

    public function isIncomplete(Subscription $subscription): bool
    {
        return $subscription->gateway_status === 'pending';
    }

    public function isPastDue(Subscription $subscription): bool
    {
        // For manual renewals, check if subscription has ended
        return $subscription->ends_at && 
               $subscription->ends_at->isPast() && 
               !$subscription->valid();
    }

    /**
     * Create payment request and return payment URL
     */
    public function createPaymentRequest(
        User $user,
        Price $price,
        Product $product
    ): array {
        $amount = (int) $price->amount; // ZarinPal uses Rials
        $description = "اشتراک {$product->name} - {$price->interval} {$price->interval_count}";
        $metadata = [
            'user_id' => $user->id,
            'price_id' => $price->id,
            'product_id' => $product->id,
        ];

        $response = $this->zarinpal()->post('/payment/request.json', [
            'merchant_id' => $this->getMerchantId(),
            'amount' => $amount,
            'description' => $description,
            'callback_url' => $this->getCallbackUrl($product->id, $price->id),
            'metadata' => $metadata,
        ]);

        if (!$response->successful() || $response['data']['code'] != 100) {
            throw new GatewayException(
                'Could not create payment request: ' . 
                ($response['errors']['message'] ?? 'Unknown error')
            );
        }

        $authority = $response['data']['authority'];

        return [
            'authority' => $authority,
            'payment_url' => $this->getPaymentUrl($authority),
        ];
    }

    /**
     * Verify payment and create/update subscription
     */
    public function verifyAndSync(
        string $authority,
        int $userId,
        Price $price
    ): Subscription {
        $user = User::findOrFail($userId);
        $amount = (int) $price->amount;

        $response = $this->zarinpal()->post('/payment/verify.json', [
            'merchant_id' => $this->getMerchantId(),
            'amount' => $amount,
            'authority' => $authority,
        ]);

        if (!$response->successful() || $response['data']['code'] != 100) {
            throw new GatewayException(
                'Payment verification failed: ' . 
                ($response['errors']['message'] ?? 'Unknown error')
            );
        }

        $refId = $response['data']['ref_id'];
        $cardPan = $response['data']['card_pan'] ?? null;

        // Store card_pan in user if available
        if ($cardPan) {
            $user->update(['zarinpal_id' => $cardPan]);
        }

        // Calculate subscription period
        $startsAt = Carbon::now();
        $renewsAt = $this->calculateRenewDate($price, $startsAt);

        // Create or update subscription
        $subscription = $user->subscriptions()->updateOrCreate(
            [
                'gateway_name' => 'zarinpal',
                'user_id' => $user->id,
            ],
            [
                'price_id' => $price->id,
                'product_id' => $price->product_id,
                'gateway_name' => 'zarinpal',
                'gateway_id' => $authority,
                'gateway_status' => 'active',
                'renews_at' => $renewsAt,
                'ends_at' => null,
            ]
        );

        // Create invoice
        $this->createInvoice($subscription, $refId, $amount);

        return $subscription;
    }

    /**
     * Calculate renewal date based on interval
     */
    protected function calculateRenewDate(Price $price, Carbon $startDate): Carbon
    {
        $renewDate = $startDate->copy();

        switch (strtolower($price->interval)) {
            case 'day':
                $renewDate->addDays($price->interval_count);
                break;
            case 'week':
                $renewDate->addWeeks($price->interval_count);
                break;
            case 'month':
                $renewDate->addMonths($price->interval_count);
                break;
            case 'year':
                $renewDate->addYears($price->interval_count);
                break;
        }

        return $renewDate;
    }

    /**
     * Create invoice for successful payment
     */
    protected function createInvoice(
        Subscription $subscription,
        string $refId,
        int $amount
    ): void {
        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'paid' => true,
            'uuid' => Str::random(10),
            'notes' => "ZarinPal Ref ID: {$refId}",
        ]);

        if ($invoice->paid && !$invoice->notified) {
            $subscription->user->notify(new NewInvoiceAvailable($invoice));
            $invoice->update(['notified' => true]);
        }
    }

    /**
     * Change subscription plan
     * For ZarinPal, this requires a new payment
     */
    public function changePlan(
        Subscription $subscription,
        Product $newProduct,
        Price $newPrice
    ): bool {
        // Mark current subscription as cancelled
        $subscription->markAsCancelled();

        // User needs to make a new payment for the new plan
        // Return true to indicate the plan change is initiated
        return true;
    }

    /**
     * Cancel subscription
     */
    public function cancel(
        Subscription $subscription,
        bool $atPeriodEnd = true
    ): bool {
        if ($atPeriodEnd) {
            // Let it expire naturally at renews_at
            $subscription->update([
                'ends_at' => $subscription->renews_at ?? Carbon::now(),
            ]);
        } else {
            // Cancel immediately
            $subscription->markAsCancelled();
        }

        return true;
    }

    /**
     * Resume subscription - requires new payment
     */
    public function resume(
        Subscription $subscription,
        array $params
    ): bool {
        // For ZarinPal, resume requires a new payment
        // Just mark as ready for renewal
        $subscription->update([
            'ends_at' => null,
            'gateway_status' => 'pending',
        ]);

        return true;
    }
}
