import ShopLayout from '@/Layouts/ShopLayout';
import { router, Link, usePage } from '@inertiajs/react';

export default function Index({ cart }) {
  const { auth } = usePage().props;
  const total = cart.items.reduce((sum, i) => sum + i.quantity * i.product.price_cents, 0);

  return (
    <div className="max-w-3xl mx-auto p-6">
      <h1 className="text-2xl font-bold mb-4">Your Cart</h1>
      {cart.items.map((item) => (
        <div key={item.id} className="flex justify-between items-center border-b py-3">
          <div>{item.product.name}</div>
          <input
            type="number"
            min="1"
            defaultValue={item.quantity}
            onBlur={(e) => router.patch(`/cart/${item.id}`, { quantity: e.target.value })}
            className="border rounded px-2 py-1 w-16"
          />
          <button onClick={() => router.delete(`/cart/${item.id}`)} className="text-red-600">Remove</button>
        </div>
      ))}
      <div className="text-right font-semibold mt-4">Total: ${(total / 100).toFixed(2)}</div>
      <Link href="/checkout" className="block text-center bg-black text-white py-2 rounded mt-4">
        {auth.user ? 'Checkout' : 'Log in to checkout'}
      </Link>
    </div>
  );
}

Index.layout = (page) => <ShopLayout>{page}</ShopLayout>;
