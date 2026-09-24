import ShopLayout from '@/Layouts/ShopLayout';
import { useForm } from '@inertiajs/react';

export default function Show({ product }) {
  const { data, setData, post, processing } = useForm({
    product_id: product.id,
    quantity: 1,
  });

  const submit = (e) => {
    e.preventDefault();
    post('/cart');
  };

  return (
    <div className="max-w-3xl mx-auto p-6">
      <h1 className="text-2xl font-bold">{product.name}</h1>
      <p className="text-gray-600 my-2">{product.description}</p>
      <div className="text-xl font-semibold mb-4">${(product.price_cents / 100).toFixed(2)}</div>

      <form onSubmit={submit} className="flex gap-2 items-center">
        <input
          type="number"
          min="1"
          value={data.quantity}
          onChange={(e) => setData('quantity', e.target.value)}
          className="border rounded px-2 py-1 w-20"
        />
        <button disabled={processing} className="bg-black text-white px-4 py-2 rounded">
          Add to cart
        </button>
      </form>
    </div>
  );
}

Show.layout = (page) => <ShopLayout>{page}</ShopLayout>;
