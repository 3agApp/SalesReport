<?php

use App\Enums\OrganizationRole;
use App\Models\Order;
use App\Models\OrderStatusSetting;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build an organization whose orders sit in the given statuses.
 *
 * @param  array<string, int>  $statuses  slug => how many orders
 */
function organizationUsing(array $statuses): User
{
    $user = User::factory()->create();
    $shop = Shop::factory()->for($user->currentOrganization)->create();

    foreach ($statuses as $status => $count) {
        Order::factory()->for($shop)->count($count)->create([
            'status' => $status,
            'total' => 100,
            'refunded_total' => 0,
            'placed_at' => now(),
        ]);
    }

    return $user;
}

test('the page lists every status the orders use, with its order count', function () {
    $user = organizationUsing(['completed' => 3, 'partial-complete' => 2]);

    $this
        ->actingAs($user)
        ->get(route('reports.statuses.index', $user->currentOrganization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/statuses')
            ->where('anyDecided', false)
            ->where('statuses', fn (Collection $statuses) => $statuses
                ->pluck('orderCount', 'status')
                ->all() === ['completed' => 3, 'partial-complete' => 2]
            )
            // Nothing has been decided, so nothing is marked as decided.
            ->where('statuses', fn (Collection $statuses) => $statuses
                ->every(fn (array $row) => $row['decided'] === false)
            )
        );
});

test('a status a shop invented can be named and counted as revenue', function () {
    $user = organizationUsing(['completed' => 1, 'partial-complete' => 1]);
    $organization = $user->currentOrganization;

    $this
        ->actingAs($user)
        ->patch(route('reports.statuses.update', $organization), [
            'statuses' => [
                ['status' => 'completed', 'label' => 'Abgeschlossen', 'countsAsRevenue' => true],
                ['status' => 'partial-complete', 'label' => 'Teilweise abgeschlossen', 'countsAsRevenue' => true],
            ],
        ])
        ->assertRedirect();

    expect($organization->refresh()->revenueStatuses())
        ->toEqualCanonicalizing(['completed', 'partial-complete']);

    // And the report counts it without anybody asking for it in the URL.
    $this
        ->actingAs($user)
        ->get(route('reports.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.orderCount', 2)
            ->where('summary.netRevenue', 200)
            ->where('statusOptions', fn (Collection $options) => $options
                ->pluck('label', 'value')
                ->all() === [
                    'completed' => 'Abgeschlossen',
                    'partial-complete' => 'Teilweise abgeschlossen',
                ]
            )
        );
});

test('a status nobody has decided on is never counted as revenue', function () {
    $user = organizationUsing(['completed' => 1, 'planzer-transmit' => 1]);

    $this
        ->actingAs($user)
        ->get(route('reports.index', $user->currentOrganization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Only the order whose payment demonstrably went through.
            ->where('summary.orderCount', 1)
            ->where('summary.netRevenue', 100)
        );
});

test('unchecking a status stops it counting', function () {
    $user = organizationUsing(['completed' => 1, 'processing' => 1]);
    $organization = $user->currentOrganization;

    $this
        ->actingAs($user)
        ->patch(route('reports.statuses.update', $organization), [
            'statuses' => [
                ['status' => 'completed', 'label' => null, 'countsAsRevenue' => true],
                ['status' => 'processing', 'label' => null, 'countsAsRevenue' => false],
            ],
        ])
        ->assertRedirect();

    expect($organization->refresh()->revenueStatuses())->toBe(['completed']);
});

test('an empty label means no name of our own rather than a blank one', function () {
    $user = organizationUsing(['completed' => 1]);
    $organization = $user->currentOrganization;

    $this
        ->actingAs($user)
        ->patch(route('reports.statuses.update', $organization), [
            'statuses' => [['status' => 'completed', 'label' => '   ', 'countsAsRevenue' => true]],
        ])
        ->assertRedirect();

    expect($organization->orderStatusSettings()->sole()->label)->toBeNull()
        ->and($organization->refresh()->orderStatuses())->toBe(['completed' => 'Completed']);
});

test('saving twice updates the decision rather than duplicating it', function () {
    $user = organizationUsing(['completed' => 1]);
    $organization = $user->currentOrganization;

    foreach ([true, false] as $counts) {
        $this
            ->actingAs($user)
            ->patch(route('reports.statuses.update', $organization), [
                'statuses' => [['status' => 'completed', 'label' => null, 'countsAsRevenue' => $counts]],
            ])
            ->assertRedirect();
    }

    expect($organization->orderStatusSettings()->count())->toBe(1)
        ->and($organization->orderStatusSettings()->sole()->counts_as_revenue)->toBeFalse();
});

test('a status no order of the organization uses cannot be decided on', function () {
    $user = organizationUsing(['completed' => 1]);

    $this
        ->actingAs($user)
        ->patch(route('reports.statuses.update', $user->currentOrganization), [
            'statuses' => [['status' => 'invented-elsewhere', 'label' => null, 'countsAsRevenue' => true]],
        ])
        ->assertSessionHasErrors('statuses.0.status');

    expect($user->currentOrganization->orderStatusSettings()->count())->toBe(0);
});

test('members can see the statuses but not change them', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $shop = Shop::factory()->for($organization)->create();
    Order::factory()->for($shop)->create(['status' => 'completed', 'total' => 10, 'refunded_total' => 0, 'placed_at' => now()]);

    $this
        ->actingAs($member)
        ->get(route('reports.statuses.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('permissions.canUpdate', false));

    $this
        ->actingAs($member)
        ->patch(route('reports.statuses.update', $organization), [
            'statuses' => [['status' => 'completed', 'label' => null, 'countsAsRevenue' => false]],
        ])
        ->assertForbidden();
});

test('users cannot see the statuses of an organization they do not belong to', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('reports.statuses.index', Organization::factory()->create()))
        ->assertForbidden();
});

test('a decided status stays listed after its last order is gone', function () {
    $user = organizationUsing(['completed' => 1]);
    $organization = $user->currentOrganization;

    OrderStatusSetting::factory()->for($organization)->create([
        'status' => 'pre-ordered',
        'label' => 'Pre-ordered',
        'counts_as_revenue' => true,
    ]);

    $this
        ->actingAs($user)
        ->get(route('reports.statuses.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('statuses', fn (Collection $statuses) => $statuses
                ->pluck('orderCount', 'status')
                ->all() === ['completed' => 1, 'pre-ordered' => 0]
            )
        );
});
