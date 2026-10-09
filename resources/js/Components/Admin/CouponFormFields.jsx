import { centsToDollarsInput, dollarsInputToCents } from '@/Components/Admin/ProductFormFields';
import { useState } from 'react';

function Field({ label, hint, error, children }) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-ink">{label}</span>
      {children}
      {hint && !error && <span className="mt-1 block text-xs text-ink-muted">{hint}</span>}
      {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
    </label>
  );
}

const inputClass =
  'w-full rounded-lg border-stroke bg-surface px-3 py-2.5 text-base text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary sm:py-2 sm:text-sm';

/** "2026-12-31T23:59:00.000000Z" -> "2026-12-31T23:59" for <input type="datetime-local">. */
export function toDateTimeInput(value) {
  return value ? value.slice(0, 16) : '';
}

function DollarInput({ cents, onChange, placeholder, ...props }) {
  const [text, setText] = useState(cents == null ? '' : centsToDollarsInput(cents));

  return (
    <div className="relative">
      <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-muted">$</span>
      <input
        type="number"
        step="0.01"
        min="0"
        value={text}
        placeholder={placeholder}
        onChange={(e) => {
          setText(e.target.value);
          onChange(e.target.value === '' ? null : dollarsInputToCents(e.target.value));
        }}
        className={`${inputClass} pl-6`}
        {...props}
      />
    </div>
  );
}

export default function CouponFormFields({ data, setData, errors }) {
  const isPercent = data.type === 'percent';

  return (
    <div className="space-y-4">
      <Field label="Code" hint="What customers type. Not case-sensitive." error={errors.code}>
        <input
          value={data.code}
          onChange={(e) => setData('code', e.target.value.toUpperCase())}
          autoComplete="off"
          spellCheck="false"
          placeholder="WELCOME10"
          className={`${inputClass} uppercase`}
        />
      </Field>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Discount type">
          <select
            value={data.type}
            onChange={(e) => {
              setData((prev) => ({ ...prev, type: e.target.value, value: e.target.value === 'percent' ? 10 : 500 }));
            }}
            className={inputClass}
          >
            <option value="percent">Percentage off</option>
            <option value="fixed">Fixed amount off</option>
          </select>
        </Field>

        <Field label={isPercent ? 'Percent off' : 'Amount off'} error={errors.value}>
          {isPercent ? (
            <div className="relative">
              <input
                type="number"
                min="1"
                max="100"
                value={data.value}
                onChange={(e) => setData('value', e.target.value === '' ? '' : parseInt(e.target.value, 10))}
                className={`${inputClass} pr-8`}
              />
              <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-ink-muted">%</span>
            </div>
          ) : (
            <DollarInput key="fixed" cents={data.value} onChange={(cents) => setData('value', cents ?? '')} />
          )}
        </Field>
      </div>

      <Field
        label="Minimum order (optional)"
        hint="The code only works when the cart is at least this much."
        error={errors.min_subtotal_cents}
      >
        <DollarInput
          cents={data.min_subtotal_cents}
          onChange={(cents) => setData('min_subtotal_cents', cents)}
          placeholder="No minimum"
        />
      </Field>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Usage limit (optional)" hint="Total redemptions across all customers." error={errors.max_uses}>
          <input
            type="number"
            min="1"
            value={data.max_uses ?? ''}
            onChange={(e) => setData('max_uses', e.target.value === '' ? null : parseInt(e.target.value, 10))}
            placeholder="Unlimited"
            className={inputClass}
          />
        </Field>

        <Field label="Expires (optional)" error={errors.expires_at}>
          <input
            type="datetime-local"
            value={data.expires_at ?? ''}
            onChange={(e) => setData('expires_at', e.target.value || null)}
            className={inputClass}
          />
        </Field>
      </div>

      <label className="flex items-center gap-2 text-sm text-ink">
        <input
          type="checkbox"
          checked={data.is_active}
          onChange={(e) => setData('is_active', e.target.checked)}
          className="rounded border-stroke text-primary focus:ring-primary"
        />
        Active (customers can use it)
      </label>

      <p className="text-xs text-ink-muted">
        Each customer can use a code once. A discount never takes an order below $0.50, the smallest amount Stripe can charge.
      </p>
    </div>
  );
}
