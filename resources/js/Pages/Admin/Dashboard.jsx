import ChartCard, { DataTable } from '@/Components/Charts/ChartCard';
import RevenueChart from '@/Components/Charts/RevenueChart';
import StatTile from '@/Components/Charts/StatTile';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate, formatDay } from '@/utils/date';
import { formatMoney, formatMoneyWhole, formatNumber } from '@/utils/money';
import { Head, Link } from '@inertiajs/react';

const focusRing = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary';

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

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

function LowStock({ products }) {
  return (
    <section className="min-w-0 rounded-lg border border-stroke bg-surface p-4 sm:p-5">
      <h2 className="font-display text-lg font-bold leading-tight text-ink">Low stock</h2>
      <p className="mt-0.5 text-sm text-ink-muted">Five or fewer left</p>

      {products.length === 0 ? (
        <p className="mt-4 text-sm text-ink-muted">Nothing is running low.</p>
      ) : (
        <ul className="mt-3 divide-y divide-stroke">
          {products.map((product) => (
            <li key={product.id} className="flex items-center gap-3 py-3">
              <span className="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-canvas">
                {product.image_url ? (
                  <img src={product.image_url} alt="" className="h-full w-full object-cover" />
                ) : (
                  <span className="flex h-full w-full items-center justify-center text-ink-muted/40">
                    <BasketIcon className="h-5 w-5" />
                  </span>
                )}
              </span>

              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium text-ink">{product.name}</div>
                {product.stock === 0 ? (
                  <span className="inline-block rounded-full bg-danger-light px-2 py-0.5 text-xs font-medium text-danger">
                    Out of stock
                  </span>
                ) : (
                  <span className="text-sm font-medium text-warning">{product.stock} left</span>
                )}
              </div>

              <Link
                href={`/admin/products/${product.id}/edit`}
                className={`shrink-0 rounded px-2 py-2 text-sm font-medium text-primary hover:text-primary-hover ${focusRing}`}
              >
                Restock
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function RecentOrders({ orders }) {
  return (
    <section>
      <h2 className="mb-3 font-display text-lg font-bold text-ink">Recent orders</h2>

      {orders.length === 0 ? (
        <p className="text-sm text-ink-muted">No orders yet.</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-stroke bg-surface">
          <table className="w-full min-w-[560px] text-sm">
            <thead>
              <tr className="border-b border-stroke text-left text-ink-muted">
                <th className="p-3 font-medium">Order</th>
                <th className="p-3 font-medium">Customer</th>
                <th className="p-3 text-right font-medium">Total</th>
                <th className="p-3 font-medium">Status</th>
                <th className="p-3 font-medium">Placed</th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order.id} className="border-b border-stroke last:border-b-0 hover:bg-primary-light/30">
                  <td className="p-3 text-ink">#{order.id}</td>
                  <td className="p-3 text-ink-muted">{order.user?.name ?? '—'}</td>
                  <td className="p-3 text-right font-medium text-ink">{formatMoney(order.total_cents)}</td>
                  <td className="p-3">
                    <OrderStatusBadge status={order.status} />
                  </td>
                  <td className="p-3 text-ink-muted">{formatDate(order.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

export default function Dashboard({ attention, trend, lowStockProducts, recentOrders }) {
  const { current, previous } = trend.kpis;
  const comparedTo = `previous ${trend.days} days`;

  return (
    <AuthenticatedLayout>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-6xl space-y-8 px-6 py-8">
          <Head title="Admin dashboard" />

          <PageHeader title="Admin dashboard" description="How the shop is doing, and what needs you today." />

          <Attention attention={attention} />

          <div className="grid gap-4 sm:grid-cols-3">
            <StatTile
              hero
              label={`Revenue, last ${trend.days} days`}
              value={formatMoneyWhole(current.revenue_cents)}
              current={current.revenue_cents}
              previous={previous.revenue_cents}
              comparedTo={comparedTo}
            />
            <StatTile
              label="Orders placed"
              value={formatNumber(current.orders)}
              current={current.orders}
              previous={previous.orders}
              comparedTo={comparedTo}
            />
            <StatTile
              label="Average order"
              value={formatMoney(current.average_order_cents)}
              current={current.average_order_cents}
              previous={previous.average_order_cents}
              comparedTo={comparedTo}
            />
          </div>

          <div className="grid gap-4 lg:grid-cols-3">
            <ChartCard
              className="lg:col-span-2"
              title="Revenue by day"
              subtitle={`The last ${trend.days} days, against the ${trend.days} before`}
              table={
                <DataTable
                  caption="Revenue and orders for each day"
                  columns={[
                    { key: 'date', label: 'Date' },
                    { key: 'revenue', label: 'Revenue', numeric: true },
                    { key: 'orders', label: 'Orders', numeric: true },
                    { key: 'previous', label: 'Previous period', numeric: true },
                  ]}
                  rows={[...trend.revenueSeries].reverse().map((d) => ({
                    date: formatDay(d.date, { year: true }),
                    revenue: formatMoney(d.revenue_cents),
                    orders: formatNumber(d.orders),
                    previous: formatMoney(d.previous_revenue_cents),
                  }))}
                />
              }
            >
              <RevenueChart series={trend.revenueSeries} />
            </ChartCard>

            <LowStock products={lowStockProducts} />
          </div>

          <RecentOrders orders={recentOrders} />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
