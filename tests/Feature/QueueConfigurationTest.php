<?php

use App\Jobs\Shops\CheckShopConnection;
use App\Jobs\Shops\SyncShopOrders;
use App\Models\Shop;

/**
 * The queue hands a job to the next worker once `retry_after` has passed,
 * whether or not the first worker is still running it. A job allowed to run
 * for longer than that is therefore picked up twice, and with `tries = 1` the
 * second pickup fails it for exceeding its attempts — while the first one is
 * still quietly working.
 *
 * This is invisible until a long job meets a worker restart or a second
 * worker, and then it looks like a random sync failure. The order backfill
 * hit it: a ten minute job against a ninety second retry window.
 */
test('the queue waits longer than the longest job before retrying it', function () {
    $shop = Shop::factory()->create();

    $longest = collect([
        new SyncShopOrders($shop),
        new CheckShopConnection($shop),
    ])->max(fn (object $job) => $job->timeout);

    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($longest);
});

test('a sync run is budgeted to finish well inside its own timeout', function () {
    $budget = (int) config('services.woocommerce.sync_max_seconds_per_run');
    $pageTimeout = (int) config('services.woocommerce.sync_timeout');

    // The budget is only checked between pages, so a run can overshoot it by
    // one page. Both together still have to fit inside the job's timeout.
    expect($budget + $pageTimeout)->toBeLessThan((new SyncShopOrders(Shop::factory()->create()))->timeout);
});
