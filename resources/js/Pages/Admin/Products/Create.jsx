import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Create({ categories }) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    category_id: '',
    description: '',
    price_cents: 0,
    stock: 0,
    is_active: true,
  });

  const submit = (e) => {
    e.preventDefault();
    post('/admin/products');
  };

  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold">New Product</h2>}>
      <form onSubmit={submit} className="max-w-lg mx-auto p-6 bg-white border rounded-lg space-y-4">
        <div>
          <label className="block text-sm font-medium mb-1">Name</label>
          <input
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            className="w-full border rounded px-3 py-2"
          />
          {errors.name && <div className="text-red-600 text-sm mt-1">{errors.name}</div>}
        </div>

        <div>
          <label className="block text-sm font-medium mb-1">Category</label>
          <select
            value={data.category_id}
            onChange={(e) => setData('category_id', e.target.value)}
            className="w-full border rounded px-3 py-2"
          >
            <option value="">None</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium mb-1">Description</label>
          <textarea
            value={data.description}
            onChange={(e) => setData('description', e.target.value)}
            className="w-full border rounded px-3 py-2"
            rows={4}
          />
        </div>

        <div className="flex gap-4">
          <div className="flex-1">
            <label className="block text-sm font-medium mb-1">Price (cents)</label>
            <input
              type="number"
              value={data.price_cents}
              onChange={(e) => setData('price_cents', e.target.value)}
              className="w-full border rounded px-3 py-2"
            />
            {errors.price_cents && <div className="text-red-600 text-sm mt-1">{errors.price_cents}</div>}
          </div>
          <div className="flex-1">
            <label className="block text-sm font-medium mb-1">Stock</label>
            <input
              type="number"
              value={data.stock}
              onChange={(e) => setData('stock', e.target.value)}
              className="w-full border rounded px-3 py-2"
            />
          </div>
        </div>

        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={data.is_active}
            onChange={(e) => setData('is_active', e.target.checked)}
          />
          Active (visible in shop)
        </label>

        <button disabled={processing} className="bg-black text-white px-4 py-2 rounded w-full">
          Create Product
        </button>
      </form>
    </AuthenticatedLayout>
  );
}
