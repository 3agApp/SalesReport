<?php

use App\Jobs\Shops\CheckShopConnection;
use App\Models\Shop;
use Illuminate\Support\Facades\Queue;

test('shops that have never been checked are queued', function () {
    Queue::fake();

    Shop::factory()->count(2)->create();

    $this->artisan('shops:check-connections')->assertSuccessful();

    Queue::assertPushed(CheckShopConnection::class, 2);
});

test('shops checked within the recheck window are left alone', function () {
    Queue::fake();
    config(['services.woocommerce.recheck_after_minutes' => 60]);

    Shop::factory()->connected()->checkedAt(now()->subMinutes(10))->create();
    $stale = Shop::factory()->connected()->checkedAt(now()->subHours(3))->create();

    $this->artisan('shops:check-connections')->assertSuccessful();

    Queue::assertPushed(CheckShopConnection::class, 1);
    Queue::assertPushed(fn (CheckShopConnection $job) => $job->shop->is($stale));
});

test('the force option checks every shop', function () {
    Queue::fake();

    Shop::factory()->count(2)->connected()->checkedAt(now())->create();

    $this->artisan('shops:check-connections', ['--force' => true])->assertSuccessful();

    Queue::assertPushed(CheckShopConnection::class, 2);
});

test('a single shop can be checked on its own', function () {
    Queue::fake();

    $shop = Shop::factory()->create();
    Shop::factory()->create();

    $this->artisan('shops:check-connections', ['--shop' => $shop->id])->assertSuccessful();

    Queue::assertPushed(CheckShopConnection::class, 1);
    Queue::assertPushed(fn (CheckShopConnection $job) => $job->shop->is($shop));
});
