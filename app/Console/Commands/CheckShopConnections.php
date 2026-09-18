<?php

namespace App\Console\Commands;

use App\Jobs\Shops\CheckShopConnection;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CheckShopConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shops:check-connections
                            {--shop= : Only check the shop with this id}
                            {--force : Check every shop, even recently checked ones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue a WooCommerce connection check for every shop that is due one';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recheckAfter = now()->subMinutes((int) config('services.woocommerce.recheck_after_minutes'));
        $queued = 0;

        Shop::query()
            ->when($this->option('shop'), fn (Builder $query, string $shop) => $query->whereKey($shop))
            ->unless($this->option('force'), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('connection_checked_at')
                ->orWhere('connection_checked_at', '<=', $recheckAfter)))
            ->chunkById(100, function ($shops) use (&$queued) {
                foreach ($shops as $shop) {
                    CheckShopConnection::dispatch($shop);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} shop connection ".str('check')->plural($queued).'.');

        return self::SUCCESS;
    }
}
