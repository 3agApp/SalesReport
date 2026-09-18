<?php

namespace App\Jobs\Shops;

use App\Actions\Shops\ImportShopOrders;
use App\Enums\ShopSyncStatus;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Throwable;

class SyncShopOrders implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * A shop that is down is recorded as a failed sync and picked up by the
     * next scheduled run, so there is nothing to gain from retrying here.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(public Shop $shop) {}

    /**
     * Execute the job.
     */
    public function handle(ImportShopOrders $action): void
    {
        $result = $action->handle($this->shop);

        // A long backfill is split across runs so no single job holds a worker
        // for an hour. The delay lets the overlap lock clear before the next
        // one starts.
        if ($result->hasMore) {
            self::dispatch($this->shop)->delay(now()->addSeconds(10));
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("shop-orders:{$this->shop->id}"))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    /**
     * Handle a job failure, so a crashed sync does not look like it is running.
     */
    public function failed(?Throwable $exception): void
    {
        $this->shop->syncStateOrCreate()->update([
            'status' => ShopSyncStatus::Failed,
            'last_error' => Str::limit(Str::squish($exception?->getMessage() ?? 'The sync stopped unexpectedly.'), 250),
            'last_finished_at' => now(),
        ]);
    }
}
