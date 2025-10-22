<?php

namespace Common\Billing\Gateways\Zarinpal;

use Common\Billing\GatewayException;
use Common\Billing\Models\Price;
use Common\Billing\Models\Product;
use Illuminate\Support\Str;

class ZarinpalPlans
{
    use InteractsWithZarinpalRestApi;

    /**
     * Sync product prices with ZarinPal
     * Since ZarinPal doesn't have a native plan system,
     * we just validate and mark prices as ready for ZarinPal
     */
    public function sync(Product $product): bool
    {
        $product->load('prices');

        // Filter only IRR currency prices
        $product->prices->each(function (Price $price) use ($product) {
            // Only sync IRR currency prices
            if (strtoupper($price->currency) === 'IRR') {
                // For ZarinPal, we generate a unique ID locally
                // since there's no remote plan to sync with
                if (!$price->zarinpal_id) {
                    $zarinpalId = 'zp_' . Str::random(24);
                    $price->fill(['zarinpal_id' => $zarinpalId])->save();
                }
            }
        });

        return true;
    }

    /**
     * Delete plan - for ZarinPal this is just a local operation
     */
    public function delete(Product $product): bool
    {
        $product->load('prices');

        $product->prices->each(function (Price $price) {
            if ($price->zarinpal_id) {
                $price->fill(['zarinpal_id' => null])->save();
            }
        });

        return true;
    }

    /**
     * Validate if price can be used with ZarinPal
     */
    public function canUsePrice(Price $price): bool
    {
        // ZarinPal only supports IRR currency
        return strtoupper($price->currency) === 'IRR' && $price->amount > 0;
    }
}
