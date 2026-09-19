<?php

use App\Data\ReportComparison;
use App\Data\ReportFilters;
use App\Enums\ReportPeriod;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use App\Services\Reports\SalesComparison;
use App\Services\Reports\SalesReport;
use Carbon\CarbonImmutable;

/**
 * Build filters for a preset period, resolved in a fixed timezone.
 */
function presetFilters(ReportPeriod $period, Organization $organization, string $now = '2026-09-18 10:00'): ReportFilters
{
    [$from, $to] = $period->resolve('Europe/Zurich', CarbonImmutable::parse($now, 'UTC'));

    return new ReportFilters(
        period: $period,
        from: $from,
        to: $to,
        timezone: 'Europe/Zurich',
        shopIds: $organization->shops()->pluck('id')->all(),
        statuses: ReportFilters::defaultStatuses(),
    );
}

test('a part-finished month compares against the same days of the month before', function () {
    $organization = Organization::factory()->create();

    // 1-18 September should look back at 1-18 August, not at all of August:
    // comparing 18 days against 31 would show a fall that did not happen.
    $previous = presetFilters(ReportPeriod::ThisMonth, $organization)->forPreviousPeriod();

    expect($previous->from->toDateString())->toBe('2026-08-01')
        ->and($previous->to->toDateString())->toBe('2026-08-18');
});

test('a whole month compares against the whole month before, however long it is', function () {
    $organization = Organization::factory()->create();

    // August has 31 days and July has 31; the July range must not be cut short.
    $previous = presetFilters(ReportPeriod::LastMonth, $organization)->forPreviousPeriod();

    expect($previous->from->toDateString())->toBe('2026-07-01')
        ->and($previous->to->toDateString())->toBe('2026-07-31');
});

test('a whole short month compares against the whole longer month before it', function () {
    $organization = Organization::factory()->create();

    // April has 30 days, March has 31. Shifting by a month would land on
    // 30 March and quietly drop a day of March.
    $previous = presetFilters(ReportPeriod::LastMonth, $organization, '2026-05-15 10:00')->forPreviousPeriod();

    expect($previous->from->toDateString())->toBe('2026-03-01')
        ->and($previous->to->toDateString())->toBe('2026-03-31');
});

test('quarters and years step back a whole period', function () {
    $organization = Organization::factory()->create();

    $quarter = presetFilters(ReportPeriod::LastQuarter, $organization)->forPreviousPeriod();
    expect($quarter->from->toDateString())->toBe('2026-01-01')
        ->and($quarter->to->toDateString())->toBe('2026-03-31');

    $year = presetFilters(ReportPeriod::LastYear, $organization)->forPreviousPeriod();
    expect($year->from->toDateString())->toBe('2024-01-01')
        ->and($year->to->toDateString())->toBe('2024-12-31');
});

test('a rolling range steps back by its own length with no gap', function () {
    $organization = Organization::factory()->create();

    $previous = presetFilters(ReportPeriod::Last30Days, $organization)->forPreviousPeriod();

    // 20 Aug - 18 Sep looks back at 21 Jul - 19 Aug: touching, never overlapping.
    expect($previous->to->toDateString())->toBe('2026-08-19')
        ->and($previous->from->toDateString())->toBe('2026-07-21');
});

test('deltas are computed against the earlier period', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    // Two orders worth 200 last month, one worth 300 the month before.
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 100, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-08-05 12:00', 'Europe/Zurich')->utc()]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 100, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-08-20 12:00', 'Europe/Zurich')->utc()]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 300, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-07-10 12:00', 'Europe/Zurich')->utc()]);

    $filters = presetFilters(ReportPeriod::LastMonth, $organization);

    $comparison = app(SalesComparison::class)->handle($filters, new SalesReport($filters));

    expect($comparison->summary['netRevenue'])->toBe(300.0)
        // 200 against 300 is a third down.
        ->and($comparison->deltas['netRevenue'])->toBe(-33.3)
        ->and($comparison->deltas['orderCount'])->toBe(100.0)
        ->and($comparison->rangeLabel)->toBe('1 Jul 2026 – 31 Jul 2026');
});

test('growth from nothing is left blank rather than reported as infinite', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 500, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-08-05 12:00', 'Europe/Zurich')->utc()]);

    $filters = presetFilters(ReportPeriod::LastMonth, $organization);
    $previous = new SalesReport($filters->forPreviousPeriod());

    $comparison = ReportComparison::between(
        current: (new SalesReport($filters))->summary(),
        previous: $previous->summary(),
        series: $previous->series(),
        rangeLabel: 'whenever',
        partial: false,
    );

    expect($comparison->deltas['netRevenue'])->toBeNull()
        ->and($comparison->summary['netRevenue'])->toBe(0.0);
});

test('a comparison reaching back before the imported history says so', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    // The only order we hold is in August, so July is not a month the shop was
    // quiet in; it is a month we simply never imported.
    Order::factory()->for($shop)->create([
        'status' => 'completed', 'total' => 100, 'refunded_total' => 0,
        'placed_at' => CarbonImmutable::parse('2026-08-05 12:00', 'Europe/Zurich')->utc(),
    ]);

    $filters = presetFilters(ReportPeriod::LastMonth, $organization);

    expect(app(SalesComparison::class)->handle($filters, new SalesReport($filters))->partial)->toBeTrue();
});

test('a comparison inside the imported history is not flagged as partial', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 100, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-05-01 12:00', 'Europe/Zurich')->utc()]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 100, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-08-05 12:00', 'Europe/Zurich')->utc()]);

    $filters = presetFilters(ReportPeriod::LastMonth, $organization);

    expect(app(SalesComparison::class)->handle($filters, new SalesReport($filters))->partial)->toBeFalse();
});

test('each shop carries its own trend, aligned to the chart buckets', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $organization->update(['timezone' => 'Europe/Zurich']);

    $first = Shop::factory()->for($organization)->create(['name' => 'Toys Online']);
    $second = Shop::factory()->for($organization)->create(['name' => 'Tigerbox']);

    Order::factory()->for($first)->create(['status' => 'completed', 'total' => 10, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-01 12:00', 'Europe/Zurich')->utc()]);
    Order::factory()->for($first)->create(['status' => 'completed', 'total' => 30, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-03 12:00', 'Europe/Zurich')->utc()]);
    Order::factory()->for($second)->create(['status' => 'completed', 'total' => 50, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-02 12:00', 'Europe/Zurich')->utc()]);

    $filters = new ReportFilters(
        period: ReportPeriod::Custom,
        from: CarbonImmutable::parse('2026-03-01', 'Europe/Zurich')->startOfDay(),
        to: CarbonImmutable::parse('2026-03-03', 'Europe/Zurich')->endOfDay(),
        timezone: 'Europe/Zurich',
        shopIds: $organization->shops()->pluck('id')->all(),
        statuses: ReportFilters::defaultStatuses(),
    );

    $byShop = collect((new SalesReport($filters))->byShop())->keyBy('name');

    expect($byShop['Toys Online']['trend'])->toBe([10.0, 0.0, 30.0])
        ->and($byShop['Tigerbox']['trend'])->toBe([0.0, 50.0, 0.0]);
});
