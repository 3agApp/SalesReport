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
                            {--force : Sync every shop, even recently synced ones}
                            {--fresh : Forget where the sync got to and walk the whole history again}';

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

        if ($this->option('fresh') && ! $this->confirmFresh()) {
            return self::SUCCESS;
        }

        Shop::query()
            // A shop whose organization has been deleted is nobody's to sync.
            ->whereHas('organization')
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
                    if ($this->option('fresh')) {
                        // Orders are upserted, so re-walking the history
                        // rewrites the rows in place rather than duplicating
                        // them. This is how columns added after a sync, such
                        // as the tax inside a refund, get filled in.
                        $shop->syncStateOrCreate()->update([
                            'backfill_cursor' => null,
                            'backfill_offset' => 0,
                            'backfill_completed_at' => null,
                            'last_synced_at' => null,
                        ]);
                    }

                    SyncShopOrders::dispatch($shop);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} order ".str('sync')->plural($queued).'.');

        return self::SUCCESS;
    }

    /**
     * Check that a full re-walk is really wanted.
     *
     * It re-reads every order a shop has ever had, which is thousands of
     * requests against somebody's live store, so it is not something to set
     * off by mistyping a flag.
     */
    private function confirmFresh(): bool
    {
        return ! $this->input->isInteractive()
            || $this->confirm('This re-reads every order from the beginning. Continue?', false);
    }
}
