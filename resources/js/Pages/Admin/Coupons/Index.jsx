import ConfirmDialog from '@/Components/ConfirmDialog';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_STYLES = {
  active: 'bg-primary-light text-primary',
  disabled: 'bg-stroke text-ink-muted',
  expired: 'bg-warning-light text-warning',
  'used up': 'bg-warning-light text-warning',
};

function TagIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M3 12V4a1 1 0 011-1h8l9 9-9 9-9-9z" strokeLinejoin="round" />
      <circle cx="8" cy="8" r="1.25" />
    </svg>
  );
}

function EmptyCoupons() {
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-stroke py-20 text-center">
      <TagIcon className="h-8 w-8 text-ink-muted/50" />
      <div className="font-medium text-ink">No coupons yet</div>
      <Link href="/admin/coupons/create" className="text-sm font-medium text-primary hover:text-primary-hover">
        Create your first coupon
      </Link>
    </div>
  );
}

export default function Index({ coupons }) {
  const [pendingDelete, setPendingDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);

  const confirmDelete = () => {
    setDeleting(true);
    router.delete(`/admin/coupons/${pendingDelete.id}`, {
      preserveScroll: true,
      onFinish: () => {
        setDeleting(false);
        setPendingDelete(null);
      },
    });
  };

  return (
    <AuthenticatedLayout>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-5xl px-6 py-8">
          <Head title="Manage coupons" />

          <PageHeader title="Manage coupons">
            <Link
              href="/admin/coupons/create"
              className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-on transition hover:bg-primary-hover"
            >
                + New coupon
            </Link>
          </PageHeader>

          {coupons.data.length === 0 ? (
            <EmptyCoupons />
          ) : (
            <div className="overflow-x-auto rounded-lg border border-stroke bg-surface">
              <table className="w-full min-w-[640px] text-sm">
                <thead>
                  <tr className="border-b border-stroke text-left text-ink-muted">
                    <th className="p-3 font-medium">Code</th>
                    <th className="p-3 font-medium">Discount</th>
                    <th className="p-3 font-medium">Minimum</th>
                    <th className="p-3 font-medium text-right">Used</th>
                    <th className="p-3 font-medium">Expires</th>
                    <th className="p-3 font-medium">Status</th>
                    <th className="p-3"></th>
                  </tr>
                </thead>
                <tbody>
                  {coupons.data.map((coupon) => (
                    <tr key={coupon.id} className="border-b border-stroke last:border-b-0 hover:bg-primary-light/30">
                      <td className="p-3 font-semibold text-ink">{coupon.code}</td>
                      <td className="p-3 text-ink">{coupon.label}</td>
                      <td className="p-3 text-ink-muted">
                        {coupon.min_subtotal_cents ? formatMoney(coupon.min_subtotal_cents) : '—'}
                      </td>
                      <td className="p-3 text-right text-ink">
                        {coupon.used_count}
                        {coupon.max_uses ? <span className="text-ink-muted"> / {coupon.max_uses}</span> : null}
                      </td>
                      <td className="p-3 text-ink-muted">{coupon.expires_at ? formatDate(coupon.expires_at) : 'Never'}</td>
                      <td className="p-3">
                        <span
                          className={`inline-block rounded-full px-2.5 py-1 text-xs font-medium capitalize ${STATUS_STYLES[coupon.status]}`}
                        >
                          {coupon.status}
                        </span>
                      </td>
                      <td className="p-3 text-right">
                        <div className="flex justify-end gap-3">
                          <Link
                            href={`/admin/coupons/${coupon.id}/edit`}
                            className="-my-2 rounded px-2 py-2.5 text-primary hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                          >
                            Edit
                          </Link>
                          <button
                            onClick={() => setPendingDelete(coupon)}
                            className="-my-2 rounded px-2 py-2.5 text-danger hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                          >
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <Pagination links={coupons.links} />
        </div>
      </div>

      <ConfirmDialog
        show={pendingDelete !== null}
        title="Delete this coupon?"
        confirmLabel="Delete coupon"
        processing={deleting}
        onConfirm={confirmDelete}
        onCancel={() => setPendingDelete(null)}
      >
        <strong className="font-medium text-ink">{pendingDelete?.code}</strong> will stop working. Past orders keep the
        code they used.
      </ConfirmDialog>
    </AuthenticatedLayout>
  );
}
