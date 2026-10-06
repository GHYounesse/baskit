import Tooltip, { TooltipRow } from '@/Components/Charts/Tooltip';
import useElementWidth from '@/Components/Charts/useElementWidth';
import { evenIndexes, niceScale } from '@/utils/chart';
import { formatDay, formatWeekday } from '@/utils/date';
import { formatMoney, formatMoneyCompact, formatNumber } from '@/utils/money';
import { useRef, useState } from 'react';

const HEIGHT = 280;

/**
 * Revenue per day as a line with a soft area wash, the previous period as a
 * dashed grey line for comparison, and a crosshair that snaps to the nearest
 * day. One axis only: orders per day live in the tooltip and the table.
 */
export default function RevenueChart({ series }) {
  const [containerRef, width] = useElementWidth();
  const svgRef = useRef(null);
  const [active, setActive] = useState(null);

  const compact = width < 480;
  const m = { top: 32, right: 24, bottom: 30, left: compact ? 46 : 54 };
  const plotW = Math.max(width - m.left - m.right, 10);
  const plotH = HEIGHT - m.top - m.bottom;
  const n = series.length;

  const peak = Math.max(0, ...series.flatMap((d) => [d.revenue_cents, d.previous_revenue_cents]));
  const { max, ticks } = niceScale(peak > 0 ? peak / 100 : 100);

  const x = (i) => m.left + (n > 1 ? (i / (n - 1)) * plotW : plotW / 2);
  const y = (cents) => m.top + plotH - (cents / 100 / max) * plotH;
  const baseline = y(0);

  const line = (key) => series.map((d, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(d[key]).toFixed(1)}`).join(' ');
  const areaPath = `${line('revenue_cents')} L${x(n - 1).toFixed(1)},${baseline} L${x(0).toFixed(1)},${baseline} Z`;

  const labelIndexes = evenIndexes(n, Math.max(2, Math.floor(plotW / 84)));

  const indexAt = (event) => {
    const rect = svgRef.current.getBoundingClientRect();
    const t = (event.clientX - rect.left - m.left) / plotW;

    return Math.min(n - 1, Math.max(0, Math.round(t * (n - 1))));
  };

  const onKeyDown = (event) => {
    const moves = {
      ArrowRight: () => Math.min(n - 1, (active ?? -1) + 1),
      ArrowLeft: () => Math.max(0, (active ?? n) - 1),
      Home: () => 0,
      End: () => n - 1,
    };

    if (event.key === 'Escape') return setActive(null);
    if (!moves[event.key]) return undefined;

    event.preventDefault();
    return setActive(moves[event.key]());
  };

  const point = active === null ? null : series[active];
  // Label the busiest day (the extreme), not the last one: today is usually
  // a partial day, so its value says little.
  const peakIndex = series.reduce((best, d, i) => (d.revenue_cents > series[best].revenue_cents ? i : best), 0);
  const peakPoint = series[peakIndex];
  const peakY = y(peakPoint.revenue_cents);
  const peakAnchor = peakIndex < n * 0.12 ? 'start' : peakIndex > n * 0.88 ? 'end' : 'middle';

  return (
    <div>
      <ul className="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-ink-muted" aria-label="Legend">
        <li className="flex items-center gap-2">
          <svg width="16" height="4" viewBox="0 0 16 4" aria-hidden="true">
            <line x1="0" y1="2" x2="16" y2="2" strokeWidth="2" strokeLinecap="round" className="stroke-series" />
          </svg>
          This period
        </li>
        <li className="flex items-center gap-2">
          <svg width="16" height="4" viewBox="0 0 16 4" aria-hidden="true">
            <line
              x1="0"
              y1="2"
              x2="16"
              y2="2"
              strokeWidth="2"
              strokeLinecap="round"
              strokeDasharray="4 3"
              className="stroke-ink-muted"
            />
          </svg>
          Previous period
        </li>
      </ul>

      <div ref={containerRef} className="relative w-full">
        <svg
          ref={svgRef}
          width={width}
          height={HEIGHT}
          role="group"
          aria-label={`Revenue by day over ${n} days. Use the left and right arrow keys to read each day.`}
          tabIndex={0}
          onKeyDown={onKeyDown}
          onFocus={() => active === null && setActive(n - 1)}
          onBlur={() => setActive(null)}
          className="block touch-pan-y rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        >
          {ticks.map((tick) => (
            <g key={tick}>
              <line
                x1={m.left}
                x2={m.left + plotW}
                y1={y(tick * 100)}
                y2={y(tick * 100)}
                strokeWidth="1"
                className={tick === 0 ? 'stroke-ink-muted' : 'stroke-stroke'}
                strokeOpacity={tick === 0 ? 0.4 : 1}
              />
              <text x={m.left - 8} y={y(tick * 100)} dy="0.32em" textAnchor="end" className="fill-ink-muted text-xs">
                {formatMoneyCompact(tick * 100)}
              </text>
            </g>
          ))}

          {labelIndexes.map((i) => (
            <text
              key={i}
              x={x(i)}
              y={HEIGHT - 8}
              textAnchor={i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle'}
              className="fill-ink-muted text-xs"
            >
              {formatDay(series[i].date)}
            </text>
          ))}

          <path d={areaPath} className="fill-series" fillOpacity="0.1" />
          <path
            d={line('previous_revenue_cents')}
            fill="none"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeDasharray="4 4"
            className="stroke-ink-muted"
            strokeOpacity="0.7"
          />
          <path
            d={line('revenue_cents')}
            fill="none"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="stroke-series"
          />

          {active === null && peakPoint.revenue_cents > 0 && (
            <>
              <circle cx={x(peakIndex)} cy={peakY} r="4.5" strokeWidth="2" className="fill-series stroke-surface" />
              <text
                x={x(peakIndex) + (peakAnchor === 'start' ? -4 : peakAnchor === 'end' ? 4 : 0)}
                y={peakY - 10}
                textAnchor={peakAnchor}
                strokeWidth="3"
                paintOrder="stroke"
                className="fill-ink stroke-surface text-xs font-semibold"
              >
                {formatMoney(peakPoint.revenue_cents)}
              </text>
            </>
          )}

          {point && (
            <>
              <line
                x1={x(active)}
                x2={x(active)}
                y1={m.top}
                y2={baseline}
                strokeWidth="1"
                className="stroke-ink-muted"
                strokeOpacity="0.5"
              />
              <circle cx={x(active)} cy={y(point.revenue_cents)} r="5" strokeWidth="2" className="fill-series stroke-surface" />
            </>
          )}

          <rect
            x={m.left}
            y={m.top}
            width={plotW}
            height={plotH}
            fill="transparent"
            onPointerDown={(e) => setActive(indexAt(e))}
            onPointerMove={(e) => setActive(indexAt(e))}
            onPointerLeave={() => setActive(null)}
          />
        </svg>

        {point && (
          <Tooltip left={x(active)} top={m.top} flip={x(active) > width / 2}>
            <div className="text-xs font-medium text-ink-muted">
              {formatWeekday(point.date)}, {formatDay(point.date, { year: true })}
            </div>
            <TooltipRow
              value={formatMoney(point.revenue_cents)}
              label="revenue"
              detail={`${formatNumber(point.orders)} ${point.orders === 1 ? 'order' : 'orders'}`}
            />
            <TooltipRow
              keyKind="previous"
              value={formatMoney(point.previous_revenue_cents)}
              label="previous"
              detail={formatDay(point.previous_date)}
            />
          </Tooltip>
        )}

        <p className="sr-only" aria-live="polite">
          {point
            ? `${formatDay(point.date, { year: true })}: ${formatMoney(point.revenue_cents)} revenue from ${point.orders} orders. Previous period: ${formatMoney(point.previous_revenue_cents)}.`
            : ''}
        </p>
      </div>
    </div>
  );
}
