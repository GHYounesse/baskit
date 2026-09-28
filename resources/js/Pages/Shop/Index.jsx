import Pagination from '@/Components/Pagination';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatMoney } from '@/utils/money';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function SearchIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.75" {...props}>
      <circle cx="9" cy="9" r="6" />
      <path d="M17 17l-4-4" strokeLinecap="round" />
    </svg>
  );
}

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

function CategoryChips({ categories, active, onSelect }) {
  const chip = (value, label) => (
    <button
      key={value}
      type="button"
      onClick={() => onSelect(value)}
      className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition ${
        active === value
          ? 'bg-primary text-white'
          : 'bg-surface text-ink-muted ring-1 ring-inset ring-stroke hover:text-ink'
      }`}
    >
      {label}
    </button>
  );

  return (
    <div className="-mx-6 flex gap-2 overflow-x-auto px-6 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
      {chip('', 'All')}
      {categories.map((c) => chip(c.slug, c.name))}
    </div>
  );
}

function ProductCard({ product }) {
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
        {outOfStock && (
          <span className="absolute left-2 top-2 rounded-full bg-surface px-2 py-0.5 text-xs font-medium text-ink-muted ring-1 ring-inset ring-stroke">
            Out of stock
          </span>
        )}
      </div>

      {product.category && (
        <div className="text-xs text-ink-muted">{product.category.name}</div>
      )}
      <div className="truncate font-medium text-ink">{product.name}</div>
      <div className="mt-0.5 font-semibold text-ink">{formatMoney(product.price_cents)}</div>
    </Link>
  );
}

function EmptyState({ hasFilters }) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-stroke py-20 text-center">
      <BasketIcon className="h-8 w-8 text-ink-muted/50" />
      <div className="font-medium text-ink">No products match your search</div>
      {hasFilters && (
        <Link href="/" className="text-sm font-medium text-primary hover:text-primary-hover">
          Clear filters
        </Link>
      )}
    </div>
  );
}

export default function Index({ products, categories, filters }) {
  const [search, setSearch] = useState(filters.search ?? '');
  const hasFilters = Boolean(filters.search || filters.category);

  const updateQuery = (changes) => {
    router.get('/', { ...filters, ...changes }, { preserveState: true, preserveScroll: true });
  };

  return (
    // Full-bleed canvas/ink scoped to this page only: ShopLayout's <main> is
    // deliberately pinned to a fixed light background because most other
    // pages (Cart, Checkout, Orders) still use hardcoded light-only classes,
    // and letting <main> itself go dark would break those. This page has been
    // fully retokened, so it opts into dark mode on its own.
    <div className="bg-canvas text-ink">
      <div className="mx-auto max-w-6xl px-6 py-8">
        <Head title="Shop" />

        <h1 className="font-display text-2xl font-bold text-ink">Shop</h1>
        <p className="mt-1 text-sm text-ink-muted">Everything for today's basket.</p>

        <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search products..."
              className="w-full rounded-lg border-stroke bg-surface py-2 pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"
              onKeyDown={(e) => {
                if (e.key === 'Enter') updateQuery({ search });
              }}
            />
          </div>
          <select
            value={filters.sort ?? 'newest'}
            onChange={(e) => updateQuery({ sort: e.target.value })}
            className="rounded-lg border-stroke bg-surface py-2 text-sm text-ink focus:border-primary focus:ring-primary sm:w-48"
          >
            <option value="newest">Newest</option>
            <option value="price_asc">Price: low to high</option>
            <option value="price_desc">Price: high to low</option>
            <option value="name">Name: A to Z</option>
          </select>
        </div>

        <div className="mt-4">
          <CategoryChips
            categories={categories}
            active={filters.category ?? ''}
            onSelect={(category) => updateQuery({ category })}
          />
        </div>

        {products.data.length === 0 ? (
          <div className="mt-10">
            <EmptyState hasFilters={hasFilters} />
          </div>
        ) : (
          <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {products.data.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}

        <Pagination links={products.links} />
      </div>
    </div>
  );
}

Index.layout = (page) => <ShopLayout>{page}</ShopLayout>;
