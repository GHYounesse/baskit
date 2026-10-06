import Tooltip from '@/Components/Charts/Tooltip';
import useElementWidth from '@/Components/Charts/useElementWidth';
import { niceScale } from '@/utils/chart';
import { formatNumber } from '@/utils/money';
import { useState } from 'react';

const HEIGHT = 306;
const MAX_BAR = 24;
const RADIUS = 4;

/**
 * A column with a rounded top and a square foot, so it grows from the baseline.
 */
function columnPath(x, top, width, bottom) {
  const r = Math.min(RADIUS, width / 2, bottom - top);

  return `M${x},${bottom} L${x},${top + r} Q${x},${top} ${x + r},${top} L${x + width - r},${top} Q${x + width},${top} ${x + width},${top + r} L${x + width},${bottom} Z`;
}

/**
 * Orders per weekday. Emphasis chart: the busiest day(s) wear the series
 * colour, the rest sit back in grey, and only the peak carries a number
 * (the axis, tooltip and table carry the rest).
 */
export default function ColumnChart({ data }) {
  const [containerRef, width] = useElementWidth();
  const [active, setActive] = useState(null);

  const m = { top: 24, right: 8, bottom: 28, left: 32 };
  const plotW = Math.max(width - m.left - m.right, 10);
  const plotH = HEIGHT - m.top - m.bottom;
  const band = plotW / data.length;
  const barW = Math.min(MAX_BAR, band * 0.6);

  const peak = Math.max(0, ...data.map((d) => d.orders));
  const { max, ticks } = niceScale(peak, { integer: true, ticks: 3 });

  const y = (value) => m.top + plotH - (value / max) * plotH;
  const baseline = y(0);
  const cx = (i) => m.left + band * i + band / 2;

  const show = (i) => setActive(i);
  const hide = () => setActive(null);

  return (
    <div ref={containerRef} className="relative w-full">
      <svg width={width} height={HEIGHT} role="group" aria-label="Orders by day of the week" className="block">
        {ticks.map((tick) => (
          <g key={tick}>
            <line
              x1={m.left}
              x2={m.left + plotW}
              y1={y(tick)}
              y2={y(tick)}
              strokeWidth="1"
              className={tick === 0 ? 'stroke-ink-muted' : 'stroke-stroke'}
              strokeOpacity={tick === 0 ? 0.4 : 1}
            />
            <text x={m.left - 8} y={y(tick)} dy="0.32em" textAnchor="end" className="fill-ink-muted text-xs">
              {formatNumber(tick)}
            </text>
          </g>
        ))}

        {data.map((d, i) => {
          const isPeak = peak > 0 && d.orders === peak;
          const hovered = active === i;
          const top = y(d.orders);

          return (
            <g key={d.day}>
              {d.orders > 0 && (
                <path
                  d={columnPath(cx(i) - barW / 2, top, barW, baseline)}
                  className={isPeak ? 'fill-series' : 'fill-ink-muted'}
                  fillOpacity={isPeak ? (hovered ? 0.85 : 1) : hovered ? 0.7 : 0.4}
                />
              )}

              {isPeak && (
                <text
                  x={cx(i)}
                  y={top - 7}
                  textAnchor="middle"
                  strokeWidth="3"
                  paintOrder="stroke"
                  className="fill-ink stroke-surface text-xs font-semibold"
                >
                  {formatNumber(d.orders)}
                </text>
              )}

              <text x={cx(i)} y={HEIGHT - 8} textAnchor="middle" className="fill-ink-muted text-xs">
                {d.day}
              </text>

              {/* The hit target is the whole column slot, not just the painted bar. */}
              <rect
                x={m.left + band * i}
                y={m.top}
                width={band}
                height={plotH + m.bottom}
                fill="transparent"
                tabIndex={0}
                role="img"
                aria-label={`${d.day}: ${d.orders} ${d.orders === 1 ? 'order' : 'orders'}`}
                className="focus:outline-none focus-visible:stroke-primary"
                strokeWidth="2"
                onPointerEnter={() => show(i)}
                onPointerMove={() => show(i)}
                onPointerLeave={hide}
                onFocus={() => show(i)}
                onBlur={hide}
              />
            </g>
          );
        })}
      </svg>

      {active !== null && (
        <Tooltip left={cx(active)} top={m.top} flip={cx(active) > width / 2}>
          <div className="text-xs font-medium text-ink-muted">{data[active].day}</div>
          <div className="mt-1 flex items-baseline gap-2">
            <span className="font-semibold text-ink">{formatNumber(data[active].orders)}</span>
            <span className="text-ink-muted">{data[active].orders === 1 ? 'order' : 'orders'}</span>
          </div>
        </Tooltip>
      )}
    </div>
  );
}
