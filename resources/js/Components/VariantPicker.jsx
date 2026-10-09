const unique = (variants, key) => [...new Set(variants.map((v) => v[key]).filter(Boolean))];

/**
 * The variant matching a size/colour choice, or null until every option the
 * product has is chosen.
 */
export function resolveVariant(variants, { size, color }) {
  const hasSizes = unique(variants, 'size').length > 0;
  const hasColors = unique(variants, 'color').length > 0;

  if ((hasSizes && !size) || (hasColors && !color)) return null;

  return variants.find((v) => v.size === (hasSizes ? size : '') && v.color === (hasColors ? color : '')) ?? null;
}

/**
 * What the Add to cart button should ask for while a choice is missing.
 */
export function missingChoice(variants, { size, color }) {
  if (unique(variants, 'size').length > 0 && !size) return 'Choose a size';
  if (unique(variants, 'color').length > 0 && !color) return 'Choose a colour';

  return null;
}

/**
 * Set one option, and drop the other one if the pair no longer exists in stock.
 */
export function chooseOption(variants, selection, key, value) {
  const next = { ...selection, [key]: value };
  const other = key === 'size' ? 'color' : 'size';

  const stillWorks = !next[other] || variants.some((v) => v[key] === value && v[other] === next[other] && v.stock > 0);

  return stillWorks ? next : { ...next, [other]: null };
}

function OptionGroup({ title, name, options, value, onSelect }) {
  return (
    <fieldset className="mt-5 first:mt-0">
      <legend className="mb-2 text-sm font-medium text-ink">
        {title}
        {value && <span className="ml-2 font-normal text-ink-muted">{value}</span>}
      </legend>

      <div className="flex flex-wrap gap-2">
        {options.map((option) => (
          <label key={option.value} className={option.available ? 'cursor-pointer' : 'cursor-not-allowed'}>
            {/* A real radio button, hidden: arrow keys, focus and screen readers come for free. */}
            <input
              type="radio"
              name={name}
              value={option.value}
              checked={value === option.value}
              disabled={!option.available}
              onChange={() => onSelect(option.value)}
              className="peer sr-only"
            />
            <span
              className="flex min-h-[2.75rem] min-w-[2.75rem] items-center justify-center rounded-lg border border-stroke bg-surface px-3.5 text-sm font-medium text-ink transition hover:border-primary peer-checked:border-primary peer-checked:bg-primary-light peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface peer-disabled:border-dashed peer-disabled:bg-transparent peer-disabled:text-ink-muted/60 peer-disabled:line-through peer-disabled:hover:border-stroke"
            >
              {option.value}
              {!option.available && <span className="sr-only"> (sold out)</span>}
            </span>
          </label>
        ))}
      </div>
    </fieldset>
  );
}

/**
 * Size and colour choosers for a product with variants. An option is greyed
 * out and struck through when nothing with it (and with what is already
 * chosen) is in stock.
 */
export default function VariantPicker({ variants, selection, onChange, error }) {
  const sizes = unique(variants, 'size');
  const colors = unique(variants, 'color');

  const options = (key, other) =>
    unique(variants, key).map((value) => ({
      value,
      available: variants.some((v) => v[key] === value && v.stock > 0 && (!selection[other] || v[other] === selection[other])),
    }));

  return (
    <div>
      {sizes.length > 0 && (
        <OptionGroup
          title="Size"
          name="variant-size"
          options={options('size', 'color')}
          value={selection.size}
          onSelect={(value) => onChange(chooseOption(variants, selection, 'size', value))}
        />
      )}
      {colors.length > 0 && (
        <OptionGroup
          title="Colour"
          name="variant-color"
          options={options('color', 'size')}
          value={selection.color}
          onSelect={(value) => onChange(chooseOption(variants, selection, 'color', value))}
        />
      )}
      {error && <p className="mt-2 text-sm text-danger">{error}</p>}
    </div>
  );
}
