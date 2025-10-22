import {useEffect, useState} from 'react';
import {Navigate, useParams, useSearchParams} from 'react-router';
import {CheckoutLayout} from '../checkout-layout';
import {Trans} from '@ui/i18n/trans';
import {apiClient} from '@common/http/query-client';
import {ProgressCircle} from '@ui/progress/progress-circle';
import {Link} from 'react-router';
import {useProducts} from '@common/billing/pricing-table/use-products';

export function CheckoutZarinpalDone() {
  const {productId, priceId} = useParams();
  const [searchParams] = useSearchParams();
  const [isVerifying, setIsVerifying] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const productQuery = useProducts();

  const authority = searchParams.get('Authority');
  const status = searchParams.get('Status');

  useEffect(() => {
    if (status !== 'OK' || !authority) {
      setError('پرداخت لغو شد یا ناموفق بود');
      setIsVerifying(false);
      return;
    }

    // Verify payment
    const verifyPayment = async () => {
      try {
        await apiClient.post('billing/zarinpal/verify-and-store-subscription', {
          authority,
          price_id: parseInt(priceId!),
        });
        setSuccess(true);
      } catch (err: any) {
        setError(
          err.response?.data?.message || 'خطا در تایید پرداخت'
        );
      } finally {
        setIsVerifying(false);
      }
    };

    verifyPayment();
  }, [authority, status, priceId]);

  if (productQuery.isLoading) {
    return (
      <CheckoutLayout>
        <div className="flex items-center justify-center py-50">
          <ProgressCircle isIndeterminate />
        </div>
      </CheckoutLayout>
    );
  }

  const product = productQuery.data?.products.find(
    p => p.id === parseInt(productId!)
  );
  const price = product?.prices.find(p => p.id === parseInt(priceId!));

  if (!product || !price) {
    return <Navigate to="/pricing" replace />;
  }

  return (
    <CheckoutLayout>
      <div className="py-30 text-center">
        {isVerifying ? (
          <div>
            <ProgressCircle className="mx-auto mb-20" isIndeterminate />
            <div className="text-lg">
              <Trans message="در حال تایید پرداخت..." />
            </div>
          </div>
        ) : success ? (
          <div>
            <div className="mb-20 text-4xl text-positive">✓</div>
            <h1 className="mb-10 text-3xl font-bold">
              <Trans message="پرداخت موفق بود!" />
            </h1>
            <p className="mb-30 text-muted">
              <Trans message="اشتراک شما فعال شد." />
            </p>
            <Link
              to="/billing"
              className="rounded bg-primary px-24 py-10 text-white hover:bg-primary-dark"
            >
              <Trans message="مشاهده اشتراک" />
            </Link>
          </div>
        ) : (
          <div>
            <div className="mb-20 text-4xl text-danger">✗</div>
            <h1 className="mb-10 text-3xl font-bold">
              <Trans message="خطا در پرداخت" />
            </h1>
            <p className="mb-30 text-muted">{error}</p>
            <Link
              to={`/checkout/${productId}/${priceId}`}
              className="rounded bg-primary px-24 py-10 text-white hover:bg-primary-dark"
            >
              <Trans message="تلاش مجدد" />
            </Link>
          </div>
        )}
      </div>
    </CheckoutLayout>
  );
}
