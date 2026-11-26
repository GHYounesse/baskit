import ConfirmDialog from '@/Components/ConfirmDialog';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import OrderTimeline from '@/Components/OrderTimeline';
import ShopLayout from '@/Layouts/ShopLayout';
import PageHeader from '@/Components/PageHeader';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

// One line of the order: the photo and name link back to the product while it still exists.
function OrderLine({ item }) {
  const product = item.product;

  const photo = product?.image_url ? (
    <img src={product.image_url} alt="" className="h-full w-full object-cover" />
  ) : (
    <span className="flex h-full w-full items-center justify-center text-ink-muted/40">
      <BasketIcon className="h-6 w-6" />
    </span>
  );

  return (
    <li className="flex items-center gap-4 py-4">
      {product ? (
        <Link
          href={`/products/${product.slug}`}
          tabIndex={-1}
          aria-hidden="true"
          className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-canvas"
        >
          {photo}
        </Link>
      ) : (
        <span className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-canvas">{photo}</span>
      )}

      <div className="min-w-0 flex-1">
        {product ? (
          <Link href={`/products/${product.slug}`} className="block font-medium text-ink hover:text-primary">
            {item.display_name}
          </Link>
        ) : (
          <span className="block font-medium text-ink">{item.display_name}</span>
        )}
        <div className="text-sm text-ink-muted">
          {formatMoney(item.unit_price_cents)} × {item.quantity}
        </div>
      </div>

      <div className="shrink-0 font-semibold text-ink">{formatMoney(item.unit_price_cents * item.quantity)}</div>
    </li>
  );
}

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
    <>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-2xl px-6 py-8">
          <Head title={`Order #${order.id}`} />

          <Link
            href="/orders"
            className="-ml-2 mb-4 inline-block rounded px-2 py-2 text-sm text-ink-muted transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          >
            ← My orders
          </Link>

          <PageHeader title={`Order #${order.id}`} />

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

            <ul className="mt-6 divide-y divide-stroke border-t border-stroke">
              {order.items.map((item) => (
                <OrderLine key={item.id} item={item} />
              ))}
            </ul>

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
                  className="mt-3 rounded-lg border border-danger px-4 py-2.5 text-sm font-semibold text-danger transition hover:bg-danger-light focus:outline-none focus-visible:ring-2 focus-visible:ring-danger dark:hover:bg-red-950"
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
    </>
  );
}

Show.layout = (page) => <ShopLayout>{page}</ShopLayout>;
