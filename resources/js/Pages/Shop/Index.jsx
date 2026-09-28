import ShopLayout from '@/Layouts/ShopLayout';
import { Link, router } from '@inertiajs/react';

export default function Index({ products, categories, filters }) {
  return (
    <div className="max-w-6xl mx-auto p-6">
      <div className="flex gap-4 mb-6">
        <input
          defaultValue={filters.search}
          placeholder="Search products..."
          className="border rounded px-3 py-2 flex-1"
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              router.get('/', { ...filters, search: e.target.value }, { preserveState: true });
            }
          }}
        />
        <select
          defaultValue={filters.category ?? ''}
          onChange={(e) => router.get('/', { ...filters, category: e.target.value })}
          className="border rounded px-3 py-2"
        >
          <option value="">All categories</option>
          {categories.map((c) => (
            <option key={c.id} value={c.slug}>{c.name}</option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
        {products.data.map((product) => (
          <Link
            key={product.id}
            href={`/products/${product.slug}`}
            className="border rounded-lg p-4 hover:shadow-lg transition"
          >
            <div className="aspect-square mb-3 bg-gray-100 rounded overflow-hidden flex items-center justify-center">
              {product.image_url ? (
                <img src={product.image_url} alt={product.name} className="w-full h-full object-cover" />
              ) : (
                <span className="text-gray-400 text-sm">No image</span>
              )}
            </div>
            <div className="font-semibold">{product.name}</div>
            <div className="text-gray-600">${(product.price_cents / 100).toFixed(2)}</div>
          </Link>
        ))}
      </div>
    </div>
  );
}

Index.layout = (page) => <ShopLayout>{page}</ShopLayout>;
