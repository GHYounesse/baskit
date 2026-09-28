import ShopLayout from '@/Layouts/ShopLayout';
import { formatMoney } from '@/utils/money';
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
      <div className="aspect-square mb-4 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center">
        {product.image_url ? (
          <img src={product.image_url} alt={product.name} className="w-full h-full object-cover" />
        ) : (
          <span className="text-gray-400">No image</span>
        )}
      </div>

      <h1 className="text-2xl font-bold">{product.name}</h1>
      <p className="text-gray-600 my-2">{product.description}</p>
      <div className="text-xl font-semibold mb-4">{formatMoney(product.price_cents)}</div>

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
