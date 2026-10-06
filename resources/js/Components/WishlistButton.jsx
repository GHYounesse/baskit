import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function HeartIcon({ filled, ...props }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill={filled ? 'currentColor' : 'none'}
      stroke="currentColor"
      strokeWidth="1.75"
      aria-hidden="true"
      {...props}
    >
      <path
        d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"
        strokeLinejoin="round"
      />
    </svg>
  );
}

/**
 * Heart toggle for saving a product. Guests get a link to log in instead,
 * since a wishlist belongs to an account.
 *
 * variant "icon": small round button for sitting on a product image.
 * variant "square": icon-only, sized to sit beside a 48px button.
 * variant "full": larger button with a text label.
 */
export default function WishlistButton({ product, variant = 'icon', className = '' }) {
  const { auth, wishlistIds } = usePage().props;
  const [pending, setPending] = useState(false);
  const saved = wishlistIds.includes(product.id);

  const label = auth.user
    ? saved
      ? `Remove ${product.name} from your wishlist`
      : `Save ${product.name} to your wishlist`
    : `Log in to save ${product.name}`;

  const base = {
    icon: 'flex h-10 w-10 items-center justify-center rounded-full bg-surface/90 shadow-sm ring-1 ring-inset ring-stroke backdrop-blur-sm sm:h-9 sm:w-9',
    square: 'flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-stroke bg-surface',
    full: 'flex h-12 items-center justify-center gap-2 rounded-lg border border-stroke bg-surface px-4 text-sm font-semibold',
  }[variant];

  const classes = `${base} transition hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary ${
    saved ? 'text-red-500' : 'text-ink-muted hover:text-ink'
  } ${className}`;

  const icon = <HeartIcon filled={saved} className="h-5 w-5" />;
  const text = variant === 'full' ? (saved ? 'Saved' : 'Save for later') : null;

  if (!auth.user) {
    return (
      <Link href="/login" aria-label={label} title="Log in to save" className={classes}>
        {icon}
        {text}
      </Link>
    );
  }

  const toggle = () => {
    setPending(true);
    const options = { preserveScroll: true, preserveState: true, onFinish: () => setPending(false) };

    saved ? router.delete(`/wishlist/${product.id}`, options) : router.post(`/wishlist/${product.id}`, {}, options);
  };

  return (
    <button
      type="button"
      onClick={toggle}
      disabled={pending}
      aria-pressed={saved}
      aria-label={label}
      title={saved ? 'Remove from wishlist' : 'Save to wishlist'}
      className={classes}
    >
      {icon}
      {text}
    </button>
  );
}
