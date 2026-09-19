<?php

namespace App\Services\Reports;

use App\Data\ReportFilters;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Answers the questions a bookkeeper brings to the order data.
 *
 * Every figure here comes from one filtered set of orders, so the summary, the
 * chart and the breakdowns can never disagree with each other.
 */
class SalesReport
{
    /**
     * The number of products listed in the top products table.
     */
    private const int TOP_PRODUCTS = 10;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $summary = null;

    /**
     * @var array<string, array{date: string, label: string, orders: int, revenue: float}>|null
     */
    private ?array $buckets = null;

    public function __construct(private ReportFilters $filters) {}

    /**
     * Get the headline figures for the range.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->summary ??= $this->buildSummary();
    }

    /**
     * Get the earliest order held for the shops in scope.
     *
     * A comparison reaching back before this is comparing against orders that
     * were simply never imported, which is worth saying out loud.
     */
    public function earliestOrderAt(): ?CarbonImmutable
    {
        $earliest = Order::query()
            ->whereIn('shop_id', $this->filters->shopIds)
            ->min('placed_at');

        return is_string($earliest) ? CarbonImmutable::parse($earliest, 'UTC') : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(): array
    {
        $row = $this->orders()
            ->selectRaw('count(*) as order_count')
            ->selectRaw('coalesce(sum(total), 0) as gross')
            ->selectRaw('coalesce(sum(refunded_total), 0) as refunded')
            ->selectRaw('coalesce(sum(total_tax) - sum(refunded_tax), 0) as tax')
            ->selectRaw('coalesce(sum(shipping_total), 0) as shipping')
            ->selectRaw('coalesce(sum(discount_total), 0) as discount')
            ->toBase()
            ->first();

        $orderCount = (int) ($row->order_count ?? 0);
        $gross = (float) ($row->gross ?? 0);
        $refunded = (float) ($row->refunded ?? 0);
        $net = $gross - $refunded;

        return [
            'orderCount' => $orderCount,
            'grossRevenue' => round($gross, 2),
            'refunded' => round($refunded, 2),
            'netRevenue' => round($net, 2),
            'tax' => round((float) ($row->tax ?? 0), 2),
            'shipping' => round((float) ($row->shipping ?? 0), 2),
            'discount' => round((float) ($row->discount ?? 0), 2),
            'averageOrderValue' => $orderCount > 0 ? round($net / $orderCount, 2) : 0.0,
            'itemsSold' => (int) $this->items()->sum('order_items.quantity'),
            'currency' => $this->currency(),
        ];
    }

    /**
     * Get revenue, orders and average order value over time.
     *
     * Buckets are built in PHP rather than in SQL because the boundaries have
     * to land in the organization's timezone, daylight saving included, which
     * no portable SQL expression gets right.
     *
     * @return array<int, array<string, mixed>>
     */
    public function series(): array
    {
        $interval = $this->filters->interval();
        $buckets = $this->emptyBuckets();

        $rows = $this->orders()
            ->select(['placed_at', 'total', 'refunded_total'])
            ->toBase()
            ->cursor();

        foreach ($rows as $row) {
            if ($row->placed_at === null) {
                continue;
            }

            $key = $interval
                ->startOf(CarbonImmutable::parse($row->placed_at, 'UTC')->setTimezone($this->filters->timezone))
                ->toDateString();

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['orders']++;
            $buckets[$key]['revenue'] += (float) $row->total - (float) $row->refunded_total;
        }

        return collect($buckets)
            ->map(fn (array $bucket) => [
                ...$bucket,
                'revenue' => round($bucket['revenue'], 2),
                'averageOrderValue' => $bucket['orders'] > 0
                    ? round($bucket['revenue'] / $bucket['orders'], 2)
                    : 0.0,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get the totals for each shop in scope, busiest first.
     *
     * Each row carries its own small series, so the table can show which way a
     * shop is heading without a second chart full of crossing lines.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byShop(): array
    {
        $names = Shop::query()
            ->whereIn('id', $this->filters->shopIds)
            ->pluck('name', 'id');

        $trends = $this->revenueByShopOverTime();

        return $this->orders()
            ->select('shop_id')
            ->selectRaw('count(*) as order_count')
            ->selectRaw('coalesce(sum(total) - sum(refunded_total), 0) as net')
            ->groupBy('shop_id')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'shopId' => (int) $row->shop_id,
                'name' => $names[$row->shop_id] ?? 'Unknown shop',
                'orderCount' => (int) $row->order_count,
                'netRevenue' => round((float) $row->net, 2),
                'trend' => $trends[(int) $row->shop_id] ?? [],
            ])
            ->sortByDesc('netRevenue')
            ->values()
            ->toArray();
    }

    /**
     * Get each shop's revenue per bucket, on the same buckets as the series.
     *
     * @return array<int, array<int, float>>
     */
    private function revenueByShopOverTime(): array
    {
        $interval = $this->filters->interval();
        $buckets = $this->emptyBuckets();
        $empty = array_fill(0, count($buckets), 0.0);
        $positions = array_flip(array_keys($buckets));
        $trends = [];

        $rows = $this->orders()
            ->select(['shop_id', 'placed_at', 'total', 'refunded_total'])
            ->toBase()
            ->cursor();

        foreach ($rows as $row) {
            if ($row->placed_at === null) {
                continue;
            }

            $key = $interval
                ->startOf(CarbonImmutable::parse($row->placed_at, 'UTC')->setTimezone($this->filters->timezone))
                ->toDateString();

            if (! isset($positions[$key])) {
                continue;
            }

            $shopId = (int) $row->shop_id;
            $trends[$shopId] ??= $empty;
            $trends[$shopId][$positions[$key]] += (float) $row->total - (float) $row->refunded_total;
        }

        return array_map(
            fn (array $values) => array_map(fn (float $value) => round($value, 2), $values),
            $trends,
        );
    }

    /**
     * Get the best selling products in the range.
     *
     * Products are grouped by SKU where there is one, so a product that was
     * renamed part way through the range still counts as one line.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topProducts(): array
    {
        return $this->items()
            ->selectRaw('coalesce(nullif(order_items.sku, \'\'), order_items.name) as product_key')
            ->selectRaw('max(order_items.name) as name')
            ->selectRaw('max(order_items.sku) as sku')
            ->selectRaw('sum(order_items.quantity) as quantity')
            // On the same footing as the headline figure, which counts
            // the order total the customer paid, tax included. A line
            // item's own total leaves tax out, so adding it back is what
            // stops this panel quietly summing to less than the total.
            ->selectRaw('coalesce(sum(order_items.total) + sum(order_items.total_tax), 0) as revenue')
            ->groupBy('product_key')
            ->orderByDesc('revenue')
            ->limit(self::TOP_PRODUCTS)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'sku' => $row->sku !== '' ? $row->sku : null,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->toArray();
    }

    /**
     * Get every status seen in the range, whether or not it is being counted.
     *
     * This is what stops the report hiding its own assumptions: a bookkeeper
     * can see how much is sitting in on-hold and decide whether to include it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byStatus(): array
    {
        return $this->baseQuery(withStatusFilter: false)
            ->select('status')
            ->selectRaw('count(*) as order_count')
            ->selectRaw('coalesce(sum(total) - sum(refunded_total), 0) as net')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'status' => (string) $row->status,
                'orderCount' => (int) $row->order_count,
                'netRevenue' => round((float) $row->net, 2),
                'counted' => in_array($row->status, $this->filters->statuses, true),
            ])
            ->sortByDesc('netRevenue')
            ->values()
            ->toArray();
    }

    /**
     * Get the filtered orders, for streaming into an export.
     *
     * @return Builder<Order>
     */
    public function ordersForExport(): Builder
    {
        return $this->orders()->with('shop')->orderBy('placed_at');
    }

    /**
     * Get the filtered line items, for streaming into an export.
     *
     * @return Builder<OrderItem>
     */
    public function itemsForExport(): Builder
    {
        return $this->items()
            ->select(['order_items.*', 'orders.placed_at', 'orders.number as order_number', 'orders.shop_id', 'orders.status as order_status', 'orders.currency'])
            ->orderBy('orders.placed_at');
    }

    /**
     * Build the set of orders every figure is drawn from.
     *
     * @return Builder<Order>
     */
    private function orders(): Builder
    {
        return $this->baseQuery();
    }

    /**
     * @return Builder<Order>
     */
    private function baseQuery(bool $withStatusFilter = true): Builder
    {
        return Order::query()
            ->whereIn('shop_id', $this->filters->shopIds)
            ->whereBetween('placed_at', $this->filters->utcRange())
            ->when($withStatusFilter, fn (Builder $query) => $query->whereIn('status', $this->filters->statuses));
    }

    /**
     * Build the line items belonging to the filtered orders.
     *
     * @return Builder<OrderItem>
     */
    private function items(): Builder
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.shop_id', $this->filters->shopIds)
            ->whereBetween('orders.placed_at', $this->filters->utcRange())
            ->whereIn('orders.status', $this->filters->statuses);
    }

    /**
     * Build one empty bucket per interval across the range, so a quiet day
     * still shows as a zero rather than vanishing from the chart.
     *
     * @return array<string, array{date: string, label: string, orders: int, revenue: float}>
     */
    private function emptyBuckets(): array
    {
        if ($this->buckets !== null) {
            return $this->buckets;
        }

        $interval = $this->filters->interval();
        $cursor = $interval->startOf($this->filters->from);
        $buckets = [];

        while ($cursor->lessThanOrEqualTo($this->filters->to)) {
            $buckets[$cursor->toDateString()] = [
                'date' => $cursor->toDateString(),
                'label' => $interval->labelFor($cursor),
                'orders' => 0,
                'revenue' => 0.0,
            ];

            $cursor = $interval->next($cursor);
        }

        return $this->buckets = $buckets;
    }

    /**
     * Get the currency the figures are in.
     *
     * Shops so far are single-currency; if a range ever mixes them the report
     * says so rather than adding francs to euros behind the reader's back.
     */
    private function currency(): string
    {
        $currencies = $this->currencies();

        return count($currencies) === 1 ? $currencies[0] : '';
    }

    /**
     * Get every currency the filtered orders were taken in.
     *
     * More than one means there is no honest total to show: two currencies
     * cannot be added together without a rate, and inventing one would put a
     * number in front of a bookkeeper that reconciles against nothing.
     *
     * @return array<int, string>
     */
    public function currencies(): array
    {
        /** @var Collection<int, string> $currencies */
        $currencies = $this->orders()->toBase()->distinct()->orderBy('currency')->pluck('currency');

        return $currencies->filter()->values()->all();
    }
}
