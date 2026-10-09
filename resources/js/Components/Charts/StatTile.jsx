function Arrow({ up }) {
  return (
    <svg viewBox="0 0 12 12" fill="currentColor" className="h-3 w-3" aria-hidden="true">
      {up ? <path d="M6 2l4.5 7h-9z" /> : <path d="M6 10L1.500 3h9z" />}
    </svg>
  );
}

/**
 * Percentage change between two numbers. Null when it can't be expressed
 * (nothing to compare to), so the tile can say so in words instead.
 */
export function percentChange(current, previous) {
  if (!previous) return null;

  return ((current - previous) / previous) * 100;
}

function formatPercent(value) {
  const abs = Math.abs(value);

  return `${abs < 10 ? abs.toFixed(1).replace(/\.0$/, '') : Math.round(abs)}%`;
}

/**
 * Whether a change is good news. Direction is shown with an arrow and a
 * word for screen readers; colour only says good or bad, so it is never
 * the only signal.
 */
function Delta({ current, previous, upIsGood, comparedTo }) {
  const change = percentChange(current, previous);

  if (change === null) {
    return (
      <p className="mt-2 text-sm text-ink-muted">
        {current === 0 ? 'No change' : `None in the ${comparedTo}`}
      </p>
    );
  }

  const rounded = Math.abs(change) < 0.05;
  const up = change > 0;
  const good = rounded ? null : up === upIsGood;
  const tone = rounded ? 'text-ink-muted' : good ? 'text-brand' : 'text-danger';

  return (
    <p className="mt-2 flex flex-wrap items-center gap-x-1.5 text-sm">
      <span className={`inline-flex items-center gap-1 font-semibold ${tone}`}>
        {!rounded && <Arrow up={up} />}
        <span className="sr-only">{rounded ? 'Unchanged' : up ? 'Up' : 'Down'}</span>
        {rounded ? '0%' : formatPercent(change)}
      </span>
      <span className="text-ink-muted">vs {comparedTo}</span>
    </p>
  );
}

/**
 * One headline number. `hero` makes it the figure the page leads with
 * (use it once per view); the rest stay a step smaller.
 */
export default function StatTile({ label, value, current, previous, upIsGood = true, comparedTo, note, hero = false }) {
  return (
    <div className="rounded-lg border border-stroke bg-surface p-4 sm:p-5">
      <div className="text-sm text-ink-muted">{label}</div>
      <div
        className={`mt-1 font-display font-bold leading-none text-ink ${hero ? 'text-5xl' : 'text-3xl'}`}
        aria-label={`${label}: ${value}`}
      >
        {value}
      </div>
      <Delta current={current} previous={previous} upIsGood={upIsGood} comparedTo={comparedTo} />
      {note && <p className="mt-1 text-xs text-ink-muted">{note}</p>}
    </div>
  );
}
