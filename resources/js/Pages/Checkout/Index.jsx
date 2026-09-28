import ShopLayout from '@/Layouts/ShopLayout';
import { formatMoney } from '@/utils/money';
import { useState, useEffect, useMemo } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { Head, router } from '@inertiajs/react';

const COUNTRIES = [
  { code: 'MA', name: 'Morocco' },
  { code: 'US', name: 'United States' },
  { code: 'CA', name: 'Canada' },
  { code: 'GB', name: 'United Kingdom' },
  { code: 'FR', name: 'France' },
  { code: 'DE', name: 'Germany' },
  { code: 'ES', name: 'Spain' },
];

// Stripe's CardElement renders inside a cross-origin iframe, so it can't
// inherit our CSS custom properties. These mirror the light/dark values in
// resources/css/app.css, read once at mount.
const CARD_THEME = {
  light: { text: '#17201D', muted: '#66736E' },
  dark: { text: '#F1F7F4', muted: '#A6B8B1' },
};

function isAddressComplete(address) {
  return Boolean(address.line1.trim() && address.city.trim() && address.country);
}

function Field({ label, error, children }) {
  return (
    <div>
      <label className="mb-1 block text-sm font-medium text-ink">{label}</label>
      {children}
      {error && <div className="mt-1 text-sm text-red-600">{error}</div>}
    </div>
  );
}

const inputClass =
  'w-full rounded-lg border-stroke bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary';

function ShippingAddressForm({ address, setAddress, errors }) {
  const update = (field) => (e) => setAddress((prev) => ({ ...prev, [field]: e.target.value }));

  return (
    <div className="space-y-4">
      <h2 className="font-medium text-ink">Shipping address</h2>

      <Field label="Address line 1" error={errors['shipping_address.line1']}>
        <input value={address.line1} onChange={update('line1')} className={inputClass} required />
      </Field>

      <Field label="Address line 2 (optional)">
        <input value={address.line2} onChange={update('line2')} className={inputClass} />
      </Field>

      <div className="flex gap-4">
        <div className="flex-1">
          <Field label="City" error={errors['shipping_address.city']}>
            <input value={address.city} onChange={update('city')} className={inputClass} required />
          </Field>
        </div>
        <div className="flex-1">
          <Field label="State / Province (optional)">
            <input value={address.state} onChange={update('state')} className={inputClass} />
          </Field>
        </div>
      </div>

      <div className="flex gap-4">
        <div className="flex-1">
          <Field label="Postal code (optional)">
            <input value={address.postal_code} onChange={update('postal_code')} className={inputClass} />
          </Field>
        </div>
        <div className="flex-1">
          <Field label="Country" error={errors['shipping_address.country']}>
            <select value={address.country} onChange={update('country')} className={inputClass} required>
              <option value="">Select a country</option>
              {COUNTRIES.map((c) => (
                <option key={c.code} value={c.code}>{c.name}</option>
              ))}
            </select>
          </Field>
        </div>
      </div>
    </div>
  );
}

function PaymentForm({ clientSecret, address, addressComplete, total }) {
  const stripe = useStripe();
  const elements = useElements();
  const [processing, setProcessing] = useState(false);
  const [cardError, setCardError] = useState(null);

  const isDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  const theme = isDark ? CARD_THEME.dark : CARD_THEME.light;

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!addressComplete) {
      setCardError('Fill in the required shipping address fields first.');
      return;
    }

    setProcessing(true);
    setCardError(null);

    const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
      payment_method: {
        card: elements.getElement(CardElement),
        billing_details: { address: { line1: address.line1, city: address.city, country: address.country } },
      },
    });

    if (error) {
      setCardError(error.message);
      setProcessing(false);
      return;
    }

    router.post('/checkout/complete', {
      payment_intent_id: paymentIntent.id,
      shipping_address: address,
    }, {
      onError: () => setProcessing(false),
    });
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <h2 className="font-medium text-ink">Payment</h2>
      <div className="rounded-lg border border-stroke bg-surface px-3 py-3">
        <CardElement
          options={{
            style: {
              base: {
                color: theme.text,
                fontFamily: 'Figtree, sans-serif',
                fontSize: '14px',
                '::placeholder': { color: theme.muted },
              },
              invalid: { color: '#dc2626' },
            },
          }}
        />
      </div>
      {cardError && <p className="text-sm text-red-600">{cardError}</p>}
      <button
        disabled={!stripe || processing || !addressComplete}
        className="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover disabled:opacity-50"
      >
        {processing ? 'Processing…' : `Pay ${formatMoney(total)}`}
      </button>
    </form>
  );
}

export default function Index({ cart, stripeKey, errors }) {
  const [clientSecret, setClientSecret] = useState(null);
  const [error, setError] = useState(null);
  const [address, setAddress] = useState({
    line1: '', line2: '', city: '', state: '', postal_code: '', country: 'MA',
  });
  const stripePromise = useMemo(() => loadStripe(stripeKey), [stripeKey]);
  const total = cart.items.reduce((sum, i) => sum + i.quantity * i.product.price_cents, 0);

  useEffect(() => {
    window.axios
      .post('/checkout/payment-intent')
      .then((res) => setClientSecret(res.data.clientSecret))
      .catch((err) => setError(err.response?.data?.message || 'Unable to start checkout.'));
  }, []);

  return (
    <div className="bg-canvas text-ink">
      <div className="mx-auto max-w-4xl px-6 py-8">
        <Head title="Checkout" />

        <h1 className="font-display text-2xl font-bold text-ink">Checkout</h1>
        {error && <p className="mt-4 text-sm text-red-600">{error}</p>}

        <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="space-y-8 rounded-lg border border-stroke bg-surface p-5 lg:col-span-2">
            <ShippingAddressForm address={address} setAddress={setAddress} errors={errors ?? {}} />

            {clientSecret && (
              <Elements stripe={stripePromise} options={{ clientSecret }}>
                <PaymentForm
                  clientSecret={clientSecret}
                  address={address}
                  addressComplete={isAddressComplete(address)}
                  total={total}
                />
              </Elements>
            )}
          </div>

          <div className="h-fit rounded-lg border border-stroke bg-surface p-4">
            <div className="font-medium text-ink">Order summary</div>
            <div className="mt-3 space-y-2">
              {cart.items.map((item) => (
                <div key={item.id} className="flex justify-between gap-3 text-sm">
                  <span className="text-ink-muted">
                    {item.product.name} <span className="text-ink-muted/70">× {item.quantity}</span>
                  </span>
                  <span className="shrink-0 text-ink">{formatMoney(item.quantity * item.product.price_cents)}</span>
                </div>
              ))}
            </div>
            <div className="mt-3 flex items-center justify-between border-t border-stroke pt-3 font-semibold text-ink">
              <span>Total</span>
              <span>{formatMoney(total)}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Index.layout = (page) => <ShopLayout>{page}</ShopLayout>;
