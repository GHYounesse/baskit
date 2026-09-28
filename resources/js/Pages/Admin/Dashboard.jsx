import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import { formatMoney } from '@/utils/money';

function StatCard({ label, value }) {
  return (
    <div className="rounded-lg border border-stroke bg-surface p-4">
      <div className="text-sm text-ink-muted">{label}</div>
      <div className="mt-1 text-2xl font-bold text-ink">{value}</div>
    </div>
  );
}

function Section({ title, children }) {
  return (
    <div>
      <h3 className="mb-3 font-medium text-ink">{title}</h3>
      {children}
    </div>
  );
}

export default function Dashboard({ stats, lowStockProducts, recentOrders }) {
  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">Admin dashboard</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-5xl space-y-8 px-6 py-8">
          <Head title="Admin dashboard" />

          <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
            <StatCard label="Revenue" value={formatMoney(stats.revenue_cents)} />
            <StatCard label="Orders" value={stats.order_count} />
            <StatCard label="Products" value={stats.product_count} />
            <StatCard label="Low stock" value={stats.low_stock_count} />
          </div>

          <Section title="Low stock products">
            {lowStockProducts.length === 0 ? (
              <p className="text-sm text-ink-muted">Nothing is running low.</p>
            ) : (
              <div className="overflow-x-auto rounded-lg border border-stroke bg-surface">
                <table className="w-full min-w-[480px] text-sm">
                  <thead>
                    <tr className="border-b border-stroke text-left text-ink-muted">
                      <th className="p-3 font-medium">Product</th>
                      <th className="p-3 font-medium">Stock left</th>
                      <th className="p-3"></th>
                    </tr>
                  </thead>
                  <tbody>
                    {lowStockProducts.map((product) => (
                      <tr key={product.id} className="border-b border-stroke last:border-b-0 hover:bg-primary-light/30">
                        <td className="p-3 text-ink">{product.name}</td>
                        <td className="p-3">
                          {product.stock === 0 ? (
                            <span className="inline-block rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-300">
                              Out of stock
                            </span>
                          ) : (
                            <span className="font-medium text-amber-600">{product.stock}</span>
                          )}
                        </td>
                        <td className="p-3 text-right">
                          <Link href={`/admin/products/${product.id}/edit`} className="text-primary hover:text-primary-hover">
                            Edit
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Section>

          <Section title="Recent orders">
            {recentOrders.length === 0 ? (
              <p className="text-sm text-ink-muted">No orders yet.</p>
            ) : (
              <div className="overflow-x-auto rounded-lg border border-stroke bg-surface">
                <table className="w-full min-w-[560px] text-sm">
                  <thead>
                    <tr className="border-b border-stroke text-left text-ink-muted">
                      <th className="p-3 font-medium">Order</th>
                      <th className="p-3 font-medium">Customer</th>
                      <th className="p-3 font-medium text-right">Total</th>
                      <th className="p-3 font-medium">Status</th>
                      <th className="p-3 font-medium">Placed</th>
                    </tr>
                  </thead>
                  <tbody>
                    {recentOrders.map((order) => (
                      <tr key={order.id} className="border-b border-stroke last:border-b-0 hover:bg-primary-light/30">
                        <td className="p-3 text-ink">#{order.id}</td>
                        <td className="p-3 text-ink-muted">{order.user?.name ?? '—'}</td>
                        <td className="p-3 text-right font-medium text-ink">{formatMoney(order.total_cents)}</td>
                        <td className="p-3"><OrderStatusBadge status={order.status} /></td>
                        <td className="p-3 text-ink-muted">{new Date(order.created_at).toLocaleDateString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Section>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
