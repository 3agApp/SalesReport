<?php

namespace App\Jobs\Shops;

use App\Actions\Shops\TestShopConnection;
use App\Data\ShopConnectionResult;
use App\Enums\ShopConnectionStatus;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class CheckShopConnection implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * A failing shop is a result worth recording, not an error worth retrying.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public Shop $shop) {}

    /**
     * Execute the job.
     */
    public function handle(TestShopConnection $action): void
    {
        $action->handle($this->shop);
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("shop-connection:{$this->shop->id}"))
                ->dontRelease()
                ->expireAfter(120),
        ];
    }

    /**
     * Handle a job failure, so a crashed check never leaves a stale status.
     */
    public function failed(?Throwable $exception): void
    {
        $this->shop->recordConnectionResult(ShopConnectionResult::for(
            ShopConnectionStatus::Failed,
            $exception?->getMessage(),
        ));
    }
}
