import ConfirmDialog from '@/Components/ConfirmDialog';
import { formatNumber } from '@/utils/money';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const inputClass =
  'w-full rounded-lg border-stroke bg-surface px-3 py-2 text-base text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary sm:text-sm';

function Field({ label, error, children, className = '' }) {
  return (
    <label className={`block ${className}`}>
      <span className="mb-1 block text-xs font-medium text-ink-muted">{label}</span>
      {children}
      {error && <span className="mt-1 block text-xs text-danger">{error}</span>}
    </label>
  );
}

/**
 * The fields of one variant. Used for adding a new one and for editing a row.
 * A reason box appears when the stock number is being changed on an existing variant.
 */
function VariantFields({ form, original }) {
  const { data, setData, errors } = form;
  const stockChanged = original && Number(data.stock) !== Number(original.stock);

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <Field label="Size" error={errors.size}>
        <input value={data.size} onChange={(e) => setData('size', e.target.value)} className={inputClass} placeholder="M" maxLength={40} />
      </Field>
      <Field label="Colour" error={errors.color}>
        <input value={data.color} onChange={(e) => setData('color', e.target.value)} className={inputClass} placeholder="Blue" maxLength={40} />
      </Field>
      <Field label="SKU" error={errors.sku}>
        <input
          value={data.sku}
          onChange={(e) => setData('sku', e.target.value)}
          className={`${inputClass} uppercase placeholder:normal-case`}
          placeholder="Made for you"
          maxLength={64}
          autoComplete="off"
        />
      </Field>
      <Field label="Stock" error={errors.stock}>
        <input type="number" min="0" value={data.stock} onChange={(e) => setData('stock', e.target.value)} className={inputClass} />
      </Field>

      {stockChanged && (
        <Field label="Reason for the stock change (optional)" error={errors.stock_note} className="col-span-2 sm:col-span-4">
          <input
            value={data.stock_note ?? ''}
            onChange={(e) => setData('stock_note', e.target.value)}
            className={inputClass}
            placeholder="For example: recount after delivery"
            maxLength={255}
          />
        </Field>
      )}
    </div>
  );
}

function AddVariant({ product, hasVariants, onDone }) {
  const form = useForm({ size: '', color: '', sku: '', stock: 0 });

  const submit = (e) => {
    e.preventDefault();
    form.post(`/admin/products/${product.id}/variants`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        onDone?.();
      },
    });
  };

  return (
    <form onSubmit={submit} className="rounded-lg border border-stroke bg-canvas p-4">
      <h3 className="mb-3 text-sm font-semibold text-ink">{hasVariants ? 'Add a variant' : 'Add the first variant'}</h3>

      {!hasVariants && product.stock > 0 && (
        <p className="mb-3 rounded-lg bg-primary-light px-3 py-2 text-sm text-ink">
          This product has {formatNumber(product.stock)} in stock now. Once it has variants, stock is tracked per variant, so that
          stock is cleared (and recorded in the inventory log). Enter each variant's own stock.
        </p>
      )}

      <VariantFields form={form} />

      <div className="mt-4 flex gap-3">
        <button
          disabled={form.processing}
          className="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-on transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:opacity-60"
        >
          {form.processing ? 'Adding…' : 'Add variant'}
        </button>
        {onDone && (
          <button
            type="button"
            onClick={onDone}
            className="rounded-lg border border-stroke px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-primary-hover/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          >
            Cancel
          </button>
        )}
      </div>
    </form>
  );
}

function EditVariant({ variant, onDone }) {
  const form = useForm({
    size: variant.size,
    color: variant.color,
    sku: variant.sku,
    stock: variant.stock,
    stock_note: '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.put(`/admin/variants/${variant.id}`, { preserveScroll: true, onSuccess: onDone });
  };

  return (
    <form onSubmit={submit} className="rounded-lg border border-primary bg-canvas p-4">
      <VariantFields form={form} original={variant} />

      <div className="mt-4 flex gap-3">
        <button
          disabled={form.processing}
          className="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-on transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:opacity-60"
        >
          {form.processing ? 'Saving…' : 'Save'}
        </button>
        <button
          type="button"
          onClick={onDone}
          className="rounded-lg border border-stroke px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        >
          Cancel
        </button>
      </div>
    </form>
  );
}

/**
 * Sizes and colours for a product, each with its own SKU and stock. The
 * product's stock becomes the total of its variants.
 */
export default function VariantsManager({ product, variants }) {
  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);

  const hasVariants = variants.length > 0;

  const remove = () => {
    setDeleting(true);
    router.delete(`/admin/variants/${pendingDelete.id}`, {
      preserveScroll: true,
      onFinish: () => {
        setDeleting(false);
        setPendingDelete(null);
      },
    });
  };

  return (
    <section className="mt-6 rounded-lg border border-stroke bg-surface p-5" aria-labelledby="variants-heading">
      <div className="flex items-baseline justify-between gap-3">
        <h2 id="variants-heading" className="font-display text-lg font-bold text-ink">
          Variants
        </h2>
        {hasVariants && <span className="text-sm text-ink-muted">{formatNumber(product.stock)} in stock in total</span>}
      </div>

      {!hasVariants && !adding && (
        <div className="mt-2">
          <p className="text-sm text-ink-muted">
            Sell this product in sizes or colours. Each variant has its own SKU and stock, and shares the product's price.
          </p>
          <button
            type="button"
            onClick={() => setAdding(true)}
            className="mt-3 rounded-lg border border-primary px-4 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          >
            Add the first variant
          </button>
        </div>
      )}

      {hasVariants && (
        <ul className="mt-3 divide-y divide-stroke">
          {variants.map((variant) =>
            editing === variant.id ? (
              <li key={variant.id} className="py-3">
                <EditVariant variant={variant} onDone={() => setEditing(null)} />
              </li>
            ) : (
              <li key={variant.id} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3 first:pt-0">
                <div className="min-w-0">
                  <div className="font-medium text-ink">{variant.label}</div>
                  <div className="text-xs text-ink-muted">SKU {variant.sku}</div>
                </div>
                <div className="flex items-center gap-4">
                  <span className={`text-sm font-semibold tabular-nums ${variant.stock === 0 ? 'text-ink-muted' : 'text-ink'}`}>
                    {variant.stock === 0 ? 'Sold out' : `${formatNumber(variant.stock)} in stock`}
                  </span>
                  <button
                    type="button"
                    onClick={() => setEditing(variant.id)}
                    className="-my-2 rounded px-2 py-2.5 text-sm text-primary hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    onClick={() => setPendingDelete(variant)}
                    className="-my-2 rounded px-2 py-2.5 text-sm text-danger hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                  >
                    Remove
                  </button>
                </div>
              </li>
            ),
          )}
        </ul>
      )}

      {(adding || hasVariants) && (
        <div className="mt-4">
          {hasVariants && !adding ? (
            <button
              type="button"
              onClick={() => setAdding(true)}
              className="rounded-lg border border-primary px-4 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
              Add a variant
            </button>
          ) : (
            <AddVariant product={product} hasVariants={hasVariants} onDone={() => setAdding(false)} />
          )}
        </div>
      )}

      <ConfirmDialog
        show={pendingDelete !== null}
        title="Remove this variant?"
        confirmLabel="Remove variant"
        processingLabel="Removing…"
        processing={deleting}
        onConfirm={remove}
        onCancel={() => setPendingDelete(null)}
      >
        <strong className="font-medium text-ink">{pendingDelete?.label}</strong> will no longer be sold
        {pendingDelete?.stock > 0 ? `, and its ${pendingDelete.stock} in stock will be taken out of the inventory (recorded in the log)` : ''}.
        Past orders keep it.
      </ConfirmDialog>
    </section>
  );
}
