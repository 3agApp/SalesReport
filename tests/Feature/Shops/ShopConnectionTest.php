<?php

use App\Enums\OrganizationRole;
use App\Enums\ShopConnectionStatus;
use App\Jobs\Shops\CheckShopConnection;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Build a shop whose credentials are known, so requests can be asserted on.
 */
function shopFor(Organization $organization, array $overrides = []): Shop
{
    return Shop::factory()->for($organization)->create([
        'url' => 'https://toysonline.test',
        'consumer_key' => 'ck_'.str_repeat('a', 40),
        'consumer_secret' => 'cs_'.str_repeat('b', 40),
        ...$overrides,
    ]);
}

test('a successful check asks woocommerce for an order and records the result', function () {
    Http::fake(['toysonline.test/*' => Http::response([['id' => 1]])]);

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization);

    $this
        ->actingAs($user)
        ->from(route('shops.index', $user->currentOrganization))
        ->post(route('shops.connection.test', [$user->currentOrganization, $shop]))
        ->assertRedirect(route('shops.index', $user->currentOrganization))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => ShopConnectionStatus::Connected->description(),
        ]);

    $shop->refresh();

    expect($shop->connection_status)->toBe(ShopConnectionStatus::Connected)
        ->and($shop->connection_checked_at)->not->toBeNull()
        ->and($shop->connection_response_time_ms)->not->toBeNull();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://toysonline.test/wp-json/wc/v3/orders')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode($shop->consumer_key.':'.$shop->consumer_secret)));
});

test('a check does not count as the shop being updated', function () {
    Http::fake(['toysonline.test/*' => Http::response([['id' => 1]])]);

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization);
    $updatedAt = $shop->updated_at;

    $this->travel(1)->hours();

    $this
        ->actingAs($user)
        ->post(route('shops.connection.test', [$user->currentOrganization, $shop]));

    $shop->refresh();

    expect($shop->connection_checked_at->timestamp)->toBeGreaterThan($updatedAt->timestamp)
        ->and($shop->updated_at->timestamp)->toBe($updatedAt->timestamp);
});

test('a failing check records why the shop could not be read', function (callable $fake, ShopConnectionStatus $expected) {
    Http::fake(['toysonline.test/*' => $fake()]);

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization);

    $this
        ->actingAs($user)
        ->from(route('shops.index', $user->currentOrganization))
        ->post(route('shops.connection.test', [$user->currentOrganization, $shop]))
        ->assertRedirect(route('shops.index', $user->currentOrganization))
        ->assertInertiaFlash('toast.type', 'error');

    $shop->refresh();

    expect($shop->connection_status)->toBe($expected)
        ->and($shop->connection_message)->toContain($expected->description())
        ->and($shop->connection_checked_at)->not->toBeNull();
})->with([
    'rejected credentials' => [fn () => Http::response(['message' => 'Invalid signature.'], 401), ShopConnectionStatus::InvalidCredentials],
    'key without read access' => [fn () => Http::response(['message' => 'Sorry, you cannot list resources.'], 403), ShopConnectionStatus::InsufficientPermissions],
    'no rest api at the url' => [fn () => Http::response(['message' => 'No route was found.'], 404), ShopConnectionStatus::NotFound],
    'shop error' => [fn () => Http::response('', 500), ShopConnectionStatus::Failed],
    'shop offline' => [fn () => Http::failedConnection(), ShopConnectionStatus::Unreachable],
]);

test('credentials are retried in the query string when the host strips the authorization header', function () {
    Http::fake([
        'toysonline.test/*woocommerce_currency*' => Http::response(['value' => 'CHF']),
        // Scoped to the order probe rather than left as a catch-all: Laravel
        // invokes every stub and takes the first answer, so a catch-all
        // sequence would be drained by the currency request as well.
        'toysonline.test/wp-json/wc/v3/orders*' => Http::sequence()
            ->push(['message' => 'Consumer key is missing.'], 401)
            ->push([['id' => 1]], 200),
    ]);

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization);

    $this
        ->actingAs($user)
        ->post(route('shops.connection.test', [$user->currentOrganization, $shop]));

    expect($shop->refresh()->connection_status)->toBe(ShopConnectionStatus::Connected);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'consumer_key='.$shop->consumer_key)
        && str_contains($request->url(), 'consumer_secret='.$shop->consumer_secret));
});

test('shops served over plain http are not contacted at all', function () {
    Http::fake();

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization, ['url' => 'http://pikosch.test']);

    $this
        ->actingAs($user)
        ->post(route('shops.connection.test', [$user->currentOrganization, $shop]));

    expect($shop->refresh()->connection_status)->toBe(ShopConnectionStatus::RequiresHttps);

    Http::assertNothingSent();
});

test('organization admins can test a connection', function () {
    Http::fake(['toysonline.test/*' => Http::response([['id' => 1]])]);

    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($admin, ['role' => OrganizationRole::Admin->value]);

    $shop = shopFor($organization);

    $this
        ->actingAs($admin)
        ->post(route('shops.connection.test', [$organization, $shop]))
        ->assertSessionHasNoErrors();

    expect($shop->refresh()->connection_status)->toBe(ShopConnectionStatus::Connected);
});

test('organization members cannot test a connection', function () {
    Http::fake();

    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $shop = shopFor($organization);

    $this
        ->actingAs($member)
        ->post(route('shops.connection.test', [$organization, $shop]))
        ->assertForbidden();

    expect($shop->refresh()->connection_status)->toBe(ShopConnectionStatus::Unknown);
    Http::assertNothingSent();
});

test('a shop belonging to another organization cannot be tested', function () {
    Http::fake();

    $user = User::factory()->create();
    $shop = shopFor(Organization::factory()->create());

    $this
        ->actingAs($user)
        ->post(route('shops.connection.test', [$user->currentOrganization, $shop]))
        ->assertNotFound();

    Http::assertNothingSent();
});

test('guests cannot test a connection', function () {
    Http::fake();

    $organization = Organization::factory()->create();
    $shop = shopFor($organization);

    $this
        ->post(route('shops.connection.test', [$organization, $shop]))
        ->assertRedirect(route('login'));

    Http::assertNothingSent();
});

test('adding a shop queues a first connection check', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('shops.store', $user->currentOrganization), [
            'name' => 'Toys Online',
            'url' => 'https://toysonline.test',
            'platform' => 'woocommerce',
            'consumer_key' => 'ck_'.str_repeat('a', 40),
            'consumer_secret' => 'cs_'.str_repeat('b', 40),
        ])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(CheckShopConnection::class, 1);
});

test('changing the credentials forgets the recorded status and rechecks', function () {
    Queue::fake();

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization)->forceFill([
        'connection_status' => ShopConnectionStatus::Connected,
        'connection_message' => ShopConnectionStatus::Connected->description(),
        'connection_checked_at' => now(),
        'connection_response_time_ms' => 120,
    ]);
    $shop->save();

    $this
        ->actingAs($user)
        ->patch(route('shops.update', [$user->currentOrganization, $shop]), [
            'name' => $shop->name,
            'url' => $shop->url,
            'platform' => $shop->platform->value,
            'consumer_key' => 'ck_'.str_repeat('c', 40),
            'consumer_secret' => 'cs_'.str_repeat('d', 40),
        ])
        ->assertSessionHasNoErrors();

    $shop->refresh();

    expect($shop->connection_status)->toBe(ShopConnectionStatus::Unknown)
        ->and($shop->connection_message)->toBeNull()
        ->and($shop->connection_checked_at)->toBeNull()
        ->and($shop->connection_response_time_ms)->toBeNull();

    Queue::assertPushed(CheckShopConnection::class, 1);
});

test('renaming a shop leaves its recorded status alone', function () {
    Queue::fake();

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization);
    $shop->forceFill(['connection_status' => ShopConnectionStatus::Connected])->save();

    $this
        ->actingAs($user)
        ->patch(route('shops.update', [$user->currentOrganization, $shop]), [
            'name' => 'Toys Online Europe',
            'url' => $shop->url,
            'platform' => $shop->platform->value,
        ])
        ->assertSessionHasNoErrors();

    expect($shop->refresh()->connection_status)->toBe(ShopConnectionStatus::Connected);

    Queue::assertNothingPushed();
});

test('a failed job records the failure rather than leaving a stale status', function () {
    $shop = shopFor(Organization::factory()->create());
    $shop->forceFill(['connection_status' => ShopConnectionStatus::Connected])->save();

    (new CheckShopConnection($shop))->failed(new RuntimeException('Worker died.'));

    expect($shop->refresh()->connection_status)->toBe(ShopConnectionStatus::Failed)
        ->and($shop->connection_message)->toContain('Worker died.');
});

test('a healthy check reads the currency the shop sells in', function () {
    Http::fake([
        'toysonline.test/*woocommerce_currency*' => Http::response(['id' => 'woocommerce_currency', 'value' => 'CHF']),
        'toysonline.test/*' => Http::response([['id' => 1]]),
    ]);

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization);

    $this->actingAs($user)->post(route('shops.connection.test', [$user->currentOrganization, $shop]));

    expect($shop->refresh()->currency)->toBe('CHF');
});

test('a shop whose currency is already known is not asked again', function () {
    Http::fake(['toysonline.test/*' => Http::response([['id' => 1]])]);

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization, ['currency' => 'CHF']);

    $this->actingAs($user)->post(route('shops.connection.test', [$user->currentOrganization, $shop]));

    // An hourly check should not pay for a second request to hear the same
    // answer a store gives about once in its life.
    Http::assertSentCount(1);
    expect($shop->refresh()->currency)->toBe('CHF');
});

test('a failed check keeps the currency it already knew', function () {
    Http::fake(['toysonline.test/*' => Http::response(['message' => 'Invalid signature.'], 401)]);

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization, ['currency' => 'CHF']);

    $this->actingAs($user)->post(route('shops.connection.test', [$user->currentOrganization, $shop]));

    $shop->refresh();

    expect($shop->connection_status)->toBe(ShopConnectionStatus::InvalidCredentials)
        ->and($shop->currency)->toBe('CHF');
});

test('changing the credentials forgets the currency too', function () {
    Queue::fake();

    $user = User::factory()->create();
    $shop = shopFor($user->currentOrganization, ['currency' => 'CHF']);

    $this
        ->actingAs($user)
        ->patch(route('shops.update', [$user->currentOrganization, $shop]), [
            'name' => $shop->name,
            'url' => $shop->url,
            'platform' => $shop->platform->value,
            'consumer_key' => 'ck_'.str_repeat('c', 40),
            'consumer_secret' => 'cs_'.str_repeat('d', 40),
        ]);

    // New credentials can point at a different store than the old ones did.
    expect($shop->refresh()->currency)->toBeNull();
});
