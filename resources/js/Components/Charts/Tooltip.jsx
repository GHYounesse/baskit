/**
 * Floating readout for a chart, positioned in pixels inside a `relative`
 * container. Never takes the pointer, so it can't get in the way of the
 * chart under it. `flip` puts it left of the anchor instead of right.
 *
 * Everything inside is rendered as React text nodes, so names that come
 * from data are never parsed as markup.
 */
export default function Tooltip({ left, top, flip = false, children }) {
  return (
    <div
      className="pointer-events-none absolute z-10 min-w-[12.5rem] max-w-[18rem] rounded-lg border border-stroke bg-surface px-3 py-2 text-sm shadow-lg"
      style={{ left, top, transform: flip ? 'translateX(calc(-100% - 12px))' : 'translateX(12px)' }}
    >
      {children}
    </div>
  );
}

/**
 * One line in a tooltip: a short line-key in the series style, the value
 * (strong) and the series name after it (secondary).
 */
export function TooltipRow({ keyKind = 'series', value, label, detail }) {
  return (
    <div className="mt-1 flex items-center gap-2">
      <svg width="14" height="4" viewBox="0 0 14 4" aria-hidden="true" className="shrink-0">
        <line
          x1="0"
          y1="2"
          x2="14"
          y2="2"
          strokeWidth="2"
          strokeLinecap="round"
          strokeDasharray={keyKind === 'previous' ? '4 3' : undefined}
          className={keyKind === 'previous' ? 'stroke-ink-muted' : 'stroke-series'}
        />
      </svg>
      <span className="font-semibold text-ink">{value}</span>
      <span className="text-ink-muted">{label}</span>
      {detail && <span className="ml-auto shrink-0 whitespace-nowrap pl-2 text-xs text-ink-muted">{detail}</span>}
    </div>
  );
}
