<?php

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * These cover the parts of the reports page that live in JavaScript and that a
 * feature test cannot reach: the chart, the measure toggle and the deferred
 * panels arriving after the first paint. The figures behind them are covered
 * by tests/Feature/Reports/SalesReportTest.php.
 */
test('the report renders its chart and breakdowns', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $organization->update(['timezone' => 'Europe/Zurich']);

    $shop = Shop::factory()->for($organization)->create(['name' => 'Toys Online']);

    foreach (range(0, 5) as $day) {
        Order::factory()->for($shop)->create([
            'status' => 'completed',
            'total' => 100,
            'refunded_total' => 0,
            'currency' => 'CHF',
            'placed_at' => CarbonImmutable::now('Europe/Zurich')->subDays($day)->setTime(12, 0)->utc(),
        ]);
    }

    $this->actingAs($user);

    $page = visit(route('reports.index', [$organization, 'period' => 'last_30_days']));

    $page->assertSee('Reports')
        ->assertSee('Net revenue')
        // The deferred panels land after the headline figures.
        ->assertSee('Toys Online')
        ->assertSee('Completed')
        // The comparison against the period before arrives with them.
        ->assertSee('Trend')
        ->assertNoJavaScriptErrors();
});

test('the chart switches measure without reloading the page', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create([
        'status' => 'completed',
        'total' => 250,
        'refunded_total' => 0,
        'currency' => 'CHF',
        'placed_at' => CarbonImmutable::now()->subDays(2),
    ]);

    $this->actingAs($user);

    $page = visit(route('reports.index', [$organization, 'period' => 'last_30_days']));

    $page->assertSee('Over time')
        ->click('Orders')
        ->assertSee('Over time')
        ->assertNoJavaScriptErrors();
});

/**
 * Build a report page showing one whole calendar month, so the date picker
 * opens on a known pair of months whatever today's date happens to be.
 *
 * @return array{0: User, 1: CarbonImmutable}
 */
function reportPageForLastMonth(): array
{
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $organization->update(['timezone' => 'Europe/Zurich']);

    $shop = Shop::factory()->for($organization)->create();
    $month = CarbonImmutable::now('Europe/Zurich')->subMonthNoOverflow()->startOfMonth();

    foreach ([5, 12, 20] as $day) {
        Order::factory()->for($shop)->create([
            'status' => 'completed',
            'total' => 100,
            'refunded_total' => 0,
            'currency' => 'CHF',
            'placed_at' => $month->setDay($day)->setTime(12, 0)->utc(),
        ]);
    }

    return [$user, $month];
}

test('picking a date range moves both ends of the report', function () {
    [$user, $month] = reportPageForLastMonth();

    // Mid-month days, so neither can also be drawn as a neighbouring month's
    // outside day and match the selector twice.
    $start = $month->setDay(5);
    $end = $month->setDay(12);

    $this->actingAs($user);

    $page = visit(route('reports.index', [
        $user->currentOrganization,
        'period' => 'custom',
        'from' => $month->toDateString(),
        'to' => $month->endOfMonth()->toDateString(),
    ]));

    $page->click('@report-range')
        ->click('[data-day="'.$start->toDateString().'"] button')
        ->click('[data-day="'.$end->toDateString().'"] button')
        // Both ends have to move. The picker used to only drag the end of the
        // range along, leaving the start wherever the last report left it.
        ->assertQueryStringHas('from', $start->toDateString())
        ->assertQueryStringHas('to', $end->toDateString())
        ->assertSeeIn(
            '[data-test="report-range"]',
            $start->format('j M Y').' – '.$end->format('j M Y'),
        )
        // Two of the three orders fall inside the new range.
        ->assertSee('200.00')
        ->assertNoJavaScriptErrors();
});

test('picking the same day twice reports on that one day', function () {
    [$user, $month] = reportPageForLastMonth();

    $day = $month->setDay(12);

    $this->actingAs($user);

    $page = visit(route('reports.index', [
        $user->currentOrganization,
        'period' => 'custom',
        'from' => $month->toDateString(),
        'to' => $month->endOfMonth()->toDateString(),
    ]));

    $page->click('@report-range')
        ->click('[data-day="'.$day->toDateString().'"] button')
        ->click('[data-day="'.$day->toDateString().'"] button')
        ->assertQueryStringHas('from', $day->toDateString())
        ->assertQueryStringHas('to', $day->toDateString())
        ->assertNoJavaScriptErrors();
});
