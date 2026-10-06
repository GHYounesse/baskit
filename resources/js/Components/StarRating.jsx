import { useState } from 'react';

const STAR_PATH = 'M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9 4.8 17.6l1-5.8L1.5 7.7l5.9-.9L10 1.5z';

function StarRow({ className, size }) {
  return (
    <span className={`inline-flex whitespace-nowrap ${className}`} aria-hidden="true">
      {[0, 1, 2, 3, 4].map((i) => (
        <svg key={i} viewBox="0 0 20 20" fill="currentColor" className={`shrink-0 ${size}`}>
          <path d={STAR_PATH} />
        </svg>
      ))}
    </span>
  );
}

/**
 * Read-only stars with fractional fill (4.3 fills four and a bit).
 */
export function StarRating({ value, size = 'h-4 w-4' }) {
  const percent = (Math.max(0, Math.min(5, Number(value) || 0)) / 5) * 100;

  return (
    <span
      role="img"
      aria-label={`Rated ${Number(value).toFixed(1)} out of 5`}
      className="relative inline-block leading-none"
    >
      <StarRow className="text-ink-muted/30" size={size} />
      <span className="absolute inset-y-0 left-0 overflow-hidden" style={{ width: `${percent}%` }}>
        <StarRow className="text-amber-500" size={size} />
      </span>
    </span>
  );
}

/**
 * Pick 1-5 stars. Real radio inputs underneath, so arrow keys and screen
 * readers work without extra code.
 */
export function StarInput({ value, onChange, name = 'rating' }) {
  const [hover, setHover] = useState(0);
  const shown = hover || value;

  return (
    <fieldset>
      <legend className="sr-only">Your rating</legend>
      <div className="-ml-1 flex" onMouseLeave={() => setHover(0)}>
        {[1, 2, 3, 4, 5].map((n) => (
          <label
            key={n}
            onMouseEnter={() => setHover(n)}
            className="cursor-pointer rounded-lg p-1 focus-within:ring-2 focus-within:ring-primary"
          >
            <input
              type="radio"
              name={name}
              value={n}
              checked={value === n}
              onChange={() => onChange(n)}
              className="sr-only"
            />
            <svg
              viewBox="0 0 20 20"
              fill="currentColor"
              className={`h-9 w-9 transition-colors sm:h-8 sm:w-8 ${shown >= n ? 'text-amber-500' : 'text-ink-muted/30'}`}
              aria-hidden="true"
            >
              <path d={STAR_PATH} />
            </svg>
            <span className="sr-only">
              {n} {n === 1 ? 'star' : 'stars'}
            </span>
          </label>
        ))}
      </div>
    </fieldset>
  );
}
