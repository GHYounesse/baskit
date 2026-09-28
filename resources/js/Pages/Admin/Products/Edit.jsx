import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Edit({ product, categories }) {
  const { data, setData, put, processing, errors } = useForm({
    name: product.name,
    category_id: product.category_id ?? '',
    description: product.description ?? '',
    price_cents: product.price_cents,
    stock: product.stock,
    is_active: product.is_active,
    image: null,
    remove_image: false,
  });
  const [preview, setPreview] = useState(product.image_url);

  const submit = (e) => {
    e.preventDefault();
    put(`/admin/products/${product.id}`);
  };

  const onImageChange = (e) => {
    const file = e.target.files[0] ?? null;
    setData((prev) => ({ ...prev, image: file, remove_image: false }));
    setPreview(file ? URL.createObjectURL(file) : product.image_url);
  };

  const removeImage = () => {
    setData((prev) => ({ ...prev, image: null, remove_image: true }));
    setPreview(null);
  };

  const destroy = () => {
    if (confirm('Delete this product?')) {
      router.delete(`/admin/products/${product.id}`);
    }
  };

  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Edit Product</h2>}>
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

        <div>
          <label className="block text-sm font-medium mb-1">Image</label>
          {preview && <img src={preview} alt="Preview" className="w-32 h-32 object-cover rounded border mb-2" />}
          <input type="file" accept="image/*" onChange={onImageChange} className="w-full" />
          {preview && !data.image && (
            <button type="button" onClick={removeImage} className="text-red-600 text-sm mt-1">
              Remove image
            </button>
          )}
          {errors.image && <div className="text-red-600 text-sm mt-1">{errors.image}</div>}
        </div>

        <div className="flex gap-3">
          <button disabled={processing} className="bg-black text-white px-4 py-2 rounded flex-1">
            Save Changes
          </button>
          <button type="button" onClick={destroy} className="border border-red-600 text-red-600 px-4 py-2 rounded">
            Delete
          </button>
        </div>
      </form>
    </AuthenticatedLayout>
  );
}
