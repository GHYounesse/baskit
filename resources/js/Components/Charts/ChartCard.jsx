import { useState } from 'react';

/**
 * Card around one chart: title and what it measures, a Chart / Table switch
 * (the table is the accessible twin of the chart, with every value in it),
 * and the chart itself.
 */
export default function ChartCard({ title, subtitle, table, children, className = '' }) {
  const [view, setView] = useState('chart');

  return (
    <section className={`min-w-0 rounded-lg border border-stroke bg-surface p-4 sm:p-5 ${className}`}>
      <header className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="font-display text-lg font-bold leading-tight text-ink">{title}</h2>
          {subtitle && <p className="mt-0.5 text-sm text-ink-muted">{subtitle}</p>}
        </div>

        {table && (
          <div role="group" aria-label={`${title} view`} className="inline-flex shrink-0 rounded-lg bg-canvas p-0.5">
            {['chart', 'table'].map((mode) => (
              <button
                key={mode}
                type="button"
                aria-pressed={view === mode}
                onClick={() => setView(mode)}
                className={`rounded-md px-2.5 py-1.5 text-xs font-medium capitalize transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary ${
                  view === mode ? 'bg-surface text-ink shadow-sm ring-1 ring-inset ring-stroke' : 'text-ink-muted hover:text-ink'
                }`}
              >
                {mode}
              </button>
            ))}
          </div>
        )}
      </header>

      <div className="mt-4">{view === 'table' && table ? table : children}</div>
    </section>
  );
}

/**
 * The table twin of a chart. Scrolls inside its card, header stays put.
 */
export function DataTable({ columns, rows, caption }) {
  return (
    <div className="max-h-80 overflow-auto rounded-lg border border-stroke">
      <table className="w-full text-sm">
        <caption className="sr-only">{caption}</caption>
        <thead className="sticky top-0 bg-surface">
          <tr className="border-b border-stroke text-left text-ink-muted">
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className={`p-2.5 font-medium ${column.numeric ? 'text-right' : ''}`}
              >
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr key={i} className="border-b border-stroke last:border-b-0">
              {columns.map((column, c) => {
                const Cell = c === 0 ? 'th' : 'td';

                return (
                  <Cell
                    key={column.key}
                    scope={c === 0 ? 'row' : undefined}
                    className={`p-2.5 text-left font-normal ${c === 0 ? 'text-ink' : 'text-ink-muted'} ${
                      column.numeric ? 'text-right tabular-nums' : ''
                    }`}
                  >
                    {row[column.key]}
                  </Cell>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
