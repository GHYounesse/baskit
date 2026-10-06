import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import OrderTimeline from '@/Components/OrderTimeline';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const CARRIERS = ['UPS', 'FedEx', 'DHL', 'USPS', 'Royal Mail', 'La Poste'];

const inputClass =
  'w-full rounded-lg border-stroke bg-surface px-3 py-2.5 text-base text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary sm:py-2 sm:text-sm';

function Card({ title, children }) {
  return (
    <section className="rounded-lg border border-stroke bg-surface p-5">
      {title && <h2 className="mb-3 font-display text-lg font-bold text-ink">{title}</h2>}
      {children}
    </section>
  );
}

function ShipForm({ order }) {
  const { data, setData, post, processing, errors } = useForm({ tracking_carrier: '', tracking_number: '' });

  const submit = (e) => {
    e.preventDefault();
    post(`/admin/orders/${order.id}/ship`, { preserveScroll: true });
  };

  return (
    <form onSubmit={submit} className="space-y-3">
      <label className="block">
        <span className="mb-1 block text-sm font-medium text-ink">Carrier (optional)</span>
        <input
          list="carriers"
          value={data.tracking_carrier}
          onChange={(e) => setData('tracking_carrier', e.target.value)}
          className={inputClass}
          placeholder="UPS"
        />
        <datalist id="carriers">
          {CARRIERS.map((c) => (
            <option key={c} value={c} />
          ))}
        </datalist>
        {errors.tracking_carrier && <span className="mt-1 block text-sm text-red-600">{errors.tracking_carrier}</span>}
      </label>

      <label className="block">
        <span className="mb-1 block text-sm font-medium text-ink">Tracking number (optional)</span>
        <input
          value={data.tracking_number}
          onChange={(e) => setData('tracking_number', e.target.value)}
          className={inputClass}
          autoComplete="off"
        />
        {errors.tracking_number && <span className="mt-1 block text-sm text-red-600">{errors.tracking_number}</span>}
      </label>

      <button
        disabled={processing}
        className="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60"
      >
        {processing ? 'Saving…' : 'Mark as shipped'}
      </button>
    </form>
  );
}

function RefundDialog({ order, show, onClose }) {
  const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ reason: '', restock: true });
  const cancelling = order.status === 'paid';

  const close = () => {
    if (processing) return;
    reset();
    clearErrors();
    onClose();
  };

  const submit = (e) => {
    e.preventDefault();
    post(`/admin/orders/${order.id}/refund`, {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        onClose();
      },
    });
  };

  return (
    <Modal show={show} onClose={close} closeable={!processing} maxWidth="md">
      <form onSubmit={submit} className="p-6">
        <h2 className="font-display text-lg font-bold text-ink">Refund order #{order.id}?</h2>
        <p className="mt-2 text-sm text-ink-muted">
          {formatMoney(order.total_cents)} goes back to the customer's card through Stripe. This can't be undone.
          {cancelling && " The order hasn't shipped, so it will be marked as cancelled."}
        </p>

        <label className="mt-4 block">
          <span className="mb-1 block text-sm font-medium text-ink">Reason (optional)</span>
          <textarea
            rows={3}
            value={data.reason}
            onChange={(e) => setData('reason', e.target.value)}
            placeholder="Shown in the order timeline"
            className={inputClass}
          />
          {errors.reason && <span className="mt-1 block text-sm text-red-600">{errors.reason}</span>}
        </label>

        <label className="mt-4 flex items-center gap-2 text-sm text-ink">
          <input
            type="checkbox"
            checked={data.restock}
            onChange={(e) => setData('restock', e.target.checked)}
            className="rounded border-stroke text-primary focus:ring-primary"
          />
          Return the items to stock
        </label>

        {errors.order && (
          <p role="alert" className="mt-4 rounded-lg bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-300">
            {errors.order}
          </p>
        )}

        <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <SecondaryButton onClick={close} disabled={processing}>
            Cancel
          </SecondaryButton>
          <DangerButton type="submit" disabled={processing}>
            {processing ? 'Refunding…' : `Refund ${formatMoney(order.total_cents)}`}
          </DangerButton>
        </div>
      </form>
    </Modal>
  );
}

function ActionsCard({ order, actions }) {
  const [refunding, setRefunding] = useState(false);
  const [delivering, setDelivering] = useState(false);

  const markDelivered = () => {
    setDelivering(true);
    router.post(`/admin/orders/${order.id}/deliver`, {}, {
      preserveScroll: true,
      onFinish: () => setDelivering(false),
    });
  };

  const none = !actions.ship && !actions.deliver && !actions.refund;

  return (
    <Card title="Actions">
      {none && <p className="text-sm text-ink-muted">No further actions for this order.</p>}

      <div className="space-y-5">
        {actions.ship && <ShipForm order={order} />}

        {actions.deliver && (
          <div>
            {order.tracking_number && (
              <p className="mb-3 text-sm text-ink-muted">
                {order.tracking_carrier ?? 'Tracking'}: <span className="text-ink">{order.tracking_number}</span>
              </p>
            )}
            <button
              type="button"
              onClick={markDelivered}
              disabled={delivering}
              className="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60"
            >
              {delivering ? 'Saving…' : 'Mark as delivered'}
            </button>
          </div>
        )}

        {actions.refund && (
          <div className={actions.ship || actions.deliver ? 'border-t border-stroke pt-5' : ''}>
            <button
              type="button"
              onClick={() => setRefunding(true)}
              className="w-full rounded-lg border border-red-600 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:hover:bg-red-950"
            >
              Refund {formatMoney(order.total_cents)}
            </button>
          </div>
        )}
      </div>

      <RefundDialog order={order} show={refunding} onClose={() => setRefunding(false)} />
    </Card>
  );
}

export default function Show({ order, timeline, actions }) {
  const address = order.shipping_address;

  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-wrap items-center gap-3">
          <h2 className="font-display text-2xl font-bold text-ink">Order #{order.id}</h2>
          <OrderStatusBadge status={order.status} />
        </div>
      }
    >
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-5xl px-6 py-8">
          <Head title={`Order #${order.id}`} />

          <Link
            href="/admin/orders"
            className="-ml-2 mb-4 inline-block rounded px-2 py-2 text-sm text-ink-muted transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          >
            ← All orders
          </Link>

          <div className="grid gap-6 lg:grid-cols-3">
            <div className="space-y-6 lg:col-span-2">
              <Card title="Items">
                <div className="divide-y divide-stroke text-sm">
                  {order.items.map((item) => (
                    <div key={item.id} className="flex justify-between gap-3 py-2 first:pt-0">
                      <span className="text-ink">
                        {item.display_name} <span className="text-ink-muted">× {item.quantity}</span>
                        {item.sku && <span className="block text-xs text-ink-muted">SKU {item.sku}</span>}
                      </span>
                      <span className="shrink-0 text-ink">{formatMoney(item.unit_price_cents * item.quantity)}</span>
                    </div>
                  ))}
                </div>

                <div className="mt-3 space-y-1.5 border-t border-stroke pt-3 text-sm">
                  {order.discount_cents > 0 && (
                    <>
                      <div className="flex justify-between text-ink-muted">
                        <span>Subtotal</span>
                        <span>{formatMoney(order.subtotal_cents)}</span>
                      </div>
                      <div className="flex justify-between text-primary">
                        <span>Discount{order.coupon_code ? ` (${order.coupon_code})` : ''}</span>
                        <span>−{formatMoney(order.discount_cents)}</span>
                      </div>
                    </>
                  )}
                  <div className="flex justify-between font-semibold text-ink">
                    <span>Total paid</span>
                    <span>{formatMoney(order.total_cents)}</span>
                  </div>
                </div>
              </Card>

              <div className="grid gap-6 sm:grid-cols-2">
                <Card title="Customer">
                  <div className="text-sm">
                    <div className="font-medium text-ink">{order.user.name}</div>
                    <a href={`mailto:${order.user.email}`} className="text-primary hover:text-primary-hover">
                      {order.user.email}
                    </a>
                  </div>
                  <div className="mt-3 text-xs text-ink-muted">Placed {formatDateTime(order.created_at)}</div>
                </Card>

                <Card title="Shipping address">
                  {address ? (
                    <div className="text-sm text-ink-muted">
                      <div>{address.line1}</div>
                      {address.line2 && <div>{address.line2}</div>}
                      <div>{[address.city, address.state, address.postal_code].filter(Boolean).join(', ')}</div>
                      <div>{address.country}</div>
                    </div>
                  ) : (
                    <p className="text-sm text-ink-muted">No address on file.</p>
                  )}
                </Card>
              </div>

              <Card title="Payment">
                <dl className="space-y-1.5 text-sm">
                  <div className="flex flex-wrap justify-between gap-x-4">
                    <dt className="text-ink-muted">Stripe payment</dt>
                    <dd className="break-all text-ink">{order.stripe_payment_intent_id ?? '—'}</dd>
                  </div>
                  {order.stripe_refund_id && (
                    <div className="flex flex-wrap justify-between gap-x-4">
                      <dt className="text-ink-muted">Stripe refund</dt>
                      <dd className="break-all text-ink">{order.stripe_refund_id}</dd>
                    </div>
                  )}
                </dl>
              </Card>
            </div>

            <div className="space-y-6">
              <ActionsCard order={order} actions={actions} />

              <Card title="Timeline">
                <OrderTimeline timeline={timeline} />
              </Card>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
