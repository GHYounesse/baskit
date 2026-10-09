import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import ProductFormFields from '@/Components/Admin/ProductFormFields';
import StockChange from '@/Components/Admin/StockChange';
import VariantsManager from '@/Components/Admin/VariantsManager';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { formatDateTime } from '@/utils/date';

export default function Edit({ product, categories, variants, movements }) {
  const { data, setData, put, processing, errors } = useForm({
    name: product.name,
    category_id: product.category_id ?? '',
    description: product.description ?? '',
    price_cents: product.price_cents,
    stock: product.stock,
    stock_note: '',
    is_active: product.is_active,
    image: null,
    remove_image: false,
  });
  const [preview, setPreview] = useState(product.image_url);

  const submit = (e) => {
    e.preventDefault();
    put(`/admin/products/${product.id}`);
  };

  const onImageChange = (e) => {
    const file = e.target.files[0] ?? null;
    setData((prev) => ({ ...prev, image: file, remove_image: false }));
    setPreview(file ? URL.createObjectURL(file) : product.image_url);
  };

  const removeImage = () => {
    setData((prev) => ({ ...prev, image: null, remove_image: true }));
    setPreview(null);
  };

  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const destroy = () => {
    setDeleting(true);
    router.delete(`/admin/products/${product.id}`, {
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
          <Head title="Edit product" />

          <PageHeader title="Edit product" />

          <form onSubmit={submit} className="space-y-6 rounded-lg border border-stroke bg-surface p-5">
            <ProductFormFields
              data={data}
              setData={setData}
              errors={errors}
              categories={categories}
              preview={preview}
              onImageChange={onImageChange}
              onRemoveImage={removeImage}
              originalStock={product.stock}
              stockLocked={variants.length > 0}
            />

            <div className="flex gap-3">
              <button
                disabled={processing}
                className="flex-1 rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-on transition hover:bg-primary-hover disabled:opacity-50"
              >
                Save changes
              </button>
              <button
                type="button"
                onClick={() => setConfirmingDelete(true)}
                className="rounded-lg border border-danger px-4 py-2.5 text-sm font-semibold text-danger transition hover:bg-danger-light"
              >
                Delete
              </button>
            </div>
          </form>

          <VariantsManager product={product} variants={variants} />

          <section className="mt-6 rounded-lg border border-stroke bg-surface p-5" aria-labelledby="stock-history">
            <div className="flex items-baseline justify-between gap-3">
              <h2 id="stock-history" className="font-display text-lg font-bold text-ink">
                Stock history
              </h2>
              <Link
                href={`/admin/inventory?product=${product.id}`}
                className="text-sm font-medium text-primary hover:text-primary-hover"
              >
                View all
              </Link>
            </div>

            {movements.length === 0 ? (
              <p className="mt-3 text-sm text-ink-muted">No stock changes recorded yet.</p>
            ) : (
              <ul className="mt-3 divide-y divide-stroke text-sm">
                {movements.map((m) => (
                  <li key={m.id} className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                    <div className="min-w-0">
                      <div className="text-ink">
                        {m.variant_label && <span className="mr-1 font-medium">{m.variant_label}:</span>}
                        {m.reason_label}
                        {m.order_id && (
                          <>
                            {' '}
                            <Link href={`/admin/orders/${m.order_id}`} className="text-primary hover:text-primary-hover">
                              Order #{m.order_id}
                            </Link>
                          </>
                        )}
                      </div>
                      <div className="text-xs text-ink-muted">
                        {formatDateTime(m.created_at)} · {m.user?.name ?? 'System'}
                      </div>
                      {m.note && <div className="mt-0.5 text-xs text-ink-muted">{m.note}</div>}
                    </div>
                    <div className="shrink-0 text-right">
                      <StockChange value={m.quantity_change} />
                      <div className="text-xs text-ink-muted">now {m.stock_after}</div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      </div>

      <ConfirmDialog
        show={confirmingDelete}
        title="Delete this product?"
        confirmLabel="Delete product"
        processing={deleting}
        onConfirm={destroy}
        onCancel={() => setConfirmingDelete(false)}
      >
        <strong className="font-medium text-ink">{product.name}</strong> will be removed from the shop.
        This can't be undone.
      </ConfirmDialog>
    </AuthenticatedLayout>
  );
}
