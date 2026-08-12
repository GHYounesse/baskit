import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ products }) {
  const destroy = (id) => {
    if (confirm('Delete this product?')) {
      router.delete(`/admin/products/${id}`);
    }
  };

  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Manage Products</h2>}>
      <div className="max-w-5xl mx-auto p-6">
        <div className="flex justify-end mb-4">
          <Link href="/admin/products/create" className="bg-black text-white px-4 py-2 rounded">
            + New Product
          </Link>
        </div>

        <table className="w-full bg-white border rounded-lg text-sm">
          <thead>
            <tr className="text-left border-b bg-gray-50">
              <th className="p-3">Name</th>
              <th className="p-3">Category</th>
              <th className="p-3">Price</th>
              <th className="p-3">Stock</th>
              <th className="p-3">Active</th>
              <th className="p-3"></th>
            </tr>
          </thead>
          <tbody>
            {products.data.map((product) => (
              <tr key={product.id} className="border-b">
                <td className="p-3">{product.name}</td>
                <td className="p-3">{product.category?.name ?? '—'}</td>
                <td className="p-3">${(product.price_cents / 100).toFixed(2)}</td>
                <td className="p-3">{product.stock}</td>
                <td className="p-3">{product.is_active ? 'Yes' : 'No'}</td>
                <td className="p-3 text-right space-x-3">
                  <Link href={`/admin/products/${product.id}/edit`} className="text-blue-600">Edit</Link>
                  <button onClick={() => destroy(product.id)} className="text-red-600">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AuthenticatedLayout>
  );
}
