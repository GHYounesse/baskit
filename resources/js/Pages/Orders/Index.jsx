import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
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
      <div className="font-medium text-ink">You haven't placed any orders yet</div>
      <Link href="/" className="text-sm font-medium text-primary hover:text-primary-hover">
        Browse the shop
      </Link>
    </div>
  );
}

export default function Index({ orders }) {
  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">My orders</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-3xl px-6 py-8">
          <Head title="My orders" />

          {orders.length === 0 ? (
            <EmptyOrders />
          ) : (
            <div className="space-y-3">
              {orders.map((order) => (
                <Link
                  key={order.id}
                  href={`/orders/${order.id}`}
                  className="flex items-center justify-between rounded-lg border border-stroke bg-surface p-4 transition hover:border-primary"
                >
                  <div>
                    <div className="font-medium text-ink">Order #{order.id}</div>
                    <div className="text-sm text-ink-muted">
                      {new Date(order.created_at).toLocaleDateString()} · {order.items.length} item(s)
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <OrderStatusBadge status={order.status} />
                    <span className="font-semibold text-ink">{formatMoney(order.total_cents)}</span>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
