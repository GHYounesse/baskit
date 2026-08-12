import { useState, useEffect } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { router, usePage } from '@inertiajs/react';

function PaymentForm({ clientSecret }) {
  const stripe = useStripe();
  const elements = useElements();
  const [processing, setProcessing] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setProcessing(true);

    const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
      payment_method: { card: elements.getElement(CardElement) },
    });

    if (error) {
      alert(error.message);
      setProcessing(false);
      return;
    }

    router.post('/checkout/complete', {
      payment_intent_id: paymentIntent.id,
      shipping_address: { line1: '123 Main St', city: 'Casablanca', country: 'MA' },
    });
  };

  return (
    <form onSubmit={handleSubmit}>
      <CardElement className="border p-3 rounded mb-4" />
      <button disabled={!stripe || processing} className="bg-black text-white px-4 py-2 rounded">
        {processing ? 'Processing…' : 'Pay now'}
      </button>
    </form>
  );
}

export default function Index({ cart, stripeKey }) {
  const [clientSecret, setClientSecret] = useState(null);
  const stripePromise = loadStripe(stripeKey);

  useEffect(() => {
    fetch('/checkout/payment-intent', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then((r) => r.json())
        .then((data) => setClientSecret(data.clientSecret));
    }, []);

  return (
    <div className="max-w-lg mx-auto p-6">
      <h1 className="text-2xl font-bold mb-4">Checkout</h1>
      {clientSecret && (
        <Elements stripe={stripePromise} options={{ clientSecret }}>
          <PaymentForm clientSecret={clientSecret} />
        </Elements>
      )}
    </div>
  );
}
