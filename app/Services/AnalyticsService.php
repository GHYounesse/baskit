<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind the admin analytics page.
 *
 * Every figure covers the last N whole days (today included) and is compared
 * with the N days just before. Sales never include cancelled or refunded
 * orders, since that money went back to the customer.
 */
class AnalyticsService
{
    private const WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /**
     * @return array<string, mixed>
     */
    public function report(int $days): array
    {
        $end = now()->endOfDay()->toImmutable();
        $start = $end->subDays($days - 1)->startOfDay();
        $previousStart = $start->subDays($days);

        $orders = Order::whereBetween('created_at', [$previousStart, $end])
            ->get(['id', 'total_cents', 'status', 'created_at']);

        $current = $orders->filter(fn (Order $o) => $o->created_at->gte($start));
        $previous = $orders->filter(fn (Order $o) => $o->created_at->lt($start));

        return [
            'period' => [
                'days' => $days,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'kpis' => [
                'current' => $this->summarize($current),
                'previous' => $this->summarize($previous),
            ],
            'revenueSeries' => $this->dailySeries($current, $previous, $start, $previousStart, $days),
            'topProducts' => $this->topProducts($start, $end),
            'categories' => $this->categories($start, $end),
            'weekdays' => $this->weekdays($current),
        ];
    }

    /**
     * @return array{revenue_cents: int, orders: int, sales_orders: int, average_order_cents: int, refunded_cents: int, refunded_orders: int}
     */
    private function summarize(Collection $orders): array
    {
        [$returned, $sales] = $orders->partition(fn (Order $o) => $this->isReturned($o));

        $revenue = (int) $sales->sum('total_cents');

        return [
            'revenue_cents' => $revenue,
            'orders' => $orders->count(),
            'sales_orders' => $sales->count(),
            'average_order_cents' => $sales->isEmpty() ? 0 : intdiv($revenue, $sales->count()),
            'refunded_cents' => (int) $returned->sum('total_cents'),
            'refunded_orders' => $returned->count(),
        ];
    }

    /**
     * One row per day, zero-filled so quiet days show as zero instead of
     * disappearing, each paired with the same-position day of the previous period.
     *
     * @return list<array<string, mixed>>
     */
    private function dailySeries(Collection $current, Collection $previous, $start, $previousStart, int $days): array
    {
        $byDay = fn (Collection $orders) => $orders
            ->reject(fn (Order $o) => $this->isReturned($o))
            ->groupBy(fn (Order $o) => $o->created_at->toDateString());

        $currentByDay = $byDay($current);
        $previousByDay = $byDay($previous);

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->toDateString();
            $previousDate = $previousStart->addDays($i)->toDateString();

            $series[] = [
                'date' => $date,
                'revenue_cents' => (int) ($currentByDay[$date] ?? collect())->sum('total_cents'),
                'orders' => ($currentByDay[$date] ?? collect())->count(),
                'previous_date' => $previousDate,
                'previous_revenue_cents' => (int) ($previousByDay[$previousDate] ?? collect())->sum('total_cents'),
            ];
        }

        return $series;
    }

    /**
     * Best sellers by item sales (before any coupon, which belongs to the
     * order as a whole, not to one product).
     *
     * @return list<array{name: string, revenue_cents: int, units: int}>
     */
    private function topProducts($start, $end): array
    {
        return $this->salesItems($start, $end)
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_name as name, SUM(order_items.unit_price_cents * order_items.quantity) as revenue_cents, SUM(order_items.quantity) as units')
            ->orderByDesc('revenue_cents')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'revenue_cents' => (int) $row->revenue_cents,
                'units' => (int) $row->units,
            ])
            ->all();
    }

    /**
     * @return list<array{name: string, revenue_cents: int, share: float}>
     */
    private function categories($start, $end): array
    {
        $rows = $this->salesItems($start, $end)
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy(DB::raw("COALESCE(categories.name, 'Uncategorized')"))
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as name, SUM(order_items.unit_price_cents * order_items.quantity) as revenue_cents")
            ->orderByDesc('revenue_cents')
            ->orderBy('name')
            ->get();

        $total = (int) $rows->sum('revenue_cents');

        return $rows->map(fn ($row) => [
            'name' => $row->name,
            'revenue_cents' => (int) $row->revenue_cents,
            'share' => $total > 0 ? round($row->revenue_cents / $total * 100, 1) : 0.0,
        ])->all();
    }

    /**
     * Orders placed on each day of the week, Monday first, every day present.
     *
     * @return list<array{day: string, orders: int}>
     */
    private function weekdays(Collection $current): array
    {
        $counts = $current
            ->reject(fn (Order $o) => $this->isReturned($o))
            ->countBy(fn (Order $o) => $o->created_at->dayOfWeekIso);

        return collect(self::WEEKDAYS)
            ->map(fn (string $day, int $iso) => ['day' => $day, 'orders' => (int) ($counts[$iso] ?? 0)])
            ->values()
            ->all();
    }

    /**
     * Line items belonging to sales (not returned orders) in the period.
     */
    private function salesItems($start, $end)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereNotIn('orders.status', Order::RETURNED_STATUSES);
    }

    private function isReturned(Order $order): bool
    {
        return in_array($order->status, Order::RETURNED_STATUSES, true);
    }
}
