import ConfirmDialog from '@/Components/ConfirmDialog';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import OrderTimeline from '@/Components/OrderTimeline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ order, timeline, canCancel }) {
  const [confirmingCancel, setConfirmingCancel] = useState(false);
  const [cancelling, setCancelling] = useState(false);

  const cancelOrder = () => {
    setCancelling(true);
    router.post(`/orders/${order.id}/cancel`, {}, {
      preserveScroll: true,
      onFinish: () => {
        setCancelling(false);
        setConfirmingCancel(false);
      },
    });
  };

  const refunded = order.status === 'cancelled' || order.status === 'refunded';

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
                  {formatDate(order.created_at)}
                </div>
              </div>
            </div>

            <div className="mt-6 space-y-2 border-t border-stroke pt-4">
              {order.items.map((item) => (
                <div key={item.id} className="flex justify-between gap-3 text-sm">
                  <span className="text-ink-muted">
                    {item.display_name} <span className="text-ink-muted/70">× {item.quantity}</span>
                  </span>
                  <span className="shrink-0 text-ink">
                    {formatMoney(item.unit_price_cents * item.quantity)}
                  </span>
                </div>
              ))}
            </div>

            {order.discount_cents > 0 && (
              <div className="mt-3 space-y-2 border-t border-stroke pt-3 text-sm">
                <div className="flex justify-between text-ink-muted">
                  <span>Subtotal</span>
                  <span>{formatMoney(order.subtotal_cents)}</span>
                </div>
                <div className="flex justify-between text-primary">
                  <span>Discount{order.coupon_code ? ` (${order.coupon_code})` : ''}</span>
                  <span>−{formatMoney(order.discount_cents)}</span>
                </div>
              </div>
            )}

            <div className="mt-3 flex items-center justify-between border-t border-stroke pt-3 font-semibold text-ink">
              <span>{refunded ? 'Total (refunded)' : 'Total'}</span>
              <span className={refunded ? 'line-through decoration-ink-muted' : ''}>{formatMoney(order.total_cents)}</span>
            </div>
          </div>

          {(order.tracking_number || order.tracking_carrier) && (
            <div className="mt-6 rounded-lg border border-stroke bg-surface p-5">
              <div className="font-medium text-ink">Tracking</div>
              <div className="mt-2 text-sm text-ink-muted">
                {order.tracking_carrier && <div>{order.tracking_carrier}</div>}
                {order.tracking_number && (
                  <div>
                    Tracking number <span className="font-medium text-ink">{order.tracking_number}</span>
                  </div>
                )}
              </div>
            </div>
          )}

          <div className="mt-6 rounded-lg border border-stroke bg-surface p-5">
            <div className="mb-4 font-medium text-ink">Order timeline</div>
            <OrderTimeline timeline={timeline} />

            {canCancel && (
              <div className="mt-6 border-t border-stroke pt-4">
                <p className="text-sm text-ink-muted">Changed your mind? You can cancel until the order ships.</p>
                <button
                  type="button"
                  onClick={() => setConfirmingCancel(true)}
                  className="mt-3 rounded-lg border border-red-600 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:hover:bg-red-950"
                >
                  Cancel order
                </button>
              </div>
            )}
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

      <ConfirmDialog
        show={confirmingCancel}
        title="Cancel this order?"
        confirmLabel="Cancel order"
        cancelLabel="Keep order"
        processingLabel="Cancelling…"
        processing={cancelling}
        onConfirm={cancelOrder}
        onCancel={() => setConfirmingCancel(false)}
      >
        We'll refund {formatMoney(order.total_cents)} to your original payment method, usually within 5 to 10
        business days. This can't be undone.
      </ConfirmDialog>
    </AuthenticatedLayout>
  );
}
