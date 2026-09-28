import { useState } from 'react';

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

function Field({ label, error, children }) {
  return (
    <div>
      <label className="mb-1 block text-sm font-medium text-ink">{label}</label>
      {children}
      {error && <div className="mt-1 text-sm text-red-600">{error}</div>}
    </div>
  );
}

const inputClass =
  'w-full rounded-lg border-stroke bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary';

/**
 * Converts price_cents <-> a dollars string for display, so the admin types
 * "14.99" instead of the raw stored integer (1499). The form still submits
 * price_cents; this only changes what's shown and typed.
 */
export function centsToDollarsInput(cents) {
  return (Number(cents ?? 0) / 100).toFixed(2);
}

export function dollarsInputToCents(value) {
  const parsed = Math.round(parseFloat(value || '0') * 100);
  return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0;
}

export default function ProductFormFields({ data, setData, errors, categories, preview, onImageChange, onRemoveImage }) {
  const [priceInput, setPriceInput] = useState(centsToDollarsInput(data.price_cents));

  const updatePrice = (value) => {
    setPriceInput(value);
    setData('price_cents', dollarsInputToCents(value));
  };

  return (
    <div className="space-y-4">
      <Field label="Name" error={errors.name}>
        <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputClass} />
      </Field>

      <Field label="Category">
        <select
          value={data.category_id}
          onChange={(e) => setData('category_id', e.target.value)}
          className={inputClass}
        >
          <option value="">None</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
      </Field>

      <Field label="Description">
        <textarea
          value={data.description}
          onChange={(e) => setData('description', e.target.value)}
          className={inputClass}
          rows={4}
        />
      </Field>

      <div className="flex gap-4">
        <div className="flex-1">
          <Field label="Price" error={errors.price_cents}>
            <div className="relative">
              <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-muted">$</span>
              <input
                type="number"
                step="0.01"
                min="0"
                value={priceInput}
                onChange={(e) => updatePrice(e.target.value)}
                className={`${inputClass} pl-6`}
              />
            </div>
          </Field>
        </div>
        <div className="flex-1">
          <Field label="Stock" error={errors.stock}>
            <input
              type="number"
              min="0"
              value={data.stock}
              onChange={(e) => setData('stock', e.target.value)}
              className={inputClass}
            />
          </Field>
        </div>
      </div>

      <label className="flex items-center gap-2 text-sm text-ink">
        <input
          type="checkbox"
          checked={data.is_active}
          onChange={(e) => setData('is_active', e.target.checked)}
          className="rounded border-stroke text-primary focus:ring-primary"
        />
        Active (visible in shop)
      </label>

      <Field label="Image" error={errors.image}>
        <div className="flex items-center gap-4">
          <div className="h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-stroke bg-canvas">
            {preview ? (
              <img src={preview} alt="Preview" className="h-full w-full object-cover" />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-ink-muted/40">
                <BasketIcon className="h-8 w-8" />
              </div>
            )}
          </div>
          <div className="flex-1">
            <input type="file" accept="image/*" onChange={onImageChange} className="block w-full text-sm text-ink-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-light file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20" />
            {onRemoveImage && preview && !data.image && (
              <button type="button" onClick={onRemoveImage} className="mt-1 text-sm text-red-600 hover:text-red-700">
                Remove image
              </button>
            )}
          </div>
        </div>
      </Field>
    </div>
  );
}
