<?php

use App\Models\Shop;
use App\Models\User;

/**
 * These cover the parts of shop management that live in JavaScript and that a
 * feature test cannot reach: the create and edit dialog, the confirmation
 * before a shop is removed, and the search box that filters as you type. The
 * rules behind them are covered by tests/Feature/Shops/ShopTest.php.
 */
test('a shop is added through the dialog', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $this->actingAs($user);

    $page = visit(route('shops.index', $organization));

    $page->click('@add-shop-button')
        ->assertSee('Connect a WooCommerce shop')
        ->fill('@shop-name', 'Toys Online')
        ->fill('@shop-url', 'https://toysonline.test')
        ->fill('@shop-consumer-key', 'ck_'.str_repeat('a', 40))
        ->fill('@shop-consumer-secret', 'cs_'.str_repeat('b', 40))
        ->click('@shop-submit')
        ->assertSee('Shop added.')
        ->assertSee('toysonline.test')
        ->assertNoJavaScriptErrors();

    expect($organization->shops()->sole())
        ->name->toBe('Toys Online')
        ->url->toBe('https://toysonline.test');
});

test('the dialog shows the validation message for a malformed consumer key', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    $this->actingAs($user);

    $page = visit(route('shops.index', $organization));

    $page->click('@add-shop-button')
        ->fill('@shop-name', 'Toys Online')
        ->fill('@shop-url', 'https://toysonline.test')
        ->fill('@shop-consumer-key', 'not-a-key')
        ->fill('@shop-consumer-secret', 'cs_'.str_repeat('b', 40))
        ->click('@shop-submit')
        ->assertSee('The consumer key must look like ck_ followed by the key WooCommerce generated.')
        ->assertNoJavaScriptErrors();

    expect($organization->shops()->count())->toBe(0);
});

test('editing a shop without retyping the credentials keeps the stored ones', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create(['name' => 'Toys Online']);

    $originalKey = $shop->consumer_key;
    $originalSecret = $shop->consumer_secret;

    $this->actingAs($user);

    $page = visit(route('shops.index', $organization));

    $page->click('@shop-actions')
        ->click('@shop-edit')
        ->assertSee('Update the connection details for Toys Online.')
        ->fill('@shop-name', 'Toys Online Switzerland')
        ->click('@shop-submit')
        ->assertSee('Shop updated.')
        ->assertNoJavaScriptErrors();

    expect($shop->fresh())
        ->name->toBe('Toys Online Switzerland')
        ->consumer_key->toBe($originalKey)
        ->consumer_secret->toBe($originalSecret);
});

test('removing a shop asks for confirmation first', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;
    $shop = Shop::factory()->for($organization)->create(['name' => 'Toys Online']);

    $this->actingAs($user);

    $page = visit(route('shops.index', $organization));

    $page->click('@shop-actions')
        ->click('@shop-delete')
        ->assertSee('Its stored API credentials will be deleted.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('shops', ['id' => $shop->id]);

    $page->click('@delete-shop-confirm')
        ->assertSee('Shop removed.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
});

test('the search box filters the list as the user types', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    Shop::factory()->for($organization)->create(['name' => 'Toys Online']);
    Shop::factory()->for($organization)->create(['name' => 'Tigerbox']);

    $this->actingAs($user);

    $page = visit(route('shops.index', $organization));

    $page->assertSee('Toys Online')
        ->assertSee('Tigerbox')
        ->fill('@shop-search', 'Tigerbox')
        ->assertDontSee('Toys Online')
        ->assertSee('Tigerbox')
        ->assertNoJavaScriptErrors();
});
