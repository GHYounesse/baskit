/**
 * The one date-range control for the whole analytics page: presets, not a
 * calendar. Everything below it re-renders for the chosen slice.
 */
export default function RangeFilter({ range, ranges, onChange }) {
  return (
    <div role="group" aria-label="Date range" className="inline-flex rounded-lg border border-stroke bg-surface p-0.5">
      {ranges.map((days) => {
        const selected = days === range;

        return (
          <button
            key={days}
            type="button"
            aria-pressed={selected}
            onClick={() => !selected && onChange(days)}
            className={`flex h-10 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:h-9 ${
              selected ? 'bg-primary-light text-ink' : 'text-ink-muted hover:bg-primary-light/60 hover:text-ink'
            }`}
          >
            {selected && (
              <svg viewBox="0 0 16 16" className="h-4 w-4 text-primary" fill="none" stroke="currentColor" strokeWidth="2.25" aria-hidden="true">
                <path d="M3 8.500l3.500 3.500L13 4.500" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            )}
            Last {days} days
          </button>
        );
      })}
    </div>
  );
}
