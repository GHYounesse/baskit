import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import PageHeader from '@/Components/PageHeader';
import OrderStatusBadge from '@/Components/OrderStatusBadge';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';

const MAX_THUMBS = 3;

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
      <Link href="/shop" className="text-sm font-medium text-primary hover:text-primary-hover">
        Browse the shop
      </Link>
    </div>
  );
}

// Overlapping photos of what was in the order, like the category rows on the home page.
function Thumbs({ items }) {
  const shown = items.slice(0, MAX_THUMBS);
  const extra = items.length - shown.length;

  return (
    <span className="flex shrink-0 -space-x-3" aria-hidden="true">
      {shown.map((item) =>
        item.product?.image_url ? (
          <img
            key={item.id}
            src={item.product.image_url}
            alt=""
            className="h-14 w-14 rounded-lg bg-canvas object-cover ring-2 ring-surface"
          />
        ) : (
          <span
            key={item.id}
            className="flex h-14 w-14 items-center justify-center rounded-lg bg-canvas text-ink-muted/50 ring-2 ring-surface"
          >
            <BasketIcon className="h-6 w-6" />
          </span>
        ),
      )}
      {extra > 0 && (
        <span className="flex h-14 w-14 items-center justify-center rounded-lg bg-stroke text-sm font-semibold text-ink ring-2 ring-surface">
          +{extra}
        </span>
      )}
    </span>
  );
}

// "Cast Iron Skillet", or "Cast Iron Skillet and 2 more".
function summary(items) {
  if (items.length === 0) return 'No items';
  if (items.length === 1) return items[0].display_name;

  return `${items[0].display_name} and ${items.length - 1} more`;
}

export default function Index({ orders }) {
  return (
    <>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-3xl px-6 py-8">
          <Head title="My orders" />

          <PageHeader title="My orders" />

          {orders.length === 0 ? (
            <EmptyOrders />
          ) : (
            <div className="space-y-3">
              {orders.map((order) => (
                <Link
                  key={order.id}
                  href={`/orders/${order.id}`}
                  className="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg border border-stroke bg-surface p-4 transition hover:border-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                  <Thumbs items={order.items} />

                  <div className="min-w-0 flex-1 basis-40">
                    <div className="font-medium text-ink">Order #{order.id}</div>
                    <div className="truncate text-sm text-ink">{summary(order.items)}</div>
                    <div className="text-sm text-ink-muted">{formatDate(order.created_at)}</div>
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
    </>
  );
}

Index.layout = (page) => <ShopLayout>{page}</ShopLayout>;
