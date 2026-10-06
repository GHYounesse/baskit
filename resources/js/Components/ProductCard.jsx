import { StarRating } from '@/Components/StarRating';
import WishlistButton from '@/Components/WishlistButton';
import { formatMoney } from '@/utils/money';
import { Link } from '@inertiajs/react';

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

/**
 * The product tile used by the shop, related products and the wishlist.
 * `children` render under the link (e.g. an "Add to cart" button), so they
 * are not nested inside the anchor.
 */
export default function ProductCard({ product, showCategory = true, children }) {
  const outOfStock = product.stock <= 0;
  const reviewCount = product.reviews_count ?? 0;

  return (
    <div className="group relative flex h-full flex-col rounded-lg border border-stroke bg-surface transition hover:border-primary hover:shadow-sm">
      <Link
        href={`/products/${product.slug}`}
        className="block flex-1 rounded-lg p-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
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
          {outOfStock && (
            <span className="absolute left-2 top-2 rounded-full bg-surface px-2 py-0.5 text-xs font-medium text-ink-muted ring-1 ring-inset ring-stroke">
              Out of stock
            </span>
          )}
        </div>

        {showCategory && product.category && <div className="text-xs text-ink-muted">{product.category.name}</div>}
        <div className="truncate font-medium text-ink">{product.name}</div>

        {reviewCount > 0 && (
          <div className="mt-0.5 flex items-center gap-1.5 text-xs text-ink-muted">
            <StarRating value={product.reviews_avg_rating} size="h-3.5 w-3.5" />
            <span>
              {Number(product.reviews_avg_rating).toFixed(1)} ({reviewCount})
            </span>
          </div>
        )}

        <div className="mt-0.5 font-semibold text-ink">{formatMoney(product.price_cents)}</div>
      </Link>

      <WishlistButton product={product} className="absolute right-5 top-5" />

      {children && <div className="px-3 pb-3">{children}</div>}
    </div>
  );
}
