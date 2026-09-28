import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import { formatMoney } from '@/utils/money';
import { Head } from '@inertiajs/react';

export default function Show({ order }) {
  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">Order #{order.id}</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-2xl px-6 py-8">
          <Head title={`Order #${order.id}`} />

          <div className="rounded-lg border border-stroke bg-surface p-5">
            <div className="flex items-center justify-between">
              <div>
                <div className="text-sm text-ink-muted">Status</div>
                <div className="mt-1"><OrderStatusBadge status={order.status} /></div>
              </div>
              <div className="text-right">
                <div className="text-sm text-ink-muted">Placed on</div>
                <div className="mt-1 font-medium text-ink">
                  {new Date(order.created_at).toLocaleDateString()}
                </div>
              </div>
            </div>

            <div className="mt-6 space-y-2 border-t border-stroke pt-4">
              {order.items.map((item) => (
                <div key={item.id} className="flex justify-between gap-3 text-sm">
                  <span className="text-ink-muted">
                    {item.product_name} <span className="text-ink-muted/70">× {item.quantity}</span>
                  </span>
                  <span className="shrink-0 text-ink">
                    {formatMoney(item.unit_price_cents * item.quantity)}
                  </span>
                </div>
              ))}
            </div>

            <div className="mt-3 flex items-center justify-between border-t border-stroke pt-3 font-semibold text-ink">
              <span>Total</span>
              <span>{formatMoney(order.total_cents)}</span>
            </div>
          </div>

          {order.shipping_address && (
            <div className="mt-6 rounded-lg border border-stroke bg-surface p-5">
              <div className="font-medium text-ink">Shipping to</div>
              <div className="mt-2 text-sm text-ink-muted">
                <div>{order.shipping_address.line1}</div>
                {order.shipping_address.line2 && <div>{order.shipping_address.line2}</div>}
                <div>
                  {[order.shipping_address.city, order.shipping_address.state, order.shipping_address.postal_code]
                    .filter(Boolean)
                    .join(', ')}
                </div>
                <div>{order.shipping_address.country}</div>
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
