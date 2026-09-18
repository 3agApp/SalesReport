<?php

use App\Data\ReportFilters;
use App\Enums\ReportInterval;
use App\Enums\ReportPeriod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Shop;
use App\Services\Reports\SalesReport;
use Carbon\CarbonImmutable;

/**
 * Build filters over every shop in an organization for a fixed range.
 *
 * @param  array<string>|null  $statuses
 */
function filtersFor(Organization $organization, string $from, string $to, ?array $statuses = null, string $timezone = 'Europe/Zurich'): ReportFilters
{
    return new ReportFilters(
        period: ReportPeriod::Custom,
        from: CarbonImmutable::parse($from, $timezone)->startOfDay(),
        to: CarbonImmutable::parse($to, $timezone)->endOfDay(),
        timezone: $timezone,
        shopIds: $organization->shops()->pluck('id')->all(),
        statuses: $statuses ?? ReportFilters::defaultStatuses(),
    );
}

test('the summary adds up the orders in range and nets off refunds', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create([
        'status' => 'completed',
        'total' => 100, 'total_tax' => 7.7, 'shipping_total' => 5, 'discount_total' => 2,
        'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich'),
    ]);
    Order::factory()->for($shop)->create([
        'status' => 'processing',
        'total' => 50, 'total_tax' => 3.85, 'shipping_total' => 0, 'discount_total' => 0,
        'refunded_total' => 10, 'placed_at' => CarbonImmutable::parse('2026-03-20 12:00', 'Europe/Zurich'),
    ]);
    // Outside the range, and a status that is not counted.
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 999, 'placed_at' => CarbonImmutable::parse('2026-02-01 12:00', 'Europe/Zurich')]);
    Order::factory()->for($shop)->create(['status' => 'cancelled', 'total' => 500, 'placed_at' => CarbonImmutable::parse('2026-03-15 12:00', 'Europe/Zurich')]);

    $summary = (new SalesReport(filtersFor($organization, '2026-03-01', '2026-03-31')))->summary();

    expect($summary['orderCount'])->toBe(2)
        ->and($summary['grossRevenue'])->toBe(150.0)
        ->and($summary['refunded'])->toBe(10.0)
        ->and($summary['netRevenue'])->toBe(140.0)
        ->and($summary['tax'])->toBe(11.55)
        ->and($summary['shipping'])->toBe(5.0)
        ->and($summary['discount'])->toBe(2.0)
        ->and($summary['averageOrderValue'])->toBe(70.0);
});

test('on-hold orders are left out of revenue but still shown by status', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 100, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich')]);
    Order::factory()->for($shop)->create(['status' => 'on-hold', 'total' => 400, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-11 12:00', 'Europe/Zurich')]);

    $report = new SalesReport(filtersFor($organization, '2026-03-01', '2026-03-31'));

    expect($report->summary()['netRevenue'])->toBe(100.0);

    $byStatus = collect($report->byStatus())->keyBy('status');

    // The breakdown ignores the status filter on purpose, so the reader can
    // see what is being excluded rather than having to guess.
    expect($byStatus)->toHaveCount(2)
        ->and($byStatus['completed']['counted'])->toBeTrue()
        ->and($byStatus['on-hold']['counted'])->toBeFalse()
        ->and($byStatus['on-hold']['netRevenue'])->toBe(400.0);
});

test('a month is bounded by the organization timezone, not utc', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    // 23:30 Zurich on 31 March is 21:30 UTC the same day; 00:30 Zurich on
    // 1 April is 22:30 UTC on 31 March. Bounding in UTC would put the second
    // order in March, where a Swiss bookkeeper would not expect it.
    Order::factory()->for($shop)->create([
        'status' => 'completed', 'total' => 10, 'refunded_total' => 0,
        'placed_at' => CarbonImmutable::parse('2026-03-31 23:30', 'Europe/Zurich')->utc(),
    ]);
    Order::factory()->for($shop)->create([
        'status' => 'completed', 'total' => 20, 'refunded_total' => 0,
        'placed_at' => CarbonImmutable::parse('2026-04-01 00:30', 'Europe/Zurich')->utc(),
    ]);

    $march = (new SalesReport(filtersFor($organization, '2026-03-01', '2026-03-31')))->summary();
    $april = (new SalesReport(filtersFor($organization, '2026-04-01', '2026-04-30')))->summary();

    expect($march['netRevenue'])->toBe(10.0)
        ->and($april['netRevenue'])->toBe(20.0);
});

test('the time series buckets by local day and keeps quiet days as zeroes', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 30, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-01 10:00', 'Europe/Zurich')->utc()]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 70, 'refunded_total' => 20, 'placed_at' => CarbonImmutable::parse('2026-03-01 18:00', 'Europe/Zurich')->utc()]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 40, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-03 09:00', 'Europe/Zurich')->utc()]);

    $series = (new SalesReport(filtersFor($organization, '2026-03-01', '2026-03-03')))->series();

    expect($series)->toHaveCount(3)
        ->and($series[0])->toMatchArray(['date' => '2026-03-01', 'orders' => 2, 'revenue' => 80.0])
        ->and($series[1])->toMatchArray(['date' => '2026-03-02', 'orders' => 0, 'revenue' => 0.0])
        ->and($series[2])->toMatchArray(['date' => '2026-03-03', 'orders' => 1, 'revenue' => 40.0])
        ->and($series[0]['averageOrderValue'])->toBe(40.0);
});

test('an order placed late in the evening lands on the local day', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    // 23:30 Zurich on 2 March is 22:30 UTC the same day, so even UTC agrees;
    // 00:30 Zurich on 3 March is 23:30 UTC on 2 March, where it does not.
    Order::factory()->for($shop)->create([
        'status' => 'completed', 'total' => 15, 'refunded_total' => 0,
        'placed_at' => CarbonImmutable::parse('2026-03-03 00:30', 'Europe/Zurich')->utc(),
    ]);

    $series = collect((new SalesReport(filtersFor($organization, '2026-03-01', '2026-03-03')))->series())->keyBy('date');

    expect($series['2026-03-02']['orders'])->toBe(0)
        ->and($series['2026-03-03']['orders'])->toBe(1);
});

test('long ranges are bucketed by month rather than by day', function () {
    $organization = Organization::factory()->create();
    Shop::factory()->for($organization)->create();

    $filters = filtersFor($organization, '2025-01-01', '2026-12-31');

    expect($filters->interval())->toBe(ReportInterval::Month);

    $series = (new SalesReport($filters))->series();

    expect($series)->toHaveCount(24)
        ->and($series[0]['label'])->toBe('Jan 2025');
});

test('shops are broken out and only the ones in scope are counted', function () {
    $organization = Organization::factory()->create();
    $first = Shop::factory()->for($organization)->create(['name' => 'Toys Online']);
    $second = Shop::factory()->for($organization)->create(['name' => 'Tigerbox']);
    $other = Shop::factory()->create(['name' => 'Someone Else']);

    Order::factory()->for($first)->create(['status' => 'completed', 'total' => 100, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich')]);
    Order::factory()->for($second)->create(['status' => 'completed', 'total' => 250, 'refunded_total' => 50, 'placed_at' => CarbonImmutable::parse('2026-03-11 12:00', 'Europe/Zurich')]);
    Order::factory()->for($other)->create(['status' => 'completed', 'total' => 999, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-12 12:00', 'Europe/Zurich')]);

    $byShop = (new SalesReport(filtersFor($organization, '2026-03-01', '2026-03-31')))->byShop();

    expect($byShop)->toHaveCount(2)
        ->and($byShop[0])->toMatchArray(['name' => 'Tigerbox', 'orderCount' => 1, 'netRevenue' => 200.0])
        ->and($byShop[1])->toMatchArray(['name' => 'Toys Online', 'orderCount' => 1, 'netRevenue' => 100.0]);
});

test('top products group by sku and survive a rename', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    $first = Order::factory()->for($shop)->create(['status' => 'completed', 'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich')]);
    $second = Order::factory()->for($shop)->create(['status' => 'completed', 'placed_at' => CarbonImmutable::parse('2026-03-11 12:00', 'Europe/Zurich')]);
    $excluded = Order::factory()->for($shop)->create(['status' => 'cancelled', 'placed_at' => CarbonImmutable::parse('2026-03-12 12:00', 'Europe/Zurich')]);

    OrderItem::factory()->for($first)->create(['sku' => 'TRAIN-1', 'name' => 'Wooden train', 'quantity' => 2, 'total' => 60]);
    OrderItem::factory()->for($second)->create(['sku' => 'TRAIN-1', 'name' => 'Wooden train set', 'quantity' => 1, 'total' => 30]);
    OrderItem::factory()->for($second)->create(['sku' => 'TRACK-1', 'name' => 'Track', 'quantity' => 5, 'total' => 25]);
    OrderItem::factory()->for($excluded)->create(['sku' => 'TRAIN-1', 'name' => 'Wooden train', 'quantity' => 9, 'total' => 900]);

    $top = (new SalesReport(filtersFor($organization, '2026-03-01', '2026-03-31')))->topProducts();

    expect($top)->toHaveCount(2)
        ->and($top[0])->toMatchArray(['sku' => 'TRAIN-1', 'quantity' => 3, 'revenue' => 90.0])
        ->and($top[1])->toMatchArray(['sku' => 'TRACK-1', 'quantity' => 5, 'revenue' => 25.0]);
});

test('a range mixing currencies says so instead of adding them together', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'completed', 'currency' => 'CHF', 'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich')]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'currency' => 'EUR', 'placed_at' => CarbonImmutable::parse('2026-03-11 12:00', 'Europe/Zurich')]);

    expect((new SalesReport(filtersFor($organization, '2026-03-01', '2026-03-31')))->summary()['currency'])->toBe('MIXED');
});

test('period presets resolve in the given timezone', function () {
    $now = CarbonImmutable::parse('2026-03-15 08:00', 'UTC');

    // A period still running stops today rather than running on into empty
    // future days, so it can be compared like for like with the one before.
    [$from, $to] = ReportPeriod::ThisMonth->resolve('Europe/Zurich', $now);

    expect($from->toIso8601String())->toBe('2026-03-01T00:00:00+01:00')
        ->and($to->toIso8601String())->toBe('2026-03-15T23:59:59+01:00');

    [$from, $to] = ReportPeriod::LastMonth->resolve('Europe/Zurich', $now);

    expect($from->toDateString())->toBe('2026-02-01')
        ->and($to->toDateString())->toBe('2026-02-28');

    [$from, $to] = ReportPeriod::ThisQuarter->resolve('Europe/Zurich', $now);

    expect($from->toDateString())->toBe('2026-01-01')
        ->and($to->toDateString())->toBe('2026-03-15');

    [$from, $to] = ReportPeriod::ThisYear->resolve('Europe/Zurich', $now);

    expect($from->toDateString())->toBe('2026-01-01')
        ->and($to->toDateString())->toBe('2026-03-15');
});
