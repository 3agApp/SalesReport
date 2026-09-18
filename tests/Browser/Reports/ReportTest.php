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
