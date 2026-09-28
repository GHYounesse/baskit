import ShopLayout from '@/Layouts/ShopLayout';
import { formatMoney } from '@/utils/money';
import { Head, Link, router, usePage } from '@inertiajs/react';

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
        className="flex h-8 w-8 items-center justify-center rounded-full text-ink-muted transition hover:bg-primary-light hover:text-primary disabled:opacity-30"
      >
        −
      </button>
      <span className="w-8 text-center text-sm font-medium text-ink">{item.quantity}</span>
      <button
        type="button"
        onClick={() => setQuantity(item.quantity + 1)}
        aria-label="Increase quantity"
        className="flex h-8 w-8 items-center justify-center rounded-full text-ink-muted transition hover:bg-primary-light hover:text-primary"
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
        <Link href={`/products/${item.product.slug}`} className="truncate font-medium text-ink hover:text-primary">
          {item.product.name}
        </Link>
        <div className="text-sm text-ink-muted">{formatMoney(item.product.price_cents)} each</div>
        <div className="mt-2 flex items-center gap-3">
          <QuantityStepper item={item} />
          <button
            type="button"
            onClick={() => router.delete(`/cart/${item.id}`, { preserveScroll: true })}
            className="text-sm text-ink-muted transition hover:text-red-600"
          >
            Remove
          </button>
        </div>
      </div>

      <div className="font-semibold text-ink">{formatMoney(lineTotal)}</div>
    </div>
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

export default function Index({ cart }) {
  const { auth } = usePage().props;
  const total = cart.items.reduce((sum, i) => sum + i.quantity * i.product.price_cents, 0);

  return (
    <div className="bg-canvas text-ink">
      <div className="mx-auto max-w-4xl px-6 py-8">
        <Head title="Your cart" />

        <h1 className="font-display text-2xl font-bold text-ink">Your cart</h1>

        {cart.items.length === 0 ? (
          <div className="mt-6">
            <EmptyCart />
          </div>
        ) : (
          <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div className="rounded-lg border border-stroke bg-surface px-4 lg:col-span-2">
              {cart.items.map((item) => (
                <CartRow key={item.id} item={item} />
              ))}
            </div>

            <div className="h-fit rounded-lg border border-stroke bg-surface p-4">
              <div className="font-medium text-ink">Order summary</div>
              <div className="mt-3 flex items-center justify-between text-sm text-ink-muted">
                <span>Subtotal</span>
                <span>{formatMoney(total)}</span>
              </div>
              <div className="mt-2 flex items-center justify-between border-t border-stroke pt-2 font-semibold text-ink">
                <span>Total</span>
                <span>{formatMoney(total)}</span>
              </div>
              <Link
                href="/checkout"
                className="mt-4 block rounded-lg bg-primary py-2.5 text-center text-sm font-semibold text-white transition hover:bg-primary-hover"
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
