import ShopLayout from '@/Layouts/ShopLayout';
import { useState, useEffect, useMemo } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { router } from '@inertiajs/react';

const COUNTRIES = [
  { code: 'MA', name: 'Morocco' },
  { code: 'US', name: 'United States' },
  { code: 'CA', name: 'Canada' },
  { code: 'GB', name: 'United Kingdom' },
  { code: 'FR', name: 'France' },
  { code: 'DE', name: 'Germany' },
  { code: 'ES', name: 'Spain' },
];

function isAddressComplete(address) {
  return Boolean(address.line1.trim() && address.city.trim() && address.country);
}

function ShippingAddressForm({ address, setAddress, errors }) {
  const update = (field) => (e) => setAddress((prev) => ({ ...prev, [field]: e.target.value }));

  return (
    <div className="space-y-4 mb-6">
      <h2 className="font-semibold">Shipping address</h2>

      <div>
        <label className="block text-sm font-medium mb-1">Address line 1</label>
        <input
          value={address.line1}
          onChange={update('line1')}
          className="w-full border rounded px-3 py-2"
          required
        />
        {errors['shipping_address.line1'] && (
          <div className="text-red-600 text-sm mt-1">{errors['shipping_address.line1']}</div>
        )}
      </div>

      <div>
        <label className="block text-sm font-medium mb-1">Address line 2 (optional)</label>
        <input value={address.line2} onChange={update('line2')} className="w-full border rounded px-3 py-2" />
      </div>

      <div className="flex gap-4">
        <div className="flex-1">
          <label className="block text-sm font-medium mb-1">City</label>
          <input
            value={address.city}
            onChange={update('city')}
            className="w-full border rounded px-3 py-2"
            required
          />
          {errors['shipping_address.city'] && (
            <div className="text-red-600 text-sm mt-1">{errors['shipping_address.city']}</div>
          )}
        </div>
        <div className="flex-1">
          <label className="block text-sm font-medium mb-1">State / Province (optional)</label>
          <input value={address.state} onChange={update('state')} className="w-full border rounded px-3 py-2" />
        </div>
      </div>

      <div className="flex gap-4">
        <div className="flex-1">
          <label className="block text-sm font-medium mb-1">Postal code (optional)</label>
          <input
            value={address.postal_code}
            onChange={update('postal_code')}
            className="w-full border rounded px-3 py-2"
          />
        </div>
        <div className="flex-1">
          <label className="block text-sm font-medium mb-1">Country</label>
          <select
            value={address.country}
            onChange={update('country')}
            className="w-full border rounded px-3 py-2"
            required
          >
            <option value="">Select a country</option>
            {COUNTRIES.map((c) => (
              <option key={c.code} value={c.code}>{c.name}</option>
            ))}
          </select>
          {errors['shipping_address.country'] && (
            <div className="text-red-600 text-sm mt-1">{errors['shipping_address.country']}</div>
          )}
        </div>
      </div>
    </div>
  );
}

function PaymentForm({ clientSecret, address, addressComplete }) {
  const stripe = useStripe();
  const elements = useElements();
  const [processing, setProcessing] = useState(false);
  const [cardError, setCardError] = useState(null);

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
    <form onSubmit={handleSubmit}>
      <CardElement className="border p-3 rounded mb-4" />
      {cardError && <p className="text-red-600 text-sm mb-4">{cardError}</p>}
      <button
        disabled={!stripe || processing || !addressComplete}
        className="bg-black text-white px-4 py-2 rounded disabled:opacity-50"
      >
        {processing ? 'Processing…' : 'Pay now'}
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

  useEffect(() => {
    window.axios
      .post('/checkout/payment-intent')
      .then((res) => setClientSecret(res.data.clientSecret))
      .catch((err) => setError(err.response?.data?.message || 'Unable to start checkout.'));
  }, []);

  return (
    <div className="max-w-lg mx-auto p-6">
      <h1 className="text-2xl font-bold mb-4">Checkout</h1>
      {error && <p className="text-red-600 mb-4">{error}</p>}

      <ShippingAddressForm address={address} setAddress={setAddress} errors={errors ?? {}} />

      {clientSecret && (
        <Elements stripe={stripePromise} options={{ clientSecret }}>
          <PaymentForm clientSecret={clientSecret} address={address} addressComplete={isAddressComplete(address)} />
        </Elements>
      )}
    </div>
  );
}

Index.layout = (page) => <ShopLayout>{page}</ShopLayout>;
