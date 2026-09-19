<?php

namespace App\Console\Commands;

use App\Enums\ShopConnectionStatus;
use App\Jobs\Shops\SyncShopOrders;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SyncOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shops:sync-orders
                            {--shop= : Only sync the shop with this id}
                            {--force : Sync every shop, even recently synced ones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue a WooCommerce order sync for every shop that is due one';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $syncAfter = now()->subMinutes((int) config('services.woocommerce.sync_after_minutes'));
        $queued = 0;

        Shop::query()
            ->when($this->option('shop'), fn (Builder $query, string $shop) => $query->whereKey($shop))
            // There is no point asking a shop for orders when the last check
            // said its credentials do not work.
            ->whereNotIn('connection_status', ShopConnectionStatus::needingAttention())
            // Whether a sync is already running is not decided here: the job
            // holds an overlap lock for that. Keeping this query to staleness
            // alone means a worker killed mid-run cannot wedge a shop.
            ->unless($this->option('force'), fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereDoesntHave('syncState')
                    ->orWhereHas('syncState', fn (Builder $query) => $query
                        // A shop part way through its history should carry on
                        // rather than wait out the window.
                        ->whereNull('backfill_completed_at')
                        ->orWhereNull('last_finished_at')
                        ->orWhere('last_finished_at', '<=', $syncAfter))))
            ->with('syncState')
            ->chunkById(100, function ($shops) use (&$queued) {
                foreach ($shops as $shop) {
                    SyncShopOrders::dispatch($shop);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} order ".str('sync')->plural($queued).'.');

        return self::SUCCESS;
    }
}
