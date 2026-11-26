import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';

const focusRing = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary';

// The things an admin has to act on today, each one a link to where they do it.
function Attention({ attention }) {
  const items = [
    {
      count: attention.to_ship,
      label: attention.to_ship === 1 ? 'order to ship' : 'orders to ship',
      hint: 'Paid and waiting for a carrier',
      href: '/admin/orders',
    },
    {
      count: attention.out_of_stock,
      label: 'out of stock',
      hint: "Customers can't buy these",
      href: '/admin/inventory',
    },
  ].filter((item) => item.count > 0);

  if (items.length === 0) {
    return (
      <p className="rounded-lg border border-dashed border-stroke px-4 py-3 text-sm text-ink-muted">
        Nothing needs your attention right now.
      </p>
    );
  }

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {items.map((item) => (
        <Link
          key={item.label}
          href={item.href}
          className={`rounded-lg border border-stroke border-l-4 border-l-warning bg-surface p-4 transition hover:border-primary hover:border-l-warning ${focusRing}`}
        >
          <div className="flex items-baseline gap-2">
            <span className="font-display text-3xl font-bold text-ink">{item.count}</span>
            <span className="font-medium text-ink">{item.label}</span>
          </div>
          <div className="mt-1 text-sm text-ink-muted">{item.hint}</div>
        </Link>
      ))}
    </div>
  );
}

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

export default function Dashboard({ stats, attention, lowStockProducts, recentOrders }) {
  return (
    <AuthenticatedLayout>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-5xl space-y-8 px-6 py-8">
          <Head title="Admin dashboard" />

          <PageHeader title="Admin dashboard" />

          <Attention attention={attention} />

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
                            <span className="inline-block rounded-full bg-danger-light px-2.5 py-1 text-xs font-medium text-danger">
                              Out of stock
                            </span>
                          ) : (
                            <span className="font-medium text-warning">{product.stock}</span>
                          )}
                        </td>
                        <td className="p-3 text-right">
                          <Link href={`/admin/products/${product.id}/edit`} className="-my-2 rounded px-2 py-2.5 text-primary hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
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
                        <td className="p-3 text-ink-muted">{formatDate(order.created_at)}</td>
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
