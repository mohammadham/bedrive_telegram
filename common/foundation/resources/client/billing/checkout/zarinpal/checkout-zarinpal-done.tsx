import {useEffect, useRef, useState} from 'react';
import {useParams, useSearchParams} from 'react-router';
import {CheckoutLayout} from '../checkout-layout';
import {CheckoutProductSummary} from '../checkout-product-summary';
import {
  BillingRedirectMessage,
  BillingRedirectMessageConfig,
} from '../../billing-redirect-message';
import {message} from '@ui/i18n/message';
import {apiClient} from '@common/http/query-client';

export function CheckoutZarinpalDone() {
  const {productId, priceId} = useParams();
  const [searchParams] = useSearchParams();
  const alreadyVerified = useRef(false);

  const [messageConfig, setMessageConfig] =
    useState<BillingRedirectMessageConfig>();

  const authority = searchParams.get('Authority');
  const status = searchParams.get('Status');

  useEffect(() => {
    if (alreadyVerified.current) {
      return;
    }

    if (status === 'OK' && authority) {
      verifyPayment(authority, priceId!).then(() => {
        setMessageConfig(
          getRedirectMessageConfig('success', productId, priceId),
        );
        window.location.href = '/billing';
      }).catch(() => {
        setMessageConfig(getRedirectMessageConfig('error', productId, priceId));
      });
    } else {
      setMessageConfig(getRedirectMessageConfig('error', productId, priceId));
    }
    alreadyVerified.current = true;
  }, [authority, status, priceId, productId]);

  return (
    <CheckoutLayout>
      <BillingRedirectMessage config={messageConfig} />
      <CheckoutProductSummary showBillingLine={false} />
    </CheckoutLayout>
  );
}

function getRedirectMessageConfig(
  status?: 'success' | 'error' | string,
  productId?: string,
  priceId?: string,
): BillingRedirectMessageConfig {
  switch (status) {
    case 'success':
      return {
        message: message('پرداخت با موفقیت انجام شد!'),
        status: 'success',
        buttonLabel: message('بازگشت به سایت'),
        link: '/billing',
      };
    default:
      return {
        message: message('خطا در پرداخت. لطفاً دوباره تلاش کنید.'),
        status: 'error',
        buttonLabel: message('بازگشت'),
        link: errorLink(productId, priceId),
      };
  }
}

function errorLink(productId?: string, priceId?: string): string {
  return productId && priceId ? `/checkout/${productId}/${priceId}` : '/';
}

function verifyPayment(authority: string, priceId: string) {
  return apiClient.post('billing/zarinpal/verify-and-store-subscription', {
    authority,
    price_id: parseInt(priceId),
  });
}
