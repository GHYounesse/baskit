import BarList from '@/Components/Charts/BarList';
import ChartCard, { DataTable } from '@/Components/Charts/ChartCard';
import ColumnChart from '@/Components/Charts/ColumnChart';
import RangeFilter from '@/Components/Charts/RangeFilter';
import RevenueChart from '@/Components/Charts/RevenueChart';
import StatTile from '@/Components/Charts/StatTile';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDay } from '@/utils/date';
import { formatMoney, formatMoneyWhole, formatNumber } from '@/utils/money';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

function percentLabel(share) {
  return `${share < 10 ? share.toFixed(1).replace(/\.0$/, '') : Math.round(share)}%`;
}

export default function Index({ range, ranges, period, kpis, revenueSeries, topProducts, categories, weekdays }) {
  const [loading, setLoading] = useState(false);

  const { current, previous } = kpis;
  const comparedTo = `previous ${range} days`;
  const refundRate = current.orders ? (current.refunded_orders / current.orders) * 100 : 0;

  const changeRange = (days) => {
    router.get('/admin/analytics', { range: days }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onStart: () => setLoading(true),
      onFinish: () => setLoading(false),
    });
  };

  const productRows = topProducts.map((p) => ({
    key: p.name,
    label: p.name,
    value: p.revenue_cents,
    valueText: formatMoneyWhole(p.revenue_cents),
    details: [
      { label: 'Item sales', text: formatMoney(p.revenue_cents) },
      { label: 'Units sold', text: formatNumber(p.units) },
    ],
  }));

  const categoryRows = categories.map((c) => ({
    key: c.name,
    label: c.name,
    value: c.revenue_cents,
    valueText: percentLabel(c.share),
    details: [
      { label: 'Item sales', text: formatMoney(c.revenue_cents) },
      { label: 'Share', text: percentLabel(c.share) },
    ],
  }));

  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">Analytics</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-6xl px-6 py-8">
          <Head title="Analytics" />

          {/* One filter row for the whole page: every number and chart below follows it. */}
          <div className="flex flex-wrap items-center justify-between gap-3">
            <RangeFilter range={range} ranges={ranges} onChange={changeRange} />
            <p className="text-sm text-ink-muted">
              {formatDay(period.start)} to {formatDay(period.end, { year: true })}
            </p>
          </div>

          {/* While new data loads, keep the old picture on screen instead of flashing a skeleton. */}
          <div aria-busy={loading} className={`transition-opacity duration-150 ${loading ? 'opacity-60' : ''}`}>
            {current.orders === 0 && (
              <p className="mt-4 rounded-lg border border-dashed border-stroke px-4 py-3 text-sm text-ink-muted">
                No orders in this period. Try a longer date range.
              </p>
            )}

            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <StatTile
                hero
                label="Revenue"
                value={formatMoneyWhole(current.revenue_cents)}
                current={current.revenue_cents}
                previous={previous.revenue_cents}
                comparedTo={comparedTo}
                note="After refunds and cancellations"
              />
              <StatTile
                label="Orders placed"
                value={formatNumber(current.orders)}
                current={current.orders}
                previous={previous.orders}
                comparedTo={comparedTo}
              />
              <StatTile
                label="Average order"
                value={formatMoney(current.average_order_cents)}
                current={current.average_order_cents}
                previous={previous.average_order_cents}
                comparedTo={comparedTo}
              />
              <StatTile
                label="Refunded"
                value={formatMoneyWhole(current.refunded_cents)}
                current={current.refunded_cents}
                previous={previous.refunded_cents}
                upIsGood={false}
                comparedTo={comparedTo}
                note={
                  current.orders
                    ? `${formatNumber(current.refunded_orders)} of ${formatNumber(current.orders)} orders (${percentLabel(refundRate)})`
                    : undefined
                }
              />
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-3">
              <ChartCard
                className="lg:col-span-2"
                title="Revenue by day"
                subtitle="Daily revenue compared with the previous period"
                table={
                  <DataTable
                    caption="Revenue and orders for each day"
                    columns={[
                      { key: 'date', label: 'Date' },
                      { key: 'revenue', label: 'Revenue', numeric: true },
                      { key: 'orders', label: 'Orders', numeric: true },
                      { key: 'previous', label: 'Previous period', numeric: true },
                    ]}
                    rows={[...revenueSeries].reverse().map((d) => ({
                      date: formatDay(d.date, { year: true }),
                      revenue: formatMoney(d.revenue_cents),
                      orders: formatNumber(d.orders),
                      previous: formatMoney(d.previous_revenue_cents),
                    }))}
                  />
                }
              >
                <RevenueChart series={revenueSeries} />
              </ChartCard>

              <ChartCard
                title="Orders by weekday"
                subtitle="When customers buy"
                table={
                  <DataTable
                    caption="Orders for each day of the week"
                    columns={[
                      { key: 'day', label: 'Day' },
                      { key: 'orders', label: 'Orders', numeric: true },
                    ]}
                    rows={weekdays.map((d) => ({ day: d.day, orders: formatNumber(d.orders) }))}
                  />
                }
              >
                <ColumnChart data={weekdays} />
              </ChartCard>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <ChartCard
                title="Top products"
                subtitle="Best sellers by item sales, before discounts"
                table={
                  <DataTable
                    caption="Top products by item sales"
                    columns={[
                      { key: 'name', label: 'Product' },
                      { key: 'sales', label: 'Item sales', numeric: true },
                      { key: 'units', label: 'Units', numeric: true },
                    ]}
                    rows={topProducts.map((p) => ({
                      name: p.name,
                      sales: formatMoney(p.revenue_cents),
                      units: formatNumber(p.units),
                    }))}
                  />
                }
              >
                <BarList rows={productRows} emptyText="No products sold in this period." />
              </ChartCard>

              <ChartCard
                title="Sales by category"
                subtitle="Share of item sales"
                table={
                  <DataTable
                    caption="Item sales by category"
                    columns={[
                      { key: 'name', label: 'Category' },
                      { key: 'sales', label: 'Item sales', numeric: true },
                      { key: 'share', label: 'Share', numeric: true },
                    ]}
                    rows={categories.map((c) => ({
                      name: c.name,
                      sales: formatMoney(c.revenue_cents),
                      share: percentLabel(c.share),
                    }))}
                  />
                }
              >
                <BarList rows={categoryRows} emptyText="No sales in this period." />
              </ChartCard>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
