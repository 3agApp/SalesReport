<?php

use App\Enums\OrganizationRole;
use App\Enums\ShopPlatform;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Saving a shop queues a connection check. These tests are about the shop
    // record itself; the check has its own test file.
    Queue::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validShopData(array $overrides = []): array
{
    return [
        'name' => 'Toys Online',
        'url' => 'https://toysonline.test',
        'platform' => ShopPlatform::WooCommerce->value,
        'consumer_key' => 'ck_'.str_repeat('a', 40),
        'consumer_secret' => 'cs_'.str_repeat('b', 40),
        ...$overrides,
    ];
}

test('organization members can see the shops page', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    Shop::factory()->for($organization)->create(['name' => 'Toys Online']);

    $this
        ->actingAs($user)
        ->get(route('shops.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shops/index')
            ->has('shops.data', 1)
            ->where('shops.data.0.name', 'Toys Online')
        );
});

test('guests are redirected to the login page', function () {
    $organization = Organization::factory()->create();

    $this->get(route('shops.index', $organization))->assertRedirect(route('login'));
});

test('users cannot see the shops of an organization they do not belong to', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Shop::factory()->for($organization)->create();

    $this
        ->actingAs($user)
        ->get(route('shops.index', $organization))
        ->assertForbidden();
});

test('the shops page never exposes stored credentials', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $shop = Shop::factory()->for($organization)->create([
        'consumer_key' => 'ck_'.str_repeat('a', 36).'9f3c',
        'consumer_secret' => 'cs_'.str_repeat('b', 40),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('shops.index', $organization));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('shops.data.0.consumerKeyHint', 'ck_…9f3c')
            ->missing('shops.data.0.consumer_key')
            ->missing('shops.data.0.consumer_secret')
        );

    $response->assertDontSee($shop->consumer_key, false);
    $response->assertDontSee($shop->consumer_secret, false);
});

test('shops can be searched by name and url', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    Shop::factory()->for($organization)->create(['name' => 'Toys Online', 'url' => 'https://toysonline.test']);
    Shop::factory()->for($organization)->create(['name' => 'Tigerbox', 'url' => 'https://tigerbox.test']);

    $this
        ->actingAs($user)
        ->get(route('shops.index', [$organization, 'search' => 'tigerbox']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('shops.data', 1)
            ->where('shops.data.0.name', 'Tigerbox')
        );
});

test('organization owners can add a shop', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $this
        ->actingAs($user)
        ->from(route('shops.index', $organization))
        ->post(route('shops.store', $organization), validShopData())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('shops.index', $organization))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Shop added.']);

    $shop = $organization->shops()->sole();

    expect($shop->name)->toBe('Toys Online')
        ->and($shop->url)->toBe('https://toysonline.test')
        ->and($shop->platform)->toBe(ShopPlatform::WooCommerce)
        ->and($shop->consumer_key)->toBe('ck_'.str_repeat('a', 40))
        ->and($shop->consumer_secret)->toBe('cs_'.str_repeat('b', 40));
});

test('shop credentials are encrypted at rest', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $this
        ->actingAs($user)
        ->post(route('shops.store', $organization), validShopData())
        ->assertSessionHasNoErrors();

    $stored = DB::table('shops')->sole();

    expect($stored->consumer_key)->not->toContain('ck_')
        ->and($stored->consumer_secret)->not->toContain('cs_');
});

test('shop urls are reduced to their scheme and host', function (string $input, string $stored) {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $this
        ->actingAs($user)
        ->post(route('shops.store', $organization), validShopData(['url' => $input]))
        ->assertSessionHasNoErrors();

    expect($organization->shops()->sole()->url)->toBe($stored);
})->with([
    'trailing slash' => ['https://toysonline.test/', 'https://toysonline.test'],
    'path and query' => ['https://toysonline.test/shop?page=2', 'https://toysonline.test'],
    'uppercase host' => ['https://ToysOnline.test', 'https://toysonline.test'],
    'missing scheme' => ['toysonline.test', 'https://toysonline.test'],
    'plain http is kept' => ['http://pikosch.test', 'http://pikosch.test'],
]);

test('shop data is validated', function (array $overrides, string $field) {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $this
        ->actingAs($user)
        ->post(route('shops.store', $organization), validShopData($overrides))
        ->assertSessionHasErrors($field);

    expect($organization->shops()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing url' => [['url' => ''], 'url'],
    'url that is not a url' => [['url' => 'not a url'], 'url'],
    'unsupported platform' => [['platform' => 'shopify'], 'platform'],
    'missing consumer key' => [['consumer_key' => ''], 'consumer_key'],
    'consumer key without the ck prefix' => [['consumer_key' => str_repeat('a', 40)], 'consumer_key'],
    'missing consumer secret' => [['consumer_secret' => ''], 'consumer_secret'],
    'consumer secret without the cs prefix' => [['consumer_secret' => str_repeat('b', 40)], 'consumer_secret'],
]);

test('the same shop url cannot be added to an organization twice', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    Shop::factory()->for($organization)->create(['url' => 'https://toysonline.test']);

    $this
        ->actingAs($user)
        ->post(route('shops.store', $organization), validShopData(['url' => 'https://toysonline.test/']))
        ->assertSessionHasErrors('url');

    expect($organization->shops()->count())->toBe(1);
});

test('the same shop url can be used by different organizations', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    Shop::factory()->create(['url' => 'https://toysonline.test']);

    $this
        ->actingAs($user)
        ->post(route('shops.store', $organization), validShopData())
        ->assertSessionHasNoErrors();

    expect($organization->shops()->count())->toBe(1);
});

test('organization owners can update a shop', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    $this
        ->actingAs($user)
        ->from(route('shops.index', $organization))
        ->patch(route('shops.update', [$organization, $shop]), validShopData(['name' => 'Renamed Shop']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('shops.index', $organization))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Shop updated.']);

    expect($shop->refresh()->name)->toBe('Renamed Shop')
        ->and($shop->consumer_key)->toBe('ck_'.str_repeat('a', 40));
});

test('leaving the credentials blank on an update keeps the stored ones', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    $originalKey = $shop->consumer_key;
    $originalSecret = $shop->consumer_secret;

    $this
        ->actingAs($user)
        ->patch(route('shops.update', [$organization, $shop]), validShopData([
            'name' => 'Renamed Shop',
            'consumer_key' => '',
            'consumer_secret' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($shop->refresh()->name)->toBe('Renamed Shop')
        ->and($shop->consumer_key)->toBe($originalKey)
        ->and($shop->consumer_secret)->toBe($originalSecret);
});

test('a malformed credential is still rejected on an update', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    $this
        ->actingAs($user)
        ->patch(route('shops.update', [$organization, $shop]), validShopData(['consumer_secret' => 'nope']))
        ->assertSessionHasErrors('consumer_secret');
});

test('organization owners can remove a shop', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();

    $this
        ->actingAs($user)
        ->from(route('shops.index', $organization))
        ->delete(route('shops.destroy', [$organization, $shop]))
        ->assertRedirect(route('shops.index', $organization))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Shop removed.']);

    $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
});

test('admins can manage shops', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($admin, ['role' => OrganizationRole::Admin->value]);

    $this
        ->actingAs($admin)
        ->post(route('shops.store', $organization), validShopData())
        ->assertSessionHasNoErrors();

    $shop = $organization->shops()->sole();

    $this
        ->actingAs($admin)
        ->delete(route('shops.destroy', [$organization, $shop]))
        ->assertRedirect();

    $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
});

test('members can see shops but cannot manage them', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $shop = Shop::factory()->for($organization)->create();

    $this
        ->actingAs($member)
        ->get(route('shops.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canCreateShop', false)
            ->where('permissions.canUpdateShop', false)
            ->where('permissions.canDeleteShop', false)
        );

    $this
        ->actingAs($member)
        ->post(route('shops.store', $organization), validShopData())
        ->assertForbidden();

    $this
        ->actingAs($member)
        ->patch(route('shops.update', [$organization, $shop]), validShopData())
        ->assertForbidden();

    $this
        ->actingAs($member)
        ->delete(route('shops.destroy', [$organization, $shop]))
        ->assertForbidden();
});

test('a shop cannot be reached through another organization', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $otherShop = Shop::factory()->create();

    $this
        ->actingAs($user)
        ->patch(route('shops.update', [$organization, $otherShop]), validShopData())
        ->assertNotFound();

    $this
        ->actingAs($user)
        ->delete(route('shops.destroy', [$organization, $otherShop]))
        ->assertNotFound();

    $this->assertDatabaseHas('shops', ['id' => $otherShop->id]);
});

test('removing an organization removes its shops', function () {
    $organization = Organization::factory()->create();
    $shop = Shop::factory()->for($organization)->create();

    $organization->forceDelete();

    $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
});
