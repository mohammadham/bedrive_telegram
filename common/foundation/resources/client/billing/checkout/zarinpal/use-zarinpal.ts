import {useEffect, useState} from 'react';
import {useProducts} from '@common/billing/pricing-table/use-products';
import {useSettings} from '@ui/settings/use-settings';
import {apiClient} from '@common/http/query-client';

interface UseZarinpalProps {
  productId?: string;
  priceId?: string;
}

export function useZarinpal({productId, priceId}: UseZarinpalProps) {
  const {data} = useProducts();
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const {
    billing: {
      zarinpal: {enable: zarinpalEnabled, merchant_id},
    },
  } = useSettings();

  const handlePayment = async () => {
    if (!productId || !priceId) {
      setError('محصول یا قیمت انتخاب نشده است');
      return;
    }

    const product = data?.products.find(p => p.id === parseInt(productId));
    const price = product?.prices.find(p => p.id === parseInt(priceId));

    if (!price) {
      setError('قیمت یافت نشد');
      return;
    }

    // Check if currency is IRR
    if (price.currency.toUpperCase() !== 'IRR') {
      setError('زرین‌پال فقط از واحد پولی ریال (IRR) پشتیبانی می\u200cکند');
      return;
    }

    setIsProcessing(true);
    setError(null);

    try {
      const response = await apiClient.post(
        'billing/zarinpal/create-payment-request',
        {
          price_id: parseInt(priceId),
        }
      );

      if (response.data.payment_url) {
        // Redirect to ZarinPal payment page
        window.location.href = response.data.payment_url;
      } else {
        setError('خطا در ایجاد درخواست پرداخت');
        setIsProcessing(false);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'خطا در اتصال به سرور');
      setIsProcessing(false);
    }
  };

  return {
    zarinpalEnabled,
    isProcessing,
    error,
    handlePayment,
    canUseZarinpal: zarinpalEnabled && merchant_id != null,
  };
}
