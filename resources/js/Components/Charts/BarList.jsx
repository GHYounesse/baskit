import Tooltip from '@/Components/Charts/Tooltip';
import { useRef, useState } from 'react';

const VALUE_ROOM = '5.75rem';

/**
 * Horizontal bars for comparing named things (products, categories). One
 * series, so one colour; the bar length is the only thing that varies. The
 * value sits at the tip of each bar. Each row is its own hover and focus
 * target, with its fuller details in the tooltip.
 *
 * rows: [{ key, label, value (number, drives length), valueText, details: [{ label, text }] }]
 */
export default function BarList({ rows, emptyText }) {
  const containerRef = useRef(null);
  const [hover, setHover] = useState(null);

  if (rows.length === 0) {
    return <p className="py-10 text-center text-sm text-ink-muted">{emptyText}</p>;
  }

  const max = Math.max(...rows.map((r) => r.value), 1);

  const place = (event, row) => {
    const box = containerRef.current.getBoundingClientRect();
    const left = event.clientX - box.left;

    setHover({ row, left, top: event.clientY - box.top, flip: left > box.width / 2 });
  };

  const placeFromFocus = (event, row) => {
    const box = containerRef.current.getBoundingClientRect();
    const target = event.currentTarget.getBoundingClientRect();
    const left = Math.min(box.width * 0.6, target.width * (row.value / max));

    setHover({ row, left, top: target.top - box.top, flip: false });
  };

  return (
    <div ref={containerRef} className="relative">
      <ul className="space-y-1">
        {rows.map((row) => (
          <li
            key={row.key}
            tabIndex={0}
            aria-label={`${row.label}: ${row.valueText}`}
            onPointerEnter={(e) => place(e, row)}
            onPointerMove={(e) => place(e, row)}
            onPointerLeave={() => setHover(null)}
            onFocus={(e) => placeFromFocus(e, row)}
            onBlur={() => setHover(null)}
            className="group rounded-md px-1 py-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:grid sm:grid-cols-[minmax(0,11rem)_1fr] sm:items-center sm:gap-3"
          >
            <div className="truncate text-sm text-ink-muted group-hover:text-ink">{row.label}</div>

            {/* The left edge is the baseline: bars start square there and round at the data end. */}
            <div className="mt-1 flex items-center border-l border-ink-muted/40 sm:mt-0">
              <div
                className="h-3 rounded-r bg-series transition-opacity group-hover:opacity-80"
                style={{ width: `calc((100% - ${VALUE_ROOM}) * ${row.value / max})`, minWidth: row.value > 0 ? 2 : 0 }}
              />
              <span className="ml-2 shrink-0 text-sm font-semibold text-ink">{row.valueText}</span>
            </div>
          </li>
        ))}
      </ul>

      {hover && (
        <Tooltip left={hover.left} top={hover.top} flip={hover.flip}>
          <div className="font-semibold text-ink">{hover.row.label}</div>
          {hover.row.details.map((detail) => (
            <div key={detail.label} className="mt-1 flex items-baseline justify-between gap-4 text-sm">
              <span className="text-ink-muted">{detail.label}</span>
              <span className="font-medium text-ink">{detail.text}</span>
            </div>
          ))}
        </Tooltip>
      )}
    </div>
  );
}
