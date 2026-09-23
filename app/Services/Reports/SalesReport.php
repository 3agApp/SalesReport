<?php

namespace App\Services\Reports;

use App\Data\ReportFilters;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

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
     * The number of rows an export reads from the database at a time.
     */
    private const int EXPORT_PAGE = 500;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $summary = null;

    /**
     * @var array<string, array{date: string, label: string, orders: int, revenue: float}>|null
     */
    private ?array $buckets = null;

    /**
     * @var array{series: array<int, array<string, mixed>>, trends: array<int, array<int, float>>}|null
     */
    private ?array $overTime = null;

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
     * @return array<int, array<string, mixed>>
     */
    public function series(): array
    {
        return $this->overTime()['series'];
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

        $trends = $this->overTime()['trends'];

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
     * Get the net revenue and VAT of every shop in scope, by name, with totals.
     *
     * This is the sheet a bookkeeper works the VAT return out from, so every
     * shop in scope gets a row, including one with no orders: a zero says the
     * shop was looked at, where a missing row leaves them wondering. A shop
     * with no orders falls back to the currency it was last seen selling in.
     *
     * The totals add up the rounded rows, so they always agree with the
     * columns above them. They are left out when the shops sold in more than
     * one currency, for the same reason the rest of the report is.
     *
     * @return array{rows: array<int, array{shopId: int, name: string, currency: string, orderCount: int, netRevenue: float, tax: float}>, currency: string|null, totals: array{orderCount: int, netRevenue: float, tax: float}|null}
     */
    public function totalsByShop(): array
    {
        $totals = $this->orders()
            ->select('shop_id', 'currency')
            ->selectRaw('count(*) as order_count')
            ->selectRaw('coalesce(sum(total) - sum(refunded_total), 0) as net')
            ->selectRaw('coalesce(sum(total_tax) - sum(refunded_tax), 0) as tax')
            ->groupBy('shop_id', 'currency')
            ->toBase()
            ->get()
            ->groupBy('shop_id');

        $rows = Shop::query()
            ->whereIn('id', $this->filters->shopIds)
            ->orderByRaw('LOWER(name)')
            ->get()
            ->flatMap(function (Shop $shop) use ($totals) {
                $rows = $totals->get($shop->id, collect([(object) [
                    'currency' => $shop->currency,
                    'order_count' => 0,
                    'net' => 0,
                    'tax' => 0,
                ]]));

                return $rows->map(fn ($row) => [
                    'shopId' => $shop->id,
                    'name' => $shop->name,
                    'currency' => (string) $row->currency,
                    'orderCount' => (int) $row->order_count,
                    'netRevenue' => round((float) $row->net, 2),
                    'tax' => round((float) $row->tax, 2),
                ]);
            })
            ->values();

        // A shop with nothing to add cannot break the total, whatever it sells in.
        $currencies = $rows->where('orderCount', '>', 0)->pluck('currency')->filter()->unique()->values();
        $singleCurrency = $currencies->count() <= 1;

        return [
            'rows' => $rows->all(),
            'currency' => $singleCurrency ? ($currencies->first() ?? $rows->pluck('currency')->filter()->first() ?? '') : null,
            'totals' => $singleCurrency ? [
                'orderCount' => (int) $rows->sum('orderCount'),
                'netRevenue' => round($rows->sum('netRevenue'), 2),
                'tax' => round($rows->sum('tax'), 2),
            ] : null,
        ];
    }

    /**
     * Lay the filtered orders out over time, once.
     *
     * Buckets are built in PHP rather than in SQL because the boundaries have
     * to land in the organization's timezone, daylight saving included, which
     * no portable SQL expression gets right. That makes this the one figure on
     * the page costing a row of work per order rather than per bucket, so the
     * chart and the per-shop trends are read off a single walk. They always
     * shared a query, a range and a set of buckets; the only difference was
     * whether the total was also kept per shop.
     *
     * @return array{series: array<int, array<string, mixed>>, trends: array<int, array<int, float>>}
     */
    private function overTime(): array
    {
        if ($this->overTime !== null) {
            return $this->overTime;
        }

        $interval = $this->filters->interval();
        $buckets = $this->emptyBuckets();
        $positions = array_flip(array_keys($buckets));
        $empty = array_fill(0, count($buckets), 0.0);
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

            // Guarded on the buckets rather than their positions: the two
            // hold the same keys, and this is the one that proves the bucket
            // being added to is really there.
            if (! isset($buckets[$key])) {
                continue;
            }

            $net = (float) $row->total - (float) $row->refunded_total;
            $shopId = (int) $row->shop_id;

            $buckets[$key]['orders']++;
            $buckets[$key]['revenue'] += $net;

            $trends[$shopId] ??= $empty;
            $trends[$shopId][$positions[$key]] += $net;
        }

        $series = collect($buckets)
            ->map(fn (array $bucket) => [
                ...$bucket,
                'revenue' => round($bucket['revenue'], 2),
                'averageOrderValue' => $bucket['orders'] > 0
                    ? round($bucket['revenue'] / $bucket['orders'], 2)
                    : 0.0,
            ])
            ->values()
            ->toArray();

        return $this->overTime = [
            'series' => $series,
            'trends' => array_map(
                fn (array $values) => array_map(fn (float $value) => round($value, 2), $values),
                $trends,
            ),
        ];
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
     * Stream the filtered orders, oldest first, for an export.
     *
     * @return LazyCollection<int, Order>
     */
    public function ordersForExport(): LazyCollection
    {
        return $this->lazyInPlacedOrder($this->orders()->with('shop'), 'orders.placed_at', 'orders.id');
    }

    /**
     * Stream the filtered line items, oldest order first, for an export.
     *
     * @return LazyCollection<int, OrderItem>
     */
    public function itemsForExport(): LazyCollection
    {
        $query = $this->items()->select([
            'order_items.*',
            'orders.placed_at',
            'orders.number as order_number',
            'orders.shop_id',
            'orders.status as order_status',
            'orders.currency',
        ]);

        return $this->lazyInPlacedOrder($query, 'orders.placed_at', 'order_items.id');
    }

    /**
     * Walk an export query in date order, a page at a time.
     *
     * Keyset pagination has to page on the same columns the rows are sorted
     * by. Eloquent's lazyById pages on the primary key alone, which quietly
     * loses rows as soon as the query is sorted by anything else: the last
     * row of a page is no longer the highest id in it, so every lower id
     * still to come is skipped by the next page's `id >` bound. Two shops
     * added at different times are enough to trigger it, because their ids
     * and their order dates then run in different directions. Paging on
     * (placed_at, id) keeps the date order and every row.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LazyCollection<int, TModel>
     */
    private function lazyInPlacedOrder(Builder $query, string $placedAtColumn, string $idColumn): LazyCollection
    {
        return LazyCollection::make(function () use ($query, $placedAtColumn, $idColumn) {
            $placedAt = null;
            $id = 0;

            while (true) {
                $page = (clone $query)
                    ->when($placedAt !== null, fn (Builder $query) => $query->where(
                        fn (Builder $query) => $query
                            ->where($placedAtColumn, '>', $placedAt)
                            ->orWhere(fn (Builder $query) => $query
                                ->where($placedAtColumn, '=', $placedAt)
                                ->where($idColumn, '>', $id)),
                    ))
                    ->orderBy($placedAtColumn)
                    ->orderBy($idColumn)
                    ->limit(self::EXPORT_PAGE)
                    ->get();

                foreach ($page as $row) {
                    yield $row;
                }

                if ($page->count() < self::EXPORT_PAGE) {
                    return;
                }

                $last = $page->last();
                $placedAt = $last->getAttribute('placed_at');
                $id = $last->getKey();
            }
        });
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
