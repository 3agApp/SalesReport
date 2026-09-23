<?php

use App\Data\ReportFilters;
use App\Enums\OrganizationRole;
use App\Enums\ReportPeriod;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use App\Services\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('members can see the reports page', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $shop = Shop::factory()->for($organization)->create();
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 100, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($member)
        ->get(route('reports.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('summary.orderCount', 1)
            ->where('summary.netRevenue', 100)
            ->has('filters.rangeLabel')
            ->has('periods')
        );
});

test('guests are redirected to the login page', function () {
    $organization = Organization::factory()->create();

    $this->get(route('reports.index', $organization))->assertRedirect(route('login'));
});

test('users cannot see the reports of an organization they do not belong to', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($user)->get(route('reports.index', $organization))->assertForbidden();
});

test('a shop id from another organization cannot widen the report', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $ours = Shop::factory()->for($organization)->create();
    $theirs = Shop::factory()->create();

    Order::factory()->for($ours)->create(['status' => 'completed', 'total' => 10, 'refunded_total' => 0, 'placed_at' => now()]);
    Order::factory()->for($theirs)->create(['status' => 'completed', 'total' => 9999, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$organization, 'shops' => [$theirs->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Falling back to every shop the viewer owns, never the other one.
            ->where('summary.netRevenue', 10)
        );
});

test('the heavy breakdowns are deferred until the page has painted', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('reports.index', $user->currentOrganization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('summary')
            ->missing('series')
            ->missing('topProducts')
        );
});

test('a custom range is honoured', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 10, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich')->utc()]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 20, 'refunded_total' => 0, 'placed_at' => CarbonImmutable::parse('2026-05-10 12:00', 'Europe/Zurich')->utc()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$organization, 'period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('summary.netRevenue', 10));
});

test('a malformed date is rejected rather than silently ignored', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$user->currentOrganization, 'period' => 'custom', 'from' => 'whenever']))
        ->assertSessionHasErrors('from');
});

test('the organization timezone decides where a month ends', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $organization->update(['timezone' => 'Europe/Zurich']);

    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create([
        'status' => 'completed', 'total' => 25, 'refunded_total' => 0,
        'placed_at' => CarbonImmutable::parse('2026-04-01 00:30', 'Europe/Zurich')->utc(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$organization, 'period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertInertia(fn (Assert $page) => $page->where('summary.netRevenue', 0));

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$organization, 'period' => 'custom', 'from' => '2026-04-01', 'to' => '2026-04-30']))
        ->assertInertia(fn (Assert $page) => $page->where('summary.netRevenue', 25));
});

test('a status the shop invented is offered as a filter', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    // WooCommerce stores register their own statuses: these two are live on
    // the real shops this was built against.
    Order::factory()->for($shop)->create(['status' => 'partial-complete', 'total' => 40, 'refunded_total' => 0, 'placed_at' => now()]);
    Order::factory()->for($shop)->create(['status' => 'planzer-transmit', 'total' => 60, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusOptions', fn (Collection $options) => $options
                ->pluck('value')
                ->contains('partial-complete')
            )
            ->where('statusOptions', fn (Collection $options) => $options
                ->pluck('value')
                ->contains('planzer-transmit')
            )
        );
});

test('the filter offers exactly the statuses the orders use', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 10, 'refunded_total' => 0, 'placed_at' => now()]);
    Order::factory()->for($shop)->create(['status' => 'cancelled', 'total' => 10, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Not a guessed list of WooCommerce's own: nothing is offered
            // that no order has ever been in.
            ->where('statusOptions', fn (Collection $options) => $options
                ->pluck('value')
                ->sort()
                ->values()
                ->all() === ['cancelled', 'completed']
            )
        );
});

test('an organization with no orders is offered no statuses', function () {
    $user = User::factory()->create();
    Shop::factory()->for($user->currentOrganization)->create();

    $this
        ->actingAs($user)
        ->get(route('reports.index', $user->currentOrganization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('statusOptions', []));
});

test('a report can be counted by a status the shop invented', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'partial-complete', 'total' => 40, 'refunded_total' => 0, 'placed_at' => now()]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 60, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$organization, 'statuses' => ['partial-complete']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.statuses', ['partial-complete'])
            ->where('summary.orderCount', 1)
            ->where('summary.netRevenue', 40)
        );
});

test('cancelled and failed orders can be counted when asked for', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'cancelled', 'total' => 25, 'refunded_total' => 0, 'placed_at' => now()]);
    Order::factory()->for($shop)->create(['status' => 'failed', 'total' => 15, 'refunded_total' => 0, 'placed_at' => now()]);
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 99, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$organization, 'statuses' => ['cancelled', 'failed']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.statuses', fn (Collection $statuses) => $statuses->sort()->values()->all() === ['cancelled', 'failed'])
            ->where('summary.orderCount', 2)
            ->where('summary.netRevenue', 40)
        );
});

test('a status no shop of the organization uses falls back to the default', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 100, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$organization, 'statuses' => ['not-a-real-status']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Narrowed to what the orders actually use: the organization has
            // nothing in "processing", so claiming to count it would be a lie.
            ->where('filters.statuses', ['completed'])
            ->where('summary.orderCount', 1)
        );
});

test('a range spanning two currencies shows no figures at all', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $swiss = Shop::factory()->for($organization)->create(['currency' => 'CHF']);
    $german = Shop::factory()->for($organization)->create(['currency' => 'EUR']);

    Order::factory()->for($swiss)->create(['status' => 'completed', 'currency' => 'CHF', 'total' => 100, 'refunded_total' => 0, 'placed_at' => now()]);
    Order::factory()->for($german)->create(['status' => 'completed', 'currency' => 'EUR', 'total' => 100, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencyConflict', ['CHF', 'EUR'])
            // No total is offered, because adding the two together would need
            // a rate and would reconcile against neither set of books.
            ->missing('summary')
            ->has('filters')
        );
});

test('narrowing to shops sharing a currency brings the figures back', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $swiss = Shop::factory()->for($organization)->create(['currency' => 'CHF']);
    $german = Shop::factory()->for($organization)->create(['currency' => 'EUR']);

    Order::factory()->for($swiss)->create(['status' => 'completed', 'currency' => 'CHF', 'total' => 100, 'refunded_total' => 0, 'placed_at' => now()]);
    Order::factory()->for($german)->create(['status' => 'completed', 'currency' => 'EUR', 'total' => 999, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', [$organization, 'shops' => [$swiss->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('currencyConflict')
            ->where('summary.currency', 'CHF')
            ->where('summary.netRevenue', 100)
        );
});

test('the shops page says when its shops disagree on currency', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    Shop::factory()->for($organization)->create(['currency' => 'CHF']);
    Shop::factory()->for($organization)->create(['currency' => 'EUR']);

    $this
        ->actingAs($user)
        ->get(route('shops.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('currencies', ['CHF', 'EUR']));
});

test('the report page does not scan the orders table once per panel', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();
    Order::factory()->count(5)->for($shop)->create(['placed_at' => now()->subDay()]);

    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    $this->actingAs($user)->get(route('reports.index', [$organization, 'period' => 'this_month']))->assertOk();

    // The status counts are a grouped scan of every order the organization
    // holds. One page asks three times over; it should only pay once.
    $scans = collect(DB::connection()->getQueryLog())
        ->filter(fn (array $query) => str_contains($query['query'], 'count(*) as order_count')
            && str_contains($query['query'], 'group by'))
        ->count();

    expect($scans)->toBe(1);
});

test('the chart and the per shop trends are read off one walk of the orders', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shopA = Shop::factory()->for($organization)->create(['name' => 'A']);
    $shopB = Shop::factory()->for($organization)->create(['name' => 'B']);

    foreach ([$shopA, $shopB] as $shop) {
        Order::factory()->count(3)->for($shop)->create([
            'status' => 'completed',
            'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'UTC'),
        ]);
    }

    $filters = new ReportFilters(
        period: ReportPeriod::Custom,
        from: CarbonImmutable::parse('2026-03-01', 'UTC'),
        to: CarbonImmutable::parse('2026-03-31 23:59:59', 'UTC'),
        timezone: 'UTC',
        shopIds: [$shopA->id, $shopB->id],
        statuses: ['completed'],
    );

    $report = new SalesReport($filters);

    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    $series = $report->series();
    $byShop = $report->byShop();

    // The only figure costing a row of work per order, rather than per
    // bucket, should be paid for once however many panels want it.
    $walks = collect(DB::connection()->getQueryLog())
        ->filter(fn (array $query) => str_contains($query['query'], '"shop_id", "placed_at", "total", "refunded_total"'))
        ->count();

    expect($walks)->toBe(1);

    // And the two still agree: each shop's trend sums to its own net, and
    // the shops together sum to the chart.
    expect(collect($byShop)->sum('netRevenue'))
        ->toEqualWithDelta(collect($series)->sum('revenue'), 0.01);

    foreach ($byShop as $row) {
        expect(round(array_sum($row['trend']), 2))->toEqualWithDelta($row['netRevenue'], 0.01);
    }
});

test('the printable revenue by shop report covers the filtered range', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create(['name' => 'Toys Online', 'currency' => 'CHF']);

    Order::factory()->for($shop)->create([
        'status' => 'completed', 'currency' => 'CHF', 'total' => 100, 'refunded_total' => 0,
        'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich')->utc(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('reports.shops', [$organization, 'period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/print/by-shop')
            ->where('filters.from', '2026-03-01')
            ->where('report.rows.0.name', 'Toys Online')
            ->where('report.totals.netRevenue', 100)
            // Both figures unless only one was asked for.
            ->where('figures', ['revenue', 'tax'])
            ->has('countedStatuses')
        );
});

test('the printable by shop report can show the vat alone', function () {
    $user = User::factory()->create();
    Shop::factory()->for($user->currentOrganization)->create();

    $this
        ->actingAs($user)
        ->get(route('reports.shops', [$user->currentOrganization, 'figures' => ['tax']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('figures', ['tax']));
});

test('the printable by shop report rejects a figure it does not know', function () {
    $user = User::factory()->create();
    Shop::factory()->for($user->currentOrganization)->create();

    $this
        ->actingAs($user)
        ->get(route('reports.shops', [$user->currentOrganization, 'figures' => ['profit']]))
        ->assertSessionHasErrors('figures.0');
});

test('users cannot print the revenue of an organization they do not belong to', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($user)->get(route('reports.shops', $organization))->assertForbidden();
    $this->actingAs($user)->get(route('reports.export.shops', $organization))->assertForbidden();
});
