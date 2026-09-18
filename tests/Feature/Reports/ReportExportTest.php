<?php

use App\Enums\OrganizationRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

/**
 * Read a streamed download back as text.
 */
function csvBody(TestResponse $response): string
{
    ob_start();
    $response->baseResponse->sendContent();

    return (string) ob_get_clean();
}

test('the orders export contains the filtered orders', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $organization->update(['timezone' => 'Europe/Zurich']);
    $shop = Shop::factory()->for($organization)->create(['name' => 'Toys Online']);

    Order::factory()->for($shop)->create([
        'status' => 'completed', 'number' => '1001', 'currency' => 'CHF',
        'total' => 120.5, 'refunded_total' => 20.5, 'customer_email' => 'anna@example.test',
        'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich')->utc(),
    ]);
    Order::factory()->for($shop)->create([
        'status' => 'cancelled', 'number' => '1002',
        'placed_at' => CarbonImmutable::parse('2026-03-11 12:00', 'Europe/Zurich')->utc(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('reports.export.orders', [$organization, 'period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertDownload();

    $csv = csvBody($response);

    expect($csv)->toContain('Toys Online')
        ->and($csv)->toContain('1001')
        ->and($csv)->toContain('anna@example.test')
        // Net of the refund, and the local wall-clock time rather than UTC.
        ->and($csv)->toContain('100.0000')
        ->and($csv)->toContain('2026-03-10 12:00:00')
        // A cancelled order is not part of the filtered set.
        ->and($csv)->not->toContain('1002');
});

test('the line items export has one row per product sold', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create(['name' => 'Toys Online']);

    $order = Order::factory()->for($shop)->create([
        'status' => 'completed', 'number' => '1001',
        'placed_at' => CarbonImmutable::parse('2026-03-10 12:00', 'Europe/Zurich')->utc(),
    ]);

    OrderItem::factory()->for($order)->create(['sku' => 'TRAIN-1', 'name' => 'Wooden train', 'quantity' => 2]);
    OrderItem::factory()->for($order)->create(['sku' => 'TRACK-1', 'name' => 'Track set', 'quantity' => 1]);

    $csv = csvBody(
        $this
            ->actingAs($user)
            ->get(route('reports.export.items', [$organization, 'period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()
    );

    expect(substr_count($csv, 'Toys Online'))->toBe(2)
        ->and($csv)->toContain('TRAIN-1')
        ->and($csv)->toContain('TRACK-1');
});

test('the export starts with a byte order mark so excel reads it as utf-8', function () {
    $user = User::factory()->create();
    Shop::factory()->for($user->currentOrganization)->create();

    $csv = csvBody(
        $this->actingAs($user)->get(route('reports.export.orders', $user->currentOrganization))->assertOk()
    );

    expect($csv)->toStartWith("\u{FEFF}");
});

test('members can export', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);
    Shop::factory()->for($organization)->create();

    $this->actingAs($member)->get(route('reports.export.orders', $organization))->assertOk();
});

test('outsiders cannot export', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($user)->get(route('reports.export.orders', $organization))->assertForbidden();
});
