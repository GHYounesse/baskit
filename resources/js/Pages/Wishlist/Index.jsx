import ProductCard from '@/Components/ProductCard';
import ShopLayout from '@/Layouts/ShopLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function HeartIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path
        d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function EmptyWishlist() {
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-stroke px-6 py-20 text-center">
      <HeartIcon className="h-8 w-8 text-ink-muted/50" />
      <div className="font-medium text-ink">Nothing saved yet</div>
      <p className="max-w-xs text-sm text-ink-muted">Tap the heart on any product to keep it here for later.</p>
      <Link href="/" className="text-sm font-medium text-primary hover:text-primary-hover">
        Browse the shop
      </Link>
    </div>
  );
}

function AddToCart({ product }) {
  const [adding, setAdding] = useState(false);

  if (product.stock <= 0) {
    return <div className="rounded-lg bg-canvas py-2.5 text-center text-sm text-ink-muted">Out of stock</div>;
  }

  // Sizes and colours have to be chosen on the product page.
  if (product.variants_count > 0) {
    return (
      <Link
        href={`/products/${product.slug}`}
        className="block w-full rounded-lg border border-primary py-2.5 text-center text-sm font-semibold text-primary transition hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
      >
        Choose options
      </Link>
    );
  }

  return (
    <button
      type="button"
      disabled={adding}
      onClick={() => {
        setAdding(true);
        router.post('/cart', { product_id: product.id, quantity: 1 }, {
          preserveScroll: true,
          onFinish: () => setAdding(false),
        });
      }}
      className="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60"
    >
      {adding ? 'Adding...' : 'Add to cart'}
    </button>
  );
}

export default function Index({ products }) {
  return (
    <div className="bg-canvas text-ink">
      <div className="mx-auto max-w-6xl px-6 py-8">
        <Head title="Wishlist" />

        <h1 className="font-display text-2xl font-bold text-ink">Wishlist</h1>
        {products.length > 0 && (
          <p className="mt-1 text-sm text-ink-muted">
            {products.length} saved {products.length === 1 ? 'item' : 'items'}
          </p>
        )}

        {products.length === 0 ? (
          <div className="mt-6">
            <EmptyWishlist />
          </div>
        ) : (
          <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product}>
                <AddToCart product={product} />
              </ProductCard>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

Index.layout = (page) => <ShopLayout>{page}</ShopLayout>;
