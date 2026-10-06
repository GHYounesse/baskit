import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import Pagination from '@/Components/Pagination';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

function EmptyOrders() {
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-stroke py-20 text-center">
      <BasketIcon className="h-8 w-8 text-ink-muted/50" />
      <div className="font-medium text-ink">No orders yet</div>
    </div>
  );
}

export default function Index({ orders }) {
  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">Manage orders</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-5xl px-6 py-8">
          <Head title="Manage orders" />

          {orders.data.length === 0 ? (
            <EmptyOrders />
          ) : (
            <div className="overflow-x-auto rounded-lg border border-stroke bg-surface">
              <table className="w-full min-w-[640px] text-sm">
                <thead>
                  <tr className="border-b border-stroke text-left text-ink-muted">
                    <th className="p-3 font-medium">Order</th>
                    <th className="p-3 font-medium">Customer</th>
                    <th className="p-3 font-medium text-right">Total</th>
                    <th className="p-3 font-medium">Status</th>
                    <th className="p-3 font-medium">Placed</th>
                    <th className="p-3"></th>
                  </tr>
                </thead>
                <tbody>
                  {orders.data.map((order) => (
                    <tr key={order.id} className="border-b border-stroke last:border-b-0 hover:bg-primary-light/30">
                      <td className="p-3 font-medium text-ink">#{order.id}</td>
                      <td className="p-3 text-ink-muted">
                        {order.user.name} <span className="text-ink-muted/70">({order.user.email})</span>
                      </td>
                      <td className="p-3 text-right font-medium text-ink">{formatMoney(order.total_cents)}</td>
                      <td className="p-3">
                        <OrderStatusBadge status={order.status} />
                      </td>
                      <td className="p-3 text-ink-muted">{formatDate(order.created_at)}</td>
                      <td className="p-3 text-right">
                        <Link
                          href={`/admin/orders/${order.id}`}
                          className="-my-2 rounded px-2 py-2.5 text-primary hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                          Manage
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <Pagination links={orders.links} />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
