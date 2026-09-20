<?php

use App\Enums\ShopConnectionStatus;
use App\Models\Shop;
use App\Models\User;
use App\Services\Network\UnsafeDestinationException;
use App\Services\WooCommerce\WooCommerceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The toast a blocked destination puts on screen: the status description,
 * with the reason this particular address was refused in brackets after it.
 */
function blockedMessage(): string
{
    return ShopConnectionStatus::Unreachable->description()
        .' (This address points to a private or reserved network, so it is not contacted.)';
}

/**
 * Fake the shop, keeping the request options each call was sent with.
 *
 * @param  array<int, array<string, mixed>>  $sent
 */
function fakeShopRecordingOptions(array &$sent): void
{
    Http::fake(function ($request, array $options) use (&$sent) {
        $sent[] = $options;

        return Http::response([['id' => 1]]);
    });
}

test('a shop address resolving to a non-public address is refused on the form', function (array $addresses) {
    $this->resolveHostsTo($addresses);
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('shops.store', $user->currentOrganization), validShopData(['url' => 'https://toysonline.test']))
        ->assertSessionHasErrors(['url' => 'That address resolves to a private network and cannot be reached as a shop.']);

    expect($user->currentOrganization->shops()->count())->toBe(0);
})->with([
    'loopback' => [['127.0.0.1']],
    'private network' => [['10.0.0.5']],
    'cloud metadata' => [['169.254.169.254']],
    'carrier-grade nat' => [['100.64.0.1']],
    'multicast' => [['224.0.0.1']],
    'ipv6 loopback' => [['::1']],
    'ipv6 unique local' => [['fd00::1']],
    'ipv4-mapped ipv6' => [['::ffff:127.0.0.1']],
    // NAT64 embeds an IPv4 address, so it reaches the private network by
    // another name. PHP's own reserved-range filter does not know it.
    'nat64' => [['64:ff9b::a00:1']],
    'one private answer among public ones' => [['93.184.215.14', '10.0.0.1']],
]);

test('every request is pinned to the addresses that were just checked', function (string $url, string $pin) {
    $this->resolveHostsTo(['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c']);
    $sent = [];
    fakeShopRecordingOptions($sent);
    $shop = Shop::factory()->create(['url' => $url]);

    app(WooCommerceClient::class)->get($shop, 'orders');

    expect($sent)->not->toBeEmpty();

    foreach ($sent as $options) {
        expect($options['curl'][CURLOPT_RESOLVE])->toBe([$pin])
            ->and($options['proxy'])->toBe('');
    }
})->with([
    'default port' => ['https://toysonline.test', 'toysonline.test:443:93.184.215.14,[2606:2800:21f:cb07:6820:80da:af6b:8b2c]'],
    'custom port' => ['https://toysonline.test:8443', 'toysonline.test:8443:93.184.215.14,[2606:2800:21f:cb07:6820:80da:af6b:8b2c]'],
]);

test('a saved shop whose dns now answers privately is never contacted', function () {
    $shop = Shop::factory()->create(['url' => 'https://toysonline.test']);
    $this->resolveHostsTo(['10.0.0.1']);
    Http::fake();

    expect(fn () => app(WooCommerceClient::class)->get($shop, 'orders'))
        ->toThrow(UnsafeDestinationException::class);

    Http::assertNothingSent();
});

test('a shop host that does not resolve is never contacted', function () {
    $shop = Shop::factory()->create(['url' => 'https://toysonline.test']);
    $this->resolveHostsTo([]);
    Http::fake();

    expect(fn () => app(WooCommerceClient::class)->get($shop, 'orders'))
        ->toThrow(ConnectionException::class, 'could not be resolved');

    Http::assertNothingSent();
});

test('a host that rebinds to a private address after validation is not contacted', function () {
    // Public while the form is validated, private by the time the client
    // connects. This is the gap the form rule on its own cannot close.
    $lookups = 0;
    $this->resolveHostsTo(function () use (&$lookups) {
        return ++$lookups === 1 ? ['93.184.215.14'] : ['127.0.0.1'];
    });
    Http::fake();

    $user = User::factory()->create();

    // Saving queues a connection check, which runs inline here, so the
    // second lookup is the one the client makes before connecting.
    $this
        ->actingAs($user)
        ->post(route('shops.store', $user->currentOrganization), validShopData(['url' => 'https://toysonline.test']))
        ->assertSessionHasNoErrors();

    $shop = $user->currentOrganization->shops()->sole();

    expect($lookups)->toBe(2)
        ->and($shop->connection_status)->toBe(ShopConnectionStatus::Unreachable)
        ->and($shop->connection_message)->toContain('private or reserved network');

    Http::assertNothingSent();
});

test('the toast names the blocked destination when a check is run by hand', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->for($user->currentOrganization)->create(['url' => 'https://toysonline.test']);
    $this->resolveHostsTo(['169.254.169.254']);
    Http::fake();

    $this
        ->actingAs($user)
        ->post(route('shops.connection.test', [$user->currentOrganization, $shop]))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => blockedMessage()]);

    Http::assertNothingSent();
});

test('a sync to a blocked address records a failure instead of crashing the job', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->for($user->currentOrganization)->create(['url' => 'https://toysonline.test']);
    $this->resolveHostsTo(['169.254.169.254']);
    Http::fake();

    $this
        ->actingAs($user)
        ->post(route('shops.sync.store', [$user->currentOrganization, $shop]))
        ->assertRedirect();

    Http::assertNothingSent();
});

test('private hosts are reachable when explicitly allowed', function () {
    config(['services.woocommerce.allow_private_hosts' => true]);
    $this->resolveHostsTo(['10.0.0.1']);
    $sent = [];
    fakeShopRecordingOptions($sent);
    $shop = Shop::factory()->create(['url' => 'https://toysonline.test']);

    app(WooCommerceClient::class)->get($shop, 'orders');

    expect($sent)->not->toBeEmpty()
        ->and($sent[0])->not->toHaveKey('curl');
});
