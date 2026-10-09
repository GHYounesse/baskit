import PageHeader from '@/Components/PageHeader';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatMoney } from '@/utils/money';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

function QuantityStepper({ item }) {
  const setQuantity = (quantity) => {
    if (quantity < 1) return;
    router.patch(`/cart/${item.id}`, { quantity }, { preserveScroll: true });
  };

  return (
    <div className="inline-flex items-center rounded-full ring-1 ring-inset ring-stroke">
      <button
        type="button"
        onClick={() => setQuantity(item.quantity - 1)}
        disabled={item.quantity <= 1}
        aria-label="Decrease quantity"
        className="flex h-10 w-10 items-center justify-center rounded-full text-ink-muted transition hover:bg-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-30 sm:h-8 sm:w-8"
      >
        −
      </button>
      <span className="w-8 text-center text-sm font-medium text-ink">{item.quantity}</span>
      <button
        type="button"
        onClick={() => setQuantity(item.quantity + 1)}
        aria-label="Increase quantity"
        className="flex h-10 w-10 items-center justify-center rounded-full text-ink-muted transition hover:bg-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:h-8 sm:w-8"
      >
        +
      </button>
    </div>
  );
}

function CartRow({ item }) {
  const lineTotal = item.quantity * item.product.price_cents;

  return (
    <div className="flex items-center gap-4 border-b border-stroke py-4 last:border-b-0">
      <Link
        href={`/products/${item.product.slug}`}
        className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-canvas"
      >
        {item.product.image_url ? (
          <img src={item.product.image_url} alt={item.product.name} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-ink-muted/40">
            <BasketIcon className="h-6 w-6" />
          </div>
        )}
      </Link>

      <div className="min-w-0 flex-1">
        <Link href={`/products/${item.product.slug}`} className="block truncate font-medium text-ink hover:text-primary">
          {item.product.name}
        </Link>
        {item.variant && <div className="text-sm text-ink">{item.variant.label}</div>}
        <div className="text-sm text-ink-muted">{formatMoney(item.product.price_cents)} each</div>
        <div className="mt-2 flex items-center gap-3">
          <QuantityStepper item={item} />
          <button
            type="button"
            onClick={() => router.delete(`/cart/${item.id}`, { preserveScroll: true })}
            className="-mx-2 rounded px-2 py-2 text-sm text-ink-muted transition hover:text-danger focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          >
            Remove
          </button>
        </div>
      </div>

      <div className="shrink-0 font-semibold text-ink">{formatMoney(lineTotal)}</div>
    </div>
  );
}

function CouponBox({ coupon, error: couponError }) {
  const { data, setData, post, processing, errors, reset } = useForm({ code: '' });

  const apply = (e) => {
    e.preventDefault();
    if (!data.code.trim()) return;
    post('/cart/coupon', { preserveScroll: true, onSuccess: () => reset() });
  };

  if (coupon) {
    return (
      <div className="mt-4 border-t border-stroke pt-4">
        <div className="flex items-center justify-between gap-3 rounded-lg bg-primary-light px-3 py-2">
          <div className="min-w-0 text-sm">
            <span className="font-semibold text-brand">{coupon.code}</span>
            <span className="text-ink-muted"> · {coupon.label}</span>
          </div>
          <button
            type="button"
            onClick={() => router.delete('/cart/coupon', { preserveScroll: true })}
            className="-my-2 -mr-2 shrink-0 rounded px-2 py-2 text-sm text-ink-muted transition hover:text-danger focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          >
            Remove
          </button>
        </div>
        {couponError && <p className="mt-2 text-sm text-danger">{couponError}</p>}
      </div>
    );
  }

  return (
    <form onSubmit={apply} className="mt-4 border-t border-stroke pt-4">
      <label htmlFor="coupon-code" className="text-sm font-medium text-ink">
        Discount code
      </label>
      <div className="mt-1.5 flex gap-2">
        <input
          id="coupon-code"
          value={data.code}
          onChange={(e) => setData('code', e.target.value)}
          autoComplete="off"
          autoCapitalize="characters"
          spellCheck="false"
          aria-invalid={Boolean(errors.code)}
          aria-describedby={errors.code ? 'coupon-error' : undefined}
          className="min-w-0 flex-1 rounded-lg border-stroke bg-surface px-3 py-2.5 text-base uppercase text-ink placeholder:normal-case placeholder:text-ink-muted focus:border-primary focus:ring-primary sm:py-2 sm:text-sm"
          placeholder="Enter code"
        />
        <button
          type="submit"
          disabled={processing || !data.code.trim()}
          className="shrink-0 rounded-lg border border-stroke bg-surface px-4 text-sm font-semibold text-ink transition hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-50"
        >
          Apply
        </button>
      </div>
      {errors.code && (
        <p id="coupon-error" className="mt-2 text-sm text-danger">
          {errors.code}
        </p>
      )}
    </form>
  );
}

function EmptyCart() {
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-stroke py-20 text-center">
      <BasketIcon className="h-8 w-8 text-ink-muted/50" />
      <div className="font-medium text-ink">Your cart is empty</div>
      <Link href="/" className="text-sm font-medium text-primary hover:text-primary-hover">
        Browse the shop
      </Link>
    </div>
  );
}

export default function Index({ cart, pricing }) {
  const { auth } = usePage().props;

  return (
    <div className="bg-canvas text-ink">
      <div className="mx-auto max-w-4xl px-6 py-8">
        <Head title="Your cart" />

        <PageHeader title="Your cart" />

        {cart.items.length === 0 ? (
          <div>
            <EmptyCart />
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div className="rounded-lg border border-stroke bg-surface px-4 lg:col-span-2">
              {cart.items.map((item) => (
                <CartRow key={item.id} item={item} />
              ))}
            </div>

            <div className="h-fit rounded-lg border border-stroke bg-surface p-4">
              <div className="font-medium text-ink">Order summary</div>
              <div className="mt-3 flex items-center justify-between text-sm text-ink-muted">
                <span>Subtotal</span>
                <span>{formatMoney(pricing.subtotal_cents)}</span>
              </div>
              {pricing.discount_cents > 0 && (
                <div className="mt-2 flex items-center justify-between text-sm text-primary">
                  <span>Discount ({pricing.coupon.code})</span>
                  <span>−{formatMoney(pricing.discount_cents)}</span>
                </div>
              )}
              <div className="mt-2 flex items-center justify-between border-t border-stroke pt-2 font-semibold text-ink">
                <span>Total</span>
                <span>{formatMoney(pricing.total_cents)}</span>
              </div>
              <CouponBox coupon={pricing.coupon} error={pricing.coupon_error} />
              <Link
                href="/checkout"
                className="mt-4 block rounded-lg bg-primary py-3 text-center text-sm font-semibold text-primary-on transition hover:bg-primary-hover"
              >
                {auth.user ? 'Checkout' : 'Log in to checkout'}
              </Link>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

Index.layout = (page) => <ShopLayout>{page}</ShopLayout>;
