import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { formatMoney } from '@/utils/money';

const LOW_STOCK_THRESHOLD = 5;

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

function StatusPill({ active }) {
  return (
    <span
      className={`inline-block rounded-full px-2.5 py-1 text-xs font-medium ${
        active
          ? 'bg-primary-light text-primary'
          : 'bg-stroke text-ink-muted'
      }`}
    >
      {active ? 'Active' : 'Inactive'}
    </span>
  );
}

function EmptyProducts() {
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-stroke py-20 text-center">
      <BasketIcon className="h-8 w-8 text-ink-muted/50" />
      <div className="font-medium text-ink">No products yet</div>
      <Link href="/admin/products/create" className="text-sm font-medium text-primary hover:text-primary-hover">
        Add your first product
      </Link>
    </div>
  );
}

export default function Index({ products }) {
  const destroy = (id) => {
    if (confirm('Delete this product?')) {
      router.delete(`/admin/products/${id}`);
    }
  };

  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">Manage products</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-5xl px-6 py-8">
          <Head title="Manage products" />

          <div className="mb-4 flex justify-end">
            <Link
              href="/admin/products/create"
              className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-hover"
            >
              + New product
            </Link>
          </div>

          {products.data.length === 0 ? (
            <EmptyProducts />
          ) : (
            <div className="overflow-x-auto rounded-lg border border-stroke bg-surface">
              <table className="w-full min-w-[640px] text-sm">
                <thead>
                  <tr className="border-b border-stroke text-left text-ink-muted">
                    <th className="p-3"></th>
                    <th className="p-3 font-medium">Name</th>
                    <th className="p-3 font-medium">Category</th>
                    <th className="p-3 font-medium text-right">Price</th>
                    <th className="p-3 font-medium text-right">Stock</th>
                    <th className="p-3 font-medium">Status</th>
                    <th className="p-3"></th>
                  </tr>
                </thead>
                <tbody>
                  {products.data.map((product) => (
                    <tr key={product.id} className="border-b border-stroke last:border-b-0 hover:bg-primary-light/30">
                      <td className="p-3">
                        <div className="h-10 w-10 overflow-hidden rounded-lg bg-canvas">
                          {product.image_url ? (
                            <img src={product.image_url} alt={product.name} className="h-full w-full object-cover" />
                          ) : (
                            <div className="flex h-full w-full items-center justify-center text-ink-muted/40">
                              <BasketIcon className="h-4 w-4" />
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="p-3 text-ink">{product.name}</td>
                      <td className="p-3 text-ink-muted">{product.category?.name ?? '—'}</td>
                      <td className="p-3 text-right text-ink">{formatMoney(product.price_cents)}</td>
                      <td
                        className={`p-3 text-right ${
                          product.stock <= LOW_STOCK_THRESHOLD ? 'font-medium text-amber-600' : 'text-ink'
                        }`}
                      >
                        {product.stock}
                      </td>
                      <td className="p-3">
                        <StatusPill active={product.is_active} />
                      </td>
                      <td className="p-3 text-right">
                        <div className="flex justify-end gap-3">
                          <Link href={`/admin/products/${product.id}/edit`} className="text-primary hover:text-primary-hover">
                            Edit
                          </Link>
                          <button onClick={() => destroy(product.id)} className="text-red-600 hover:text-red-700">
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <Pagination links={products.links} />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
