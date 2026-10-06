import ProductCard from '@/Components/ProductCard';
import ProductReviews from '@/Components/ProductReviews';
import { StarRating } from '@/Components/StarRating';
import WishlistButton from '@/Components/WishlistButton';
import ShopLayout from '@/Layouts/ShopLayout';
import VariantPicker, { missingChoice, resolveVariant } from '@/Components/VariantPicker';
import { formatMoney } from '@/utils/money';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

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

export default function Show({ product, related = [], ratings, reviews, myReview, canReview }) {
  const outOfStock = product.stock <= 0;

  // Sizes and colours: nothing can be added until each option is chosen.
  const variants = product.variants ?? [];
  const hasVariants = variants.length > 0;
  const [selection, setSelection] = useState(() => {
    // One variant: nothing to choose.
    const only = variants.length === 1 && variants[0].stock > 0 ? variants[0] : null;

    return { size: only?.size || null, color: only?.color || null };
  });
  const selected = hasVariants ? resolveVariant(variants, selection) : null;
  const missing = hasVariants && !selected ? missingChoice(variants, selection) : null;
  const available = hasVariants ? selected?.stock ?? 0 : product.stock;

  const { data, setData, post, processing, errors } = useForm({
    product_id: product.id,
    variant_id: selected?.id ?? null,
    quantity: 1,
  });

  const choose = (next) => {
    const variant = resolveVariant(variants, next);

    setSelection(next);
    setData((prev) => ({
      ...prev,
      variant_id: variant?.id ?? null,
      quantity: variant ? Math.max(1, Math.min(prev.quantity, variant.stock)) : prev.quantity,
    }));
  };

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

            {ratings.count > 0 && (
              <a
                href="#reviews"
                className="mt-2 inline-flex items-center gap-2 rounded text-sm text-ink-muted transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
              >
                <StarRating value={ratings.average} />
                <span>
                  {ratings.average.toFixed(1)} · {ratings.count} {ratings.count === 1 ? 'review' : 'reviews'}
                </span>
              </a>
            )}

            <div className="mt-3 flex items-baseline gap-4">
              <span className="font-display text-3xl font-bold text-ink">
                {formatMoney(product.price_cents)}
              </span>
              {hasVariants && !selected && !outOfStock ? (
                <span className="text-sm text-ink-muted">Choose options to see availability</span>
              ) : (
                <StockStatus stock={hasVariants ? available : product.stock} />
              )}
            </div>
            {selected && <p className="mt-1 text-xs text-ink-muted">SKU {selected.sku}</p>}

            <form
              onSubmit={submit}
              className="mt-6 rounded-lg border border-stroke bg-surface p-5"
            >
              {outOfStock ? (
                <>
                  <p className="font-medium text-ink">This one is out of stock right now.</p>
                  <div className="mt-4 flex flex-wrap items-center gap-4">
                    <WishlistButton product={product} variant="full" />
                    <Link href="/" className="text-sm font-medium text-primary hover:text-primary-hover">
                      Keep shopping
                    </Link>
                  </div>
                </>
              ) : (
                <>
                  {hasVariants && (
                    <div className="mb-5 border-b border-stroke pb-5">
                      <VariantPicker variants={variants} selection={selection} onChange={choose} error={errors.variant_id} />
                    </div>
                  )}

                  <div className="flex items-center justify-between gap-4">
                    <span className="text-sm font-medium text-ink-muted">Quantity</span>
                    <QuantityStepper
                      value={data.quantity}
                      max={Math.max(available, 1)}
                      onChange={(q) => setData('quantity', q)}
                      disabled={processing || Boolean(missing)}
                    />
                  </div>

                  <div className="mt-4 flex gap-3">
                    <button
                      type="submit"
                      disabled={processing || Boolean(missing)}
                      className="flex h-12 flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-5 font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60"
                    >
                      <BasketIcon className="h-5 w-5" />
                      {missing ?? (processing ? 'Adding...' : `Add to cart · ${formatMoney(lineTotal)}`)}
                    </button>
                    <WishlistButton product={product} variant="square" />
                  </div>

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

        <ProductReviews
          product={product}
          ratings={ratings}
          reviews={reviews}
          myReview={myReview}
          canReview={canReview}
        />

        {related.length > 0 && (
          <section className="mt-16" aria-labelledby="related-heading">
            <h2 id="related-heading" className="font-display text-xl font-bold text-ink">
              More from {product.category?.name}
            </h2>
            <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
              {related.map((p) => (
                <ProductCard key={p.id} product={p} showCategory={false} />
              ))}
            </div>
          </section>
        )}
      </div>
    </div>
  );
}

Show.layout = (page) => <ShopLayout>{page}</ShopLayout>;
