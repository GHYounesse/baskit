import Pagination from '@/Components/Pagination';
import ProductCard from '@/Components/ProductCard';
import SearchBox from '@/Components/SearchBox';
import PageHeader from '@/Components/PageHeader';
import ShopLayout from '@/Layouts/ShopLayout';
import { Head, Link, router } from '@inertiajs/react';

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
      className={`shrink-0 rounded-full px-4 py-2 text-sm font-medium transition ${
        active === value
          ? 'bg-primary text-primary-on'
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

function EmptyState({ hasFilters, search, correction, onSearch }) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-stroke px-6 py-20 text-center">
      <BasketIcon className="h-8 w-8 text-ink-muted/50" />
      <div className="font-medium text-ink">
        {search ? `No products match “${search}”` : 'No products match your filters'}
      </div>
      {correction && (
        <p className="text-sm text-ink-muted">
          Did you mean{' '}
          <button
            type="button"
            onClick={() => onSearch(correction)}
            className="rounded font-medium text-primary hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          >
            {correction}
          </button>
          ?
        </p>
      )}
      {hasFilters && (
        <Link href="/" className="text-sm font-medium text-primary hover:text-primary-hover">
          Clear filters
        </Link>
      )}
    </div>
  );
}

export default function Index({ products, categories, filters, correction }) {
  const hasFilters = Boolean(filters.search || filters.category);

  const updateQuery = (changes) => {
    router.get('/', { ...filters, ...changes }, { preserveState: true, preserveScroll: true });
  };

  // A new search starts over on "best match" (an old sort choice shouldn't stick).
  const search = (term) => updateQuery({ search: term || undefined, sort: undefined });

  return (
    // Full-bleed canvas/ink scoped to this page only: ShopLayout's <main> is
    // deliberately pinned to a fixed light background because most other
    // pages (Cart, Checkout, Orders) still use hardcoded light-only classes,
    // and letting <main> itself go dark would break those. This page has been
    // fully retokened, so it opts into dark mode on its own.
    <div className="bg-canvas text-ink">
      <div className="mx-auto max-w-6xl px-6 py-8">
        <Head title="Shop" />

        <PageHeader title="Shop" description="Everything for today's basket." />

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <SearchBox
            initialValue={filters.search ?? ''}
            onSearch={search}
            onPickCategory={(slug) => router.get('/', { category: slug })}
          />
          <select
            value={filters.sort ?? 'newest'}
            onChange={(e) => updateQuery({ sort: e.target.value })}
            className="rounded-lg border-stroke bg-surface py-2 text-sm text-ink focus:border-primary focus:ring-primary sm:w-48"
          >
            {filters.search && <option value="relevance">Best match</option>}
            <option value="newest">Newest</option>
            <option value="price_asc">Price: low to high</option>
            <option value="price_desc">Price: high to low</option>
            <option value="rating">Top rated</option>
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
            <EmptyState hasFilters={hasFilters} search={filters.search} correction={correction} onSearch={search} />
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
