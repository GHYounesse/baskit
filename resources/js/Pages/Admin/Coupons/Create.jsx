import CouponFormFields from '@/Components/Admin/CouponFormFields';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, useForm } from '@inertiajs/react';

export default function Create() {
  const { data, setData, post, processing, errors } = useForm({
    code: '',
    type: 'percent',
    value: 10,
    min_subtotal_cents: null,
    max_uses: null,
    expires_at: null,
    is_active: true,
  });

  const submit = (e) => {
    e.preventDefault();
    post('/admin/coupons');
  };

  return (
    <AuthenticatedLayout>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-lg px-6 py-8">
          <Head title="New coupon" />

          <PageHeader title="New coupon" />

          <form onSubmit={submit} className="space-y-6 rounded-lg border border-stroke bg-surface p-5">
            <CouponFormFields data={data} setData={setData} errors={errors} />

            <button
              disabled={processing}
              className="w-full rounded-lg bg-primary py-3 text-sm font-semibold text-primary-on transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-50"
            >
              Create coupon
            </button>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
