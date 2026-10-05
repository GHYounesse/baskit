import ShopLayout from '@/Layouts/ShopLayout';
import { formatMoney } from '@/utils/money';
import { Head, Link, useForm } from '@inertiajs/react';

const LOW_STOCK_THRESHOLD = 5;

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

function ChevronLeft(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.75" {...props}>
      <path d="M12 5l-5 5 5 5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function LockIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <rect x="4" y="9" width="12" height="8" rx="2" />
      <path d="M7 9V6.5a3 3 0 016 0V9" strokeLinecap="round" />
    </svg>
  );
}

function StockStatus({ stock }) {
  if (stock <= 0) {
    return (
      <span className="inline-flex items-center gap-2 text-sm font-medium text-ink-muted">
        <span className="h-2 w-2 rounded-full bg-ink-muted/50" aria-hidden="true" />
        Out of stock
      </span>
    );
  }

  const low = stock <= LOW_STOCK_THRESHOLD;

  return (
    <span className="inline-flex items-center gap-2 text-sm font-medium text-ink">
      <span
        className={`h-2 w-2 rounded-full ${low ? 'bg-amber-500' : 'bg-primary'}`}
        aria-hidden="true"
      />
      {low ? `Only ${stock} left` : 'In stock'}
    </span>
  );
}

function QuantityStepper({ value, max, onChange, disabled }) {
  const step = (delta) => onChange(Math.min(max, Math.max(1, value + delta)));

  const button =
    'flex h-11 w-11 items-center justify-center text-lg text-ink transition hover:bg-primary-light disabled:cursor-not-allowed disabled:text-ink-muted/40 disabled:hover:bg-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary';

  return (
    <div
      role="group"
      aria-label="Quantity"
      className="inline-flex items-center overflow-hidden rounded-lg border border-stroke bg-surface"
    >
      <button
        type="button"
        onClick={() => step(-1)}
        disabled={disabled || value <= 1}
        aria-label="Decrease quantity"
        className={button}
      >
        −
      </button>
      <input
        type="number"
        min="1"
        max={max}
        value={value}
        disabled={disabled}
        aria-label="Quantity"
        onChange={(e) => {
          const n = parseInt(e.target.value, 10);
          onChange(Number.isNaN(n) ? 1 : Math.min(max, Math.max(1, n)));
        }}
        className="h-11 w-12 border-0 bg-transparent p-0 text-center text-base font-semibold text-ink [appearance:textfield] focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
      />
      <button
        type="button"
        onClick={() => step(1)}
        disabled={disabled || value >= max}
        aria-label="Increase quantity"
        className={button}
      >
        +
      </button>
    </div>
  );
}

function RelatedCard({ product }) {
  const outOfStock = product.stock <= 0;

  return (
    <Link
      href={`/products/${product.slug}`}
      className="group rounded-lg border border-stroke bg-surface p-3 transition hover:border-primary hover:shadow-sm"
    >
      <div className="relative mb-3 aspect-square overflow-hidden rounded-lg bg-canvas">
        {product.image_url ? (
          <img
            src={product.image_url}
            alt={product.name}
            className={`h-full w-full object-cover transition group-hover:scale-[1.03] ${outOfStock ? 'opacity-50' : ''}`}
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-ink-muted/40">
            <BasketIcon className="h-10 w-10" />
          </div>
        )}
      </div>
      <div className="truncate font-medium text-ink">{product.name}</div>
      <div className="mt-0.5 font-semibold text-ink">{formatMoney(product.price_cents)}</div>
    </Link>
  );
}

export default function Show({ product, related = [] }) {
  const outOfStock = product.stock <= 0;

  const { data, setData, post, processing } = useForm({
    product_id: product.id,
    quantity: 1,
  });

  const submit = (e) => {
    e.preventDefault();
    post('/cart', { preserveScroll: true });
  };

  const lineTotal = product.price_cents * data.quantity;

  return (
    // Same full-bleed canvas/ink scoping as the shop index (see ShopLayout).
    <div className="bg-canvas text-ink">
      <Head title={product.name} />

      <div className="mx-auto max-w-6xl px-6 py-8">
        <Link
          href={product.category ? `/?category=${product.category.slug}` : '/'}
          className="inline-flex items-center gap-1 rounded text-sm text-ink-muted transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        >
          <ChevronLeft className="h-4 w-4" />
          {product.category ? product.category.name : 'Shop'}
        </Link>

        <div className="mt-6 grid gap-8 md:grid-cols-2 md:gap-12">
          <div className="md:sticky md:top-6 md:self-start">
            <div className="relative aspect-square overflow-hidden rounded-lg border border-stroke bg-surface">
              {product.image_url ? (
                <img
                  src={product.image_url}
                  alt={product.name}
                  className={`h-full w-full object-cover ${outOfStock ? 'opacity-50' : ''}`}
                />
              ) : (
                <div className="flex h-full w-full flex-col items-center justify-center gap-2 text-ink-muted/50">
                  <BasketIcon className="h-14 w-14" />
                  <span className="text-sm">No image yet</span>
                </div>
              )}
            </div>
          </div>

          <div>
            {product.category && (
              <Link
                href={`/?category=${product.category.slug}`}
                className="inline-block rounded-full bg-primary-light px-3 py-1 text-sm font-medium text-brand transition hover:opacity-80"
              >
                {product.category.name}
              </Link>
            )}

            <h1 className="mt-3 font-display text-3xl font-bold leading-tight text-ink sm:text-4xl">
              {product.name}
            </h1>

            <div className="mt-3 flex items-baseline gap-4">
              <span className="font-display text-3xl font-bold text-ink">
                {formatMoney(product.price_cents)}
              </span>
              <StockStatus stock={product.stock} />
            </div>

            <form
              onSubmit={submit}
              className="mt-6 rounded-lg border border-stroke bg-surface p-5"
            >
              {outOfStock ? (
                <>
                  <p className="font-medium text-ink">This one is out of stock right now.</p>
                  <Link
                    href="/"
                    className="mt-3 inline-block text-sm font-medium text-primary hover:text-primary-hover"
                  >
                    Keep shopping
                  </Link>
                </>
              ) : (
                <>
                  <div className="flex items-center justify-between gap-4">
                    <span className="text-sm font-medium text-ink-muted">Quantity</span>
                    <QuantityStepper
                      value={data.quantity}
                      max={product.stock}
                      onChange={(q) => setData('quantity', q)}
                      disabled={processing}
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={processing}
                    className="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60"
                  >
                    <BasketIcon className="h-5 w-5" />
                    {processing ? 'Adding...' : `Add to cart · ${formatMoney(lineTotal)}`}
                  </button>

                  <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-ink-muted">
                    <LockIcon className="h-3.5 w-3.5" />
                    Secure card payment with Stripe
                  </p>
                </>
              )}
            </form>

            {product.description && (
              <div className="mt-8">
                <h2 className="font-display text-lg font-bold text-ink">About this product</h2>
                <p className="mt-2 max-w-prose whitespace-pre-line leading-relaxed text-ink-muted">
                  {product.description}
                </p>
              </div>
            )}
          </div>
        </div>

        {related.length > 0 && (
          <section className="mt-16" aria-labelledby="related-heading">
            <h2 id="related-heading" className="font-display text-xl font-bold text-ink">
              More from {product.category?.name}
            </h2>
            <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
              {related.map((p) => (
                <RelatedCard key={p.id} product={p} />
              ))}
            </div>
          </section>
        )}
      </div>
    </div>
  );
}

Show.layout = (page) => <ShopLayout>{page}</ShopLayout>;
