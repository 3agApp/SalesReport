<?php

use App\Enums\ShopConnectionStatus;
use App\Enums\ShopSyncStatus;
use App\Jobs\Shops\SyncShopOrders;
use App\Models\Shop;
use App\Models\ShopSyncState;
use Illuminate\Support\Facades\Queue;

test('shops that have never been synced are queued', function () {
    Queue::fake();

    Shop::factory()->count(2)->connected()->create();

    $this->artisan('shops:sync-orders')->assertSuccessful();

    Queue::assertPushed(SyncShopOrders::class, 2);
});

test('shops with broken credentials are skipped', function () {
    Queue::fake();

    Shop::factory()->connected()->create();
    Shop::factory()->failing(ShopConnectionStatus::InvalidCredentials)->create();
    Shop::factory()->failing(ShopConnectionStatus::Unreachable)->create();

    $this->artisan('shops:sync-orders')->assertSuccessful();

    Queue::assertPushed(SyncShopOrders::class, 1);
});

test('shops synced within the window are left alone', function () {
    Queue::fake();
    config(['services.woocommerce.sync_after_minutes' => 15]);

    $recent = Shop::factory()->connected()->create();
    ShopSyncState::factory()->for($recent)->backfilled()->finishedAt(now()->subMinutes(2))->create();

    $stale = Shop::factory()->connected()->create();
    ShopSyncState::factory()->for($stale)->backfilled()->finishedAt(now()->subHour())->create();

    $this->artisan('shops:sync-orders')->assertSuccessful();

    Queue::assertPushed(SyncShopOrders::class, 1);
    Queue::assertPushed(fn (SyncShopOrders $job) => $job->shop->is($stale));
});

test('a shop left marked as syncing by a dead worker is still picked up', function () {
    Queue::fake();

    // Concurrency is the job's overlap lock to worry about. If this query
    // skipped a shop stuck on "syncing", a killed worker would wedge it.
    $shop = Shop::factory()->connected()->create();
    ShopSyncState::factory()->for($shop)->backfilled()->create([
        'status' => ShopSyncStatus::Syncing,
        'last_finished_at' => now()->subHour(),
    ]);

    $this->artisan('shops:sync-orders')->assertSuccessful();

    Queue::assertPushed(SyncShopOrders::class, 1);
});

test('a shop part way through its history is queued again straight away', function () {
    Queue::fake();

    $shop = Shop::factory()->connected()->create();
    ShopSyncState::factory()->for($shop)->create([
        'status' => ShopSyncStatus::Backfilling,
        'backfill_cursor' => now()->subMonths(6),
        'last_finished_at' => now()->subMinute(),
    ]);

    $this->artisan('shops:sync-orders')->assertSuccessful();

    Queue::assertPushed(SyncShopOrders::class, 1);
});

test('the force option syncs every healthy shop', function () {
    Queue::fake();

    $shop = Shop::factory()->connected()->create();
    ShopSyncState::factory()->for($shop)->backfilled()->finishedAt(now())->create();

    $this->artisan('shops:sync-orders', ['--force' => true])->assertSuccessful();

    Queue::assertPushed(SyncShopOrders::class, 1);
});

test('a single shop can be synced on its own', function () {
    Queue::fake();

    $shop = Shop::factory()->connected()->create();
    Shop::factory()->connected()->create();

    $this->artisan('shops:sync-orders', ['--shop' => $shop->id])->assertSuccessful();

    Queue::assertPushed(SyncShopOrders::class, 1);
    Queue::assertPushed(fn (SyncShopOrders $job) => $job->shop->is($shop));
});
