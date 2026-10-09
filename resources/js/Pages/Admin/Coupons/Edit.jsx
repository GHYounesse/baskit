import CouponFormFields, { toDateTimeInput } from '@/Components/Admin/CouponFormFields';
import ConfirmDialog from '@/Components/ConfirmDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Edit({ coupon }) {
  const { data, setData, put, processing, errors } = useForm({
    code: coupon.code,
    type: coupon.type,
    value: coupon.value,
    min_subtotal_cents: coupon.min_subtotal_cents,
    max_uses: coupon.max_uses,
    expires_at: toDateTimeInput(coupon.expires_at),
    is_active: coupon.is_active,
  });
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const submit = (e) => {
    e.preventDefault();
    put(`/admin/coupons/${coupon.id}`);
  };

  const destroy = () => {
    setDeleting(true);
    router.delete(`/admin/coupons/${coupon.id}`, {
      onFinish: () => {
        setDeleting(false);
        setConfirmingDelete(false);
      },
    });
  };

  return (
    <AuthenticatedLayout>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-lg px-6 py-8">
          <Head title={`Edit ${coupon.code}`} />

          <PageHeader title="Edit coupon" />

          <p className="mb-4 text-sm text-ink-muted">
            Used {coupon.used_count} {coupon.used_count === 1 ? 'time' : 'times'}
            {coupon.max_uses ? ` of ${coupon.max_uses}` : ''}.
          </p>

          <form onSubmit={submit} className="space-y-6 rounded-lg border border-stroke bg-surface p-5">
            <CouponFormFields data={data} setData={setData} errors={errors} />

            <div className="flex gap-3">
              <button
                disabled={processing}
                className="flex-1 rounded-lg bg-primary py-3 text-sm font-semibold text-primary-on transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-50"
              >
                Save changes
              </button>
              <button
                type="button"
                onClick={() => setConfirmingDelete(true)}
                className="rounded-lg border border-danger px-4 py-3 text-sm font-semibold text-danger transition hover:bg-danger-light focus:outline-none focus-visible:ring-2 focus-visible:ring-danger dark:hover:bg-red-950"
              >
                Delete
              </button>
            </div>
          </form>
        </div>
      </div>

      <ConfirmDialog
        show={confirmingDelete}
        title="Delete this coupon?"
        confirmLabel="Delete coupon"
        processing={deleting}
        onConfirm={destroy}
        onCancel={() => setConfirmingDelete(false)}
      >
        <strong className="font-medium text-ink">{coupon.code}</strong> will stop working. Past orders keep the code
        they used. To pause it instead, untick Active.
      </ConfirmDialog>
    </AuthenticatedLayout>
  );
}
