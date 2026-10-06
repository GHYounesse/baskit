import StockChange from '@/Components/Admin/StockChange';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/utils/date';
import { formatNumber } from '@/utils/money';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function CheckIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true" {...props}>
      <path d="M4 10.500l4 4 8-9" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function WarningIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true" {...props}>
      <path d="M10 3l8 14H2L10 3z" strokeLinejoin="round" />
      <path d="M10 8.500v3.500M10 14.500v.01" strokeLinecap="round" />
    </svg>
  );
}

/**
 * The ledger's health check: green when every product's stock matches its
 * history, otherwise the products that don't, with what is wrong.
 */
function IntegrityBanner({ integrity }) {
  if (integrity.ok) {
    return (
      <div role="status" className="flex items-center gap-2 rounded-lg bg-primary-light px-4 py-3 text-sm text-brand">
        <CheckIcon className="h-4 w-4 shrink-0" />
        Stock matches the history for every product.
      </div>
    );
  }

  return (
    <div role="alert" className="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
      <div className="flex items-center gap-2 font-semibold">
        <WarningIcon className="h-4 w-4 shrink-0" />
        {integrity.count} {integrity.count === 1 ? 'product has' : 'products have'} stock that doesn't match its history
      </div>
      <ul className="mt-2 space-y-1">
        {integrity.issues.map((issue) => (
          <li key={`${issue.product_id}-${issue.type}`}>
            <Link href={`/admin/products/${issue.product_id}/edit`} className="font-medium underline">
              {issue.product_name}
            </Link>
            : {issue.detail}
          </li>
        ))}
      </ul>
      {integrity.count > integrity.issues.length && (
        <p className="mt-2">
          And {integrity.count - integrity.issues.length} more. Run <code>php artisan inventory:verify</code> for the full list.
        </p>
      )}
    </div>
  );
}

function Filters({ filters, reasons, product }) {
  const [search, setSearch] = useState(filters.q ?? '');

  const apply = (changes) => {
    router.get('/admin/inventory', { ...filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
  };

  const active = Boolean(filters.q || filters.reason || filters.product);

  return (
    <div className="flex flex-wrap items-center gap-3">
      <form
        onSubmit={(e) => {
          e.preventDefault();
          apply({ q: search.trim() || undefined });
        }}
        className="min-w-[12rem] flex-1 sm:max-w-xs"
      >
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Search by product name"
          placeholder="Search by product..."
          className="w-full rounded-lg border-stroke bg-surface px-3 py-2.5 text-base text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary sm:py-2 sm:text-sm"
        />
      </form>

      <select
        value={filters.reason ?? ''}
        onChange={(e) => apply({ reason: e.target.value || undefined })}
        aria-label="Filter by reason"
        className="rounded-lg border-stroke bg-surface py-2.5 text-base text-ink focus:border-primary focus:ring-primary sm:py-2 sm:text-sm"
      >
        <option value="">All reasons</option>
        {Object.entries(reasons).map(([value, label]) => (
          <option key={value} value={value}>
            {label}
          </option>
        ))}
      </select>

      {product && (
        <span className="rounded-full bg-primary-light px-3 py-1.5 text-sm text-ink">
          Only {product.name}
        </span>
      )}

      {active && (
        <Link href="/admin/inventory" className="text-sm font-medium text-primary hover:text-primary-hover">
          Clear filters
        </Link>
      )}
    </div>
  );
}

export default function Index({ movements, filters, reasons, product, integrity }) {
  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">Inventory log</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-6xl px-6 py-8">
          <Head title="Inventory log" />

          <IntegrityBanner integrity={integrity} />

          <div className="mt-6">
            <Filters filters={filters} reasons={reasons} product={product} />
          </div>

          {movements.data.length === 0 ? (
            <div className="mt-6 rounded-lg border border-dashed border-stroke py-16 text-center">
              <p className="font-medium text-ink">No stock changes match</p>
              <p className="mt-1 text-sm text-ink-muted">Try a different search or reason.</p>
            </div>
          ) : (
            <div className="mt-6 overflow-x-auto rounded-lg border border-stroke bg-surface">
              <table className="w-full min-w-[820px] text-sm">
                <thead>
                  <tr className="border-b border-stroke text-left text-ink-muted">
                    <th className="p-3 font-medium">When</th>
                    <th className="p-3 font-medium">Product</th>
                    <th className="p-3 font-medium text-right">Change</th>
                    <th className="p-3 font-medium text-right">Stock after</th>
                    <th className="p-3 font-medium">Reason</th>
                    <th className="p-3 font-medium">By</th>
                    <th className="p-3 font-medium">Note</th>
                  </tr>
                </thead>
                <tbody>
                  {movements.data.map((m) => (
                    <tr key={m.id} className="border-b border-stroke align-top last:border-b-0 hover:bg-primary-light/30">
                      <td className="whitespace-nowrap p-3 text-ink-muted">{formatDateTime(m.created_at)}</td>
                      <td className="p-3 text-ink">
                        {m.product_id ? (
                          <Link href={`/admin/products/${m.product_id}/edit`} className="hover:text-primary">
                            {m.product_name}
                          </Link>
                        ) : (
                          <>
                            {m.product_name} <span className="text-ink-muted">(deleted)</span>
                          </>
                        )}
                        {m.variant_label && <span className="block text-xs text-ink-muted">{m.variant_label}</span>}
                      </td>
                      <td className="p-3 text-right">
                        <StockChange value={m.quantity_change} />
                      </td>
                      <td className="p-3 text-right tabular-nums text-ink">{formatNumber(m.stock_after)}</td>
                      <td className="p-3 text-ink">
                        {m.reason_label}
                        {m.order_id && (
                          <>
                            {' '}
                            <Link href={`/admin/orders/${m.order_id}`} className="text-primary hover:text-primary-hover">
                              Order #{m.order_id}
                            </Link>
                          </>
                        )}
                      </td>
                      <td className="p-3 text-ink-muted">{m.user?.name ?? 'System'}</td>
                      <td className="max-w-[16rem] p-3 text-ink-muted">{m.note ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <Pagination links={movements.links} />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
