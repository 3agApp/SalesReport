<?php

namespace App\Actions\Shops;

use App\Data\ShopSyncResult;
use App\Enums\ShopSyncStatus;
use App\Models\Shop;
use App\Models\ShopSyncState;
use App\Services\WooCommerce\OrderImporter;
use App\Services\WooCommerce\WooCommerceClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pulls a shop's orders into our own tables.
 *
 * Two passes share the work. The backfill walks the shop's history from the
 * oldest order forward, one page at a time, remembering where it got to. Once
 * that finishes, every later run asks only for orders modified since the last
 * one, which is what brings status changes and refunds up to date rather than
 * only new orders.
 */
class ImportShopOrders
{
    public function __construct(
        private WooCommerceClient $client,
        private OrderImporter $importer,
    ) {}

    /**
     * Sync a shop's orders, picking up wherever the last run stopped.
     */
    public function handle(Shop $shop): ShopSyncResult
    {
        $state = $shop->syncStateOrCreate();
        $startedAt = now();

        $attributes = [
            'status' => $state->hasBackfilled() ? ShopSyncStatus::Syncing : ShopSyncStatus::Backfilling,
            'last_error' => null,
        ];

        // Where the incremental pass will pick up once the history is in. It
        // is set when the backfill begins rather than when it ends, because
        // walking years of orders takes hours or days: an order refunded
        // while the walk was still somewhere behind it went in at its old
        // figures, and a mark set at the end would never look at it again.
        if (! $state->hasBackfilled() && $state->last_synced_at === null) {
            $attributes['last_synced_at'] = $startedAt;
        }

        $state->update($attributes);

        try {
            $result = $state->hasBackfilled()
                ? $this->runIncrementalPass($shop, $state, $startedAt)
                : $this->runBackfillPass($shop, $state);
        } catch (ConnectionException|RuntimeException $exception) {
            // An unreachable shop or an error response is an outcome to record,
            // not a bug to fail the job over. Anything else is left to bubble.
            return $this->recordFailure($state, $exception->getMessage());
        }

        $state->update([
            'status' => $result->status,
            'last_finished_at' => now(),
        ]);

        return $result;
    }

    /**
     * Walk the shop's order history from the oldest order forward.
     */
    private function runBackfillPass(Shop $shop, ShopSyncState $state): ShopSyncResult
    {
        $cursor = $state->backfill_cursor;
        $offset = $state->backfill_offset;
        $imported = 0;
        $pages = 0;
        $runStartedAt = microtime(true);

        while (true) {
            $query = [
                'orderby' => 'date',
                'order' => 'asc',
                ...$this->baseQuery(),
            ];

            if ($cursor instanceof CarbonImmutable) {
                // Overlapping by a second is harmless because orders are
                // upserted, whereas skipping one would lose it for good.
                $query['after'] = $cursor->subSecond()->toIso8601String();
            }

            if ($offset > 0) {
                $query['offset'] = $offset;
            }

            $payloads = $this->fetchPage($shop, $query);
            $pages++;

            if ($payloads === []) {
                return $this->completeBackfill($state, $imported, $pages);
            }

            $imported += $this->importer->import($shop, $payloads);
            $furthest = $this->furthestTimestamp($payloads, 'date_created_gmt');

            if ($cursor instanceof CarbonImmutable && $furthest !== null && $furthest->lessThanOrEqualTo($cursor)) {
                // A whole page sharing one second. The date cursor cannot
                // move without skipping the orders sitting on it, so step
                // over the ones already read by offset and leave the cursor
                // where it is. A store that was bulk migrated puts thousands
                // of orders on a single timestamp, and refusing to go on
                // would wedge its history for good.
                $offset += count($payloads);
            } else {
                $cursor = $furthest ?? $cursor;
                $offset = 0;
            }

            // Both together: the offset only means anything alongside the
            // cursor it counts from, and a run that stops here has to be
            // able to pick up mid-second rather than start that second again.
            $state->update(['backfill_cursor' => $cursor, 'backfill_offset' => $offset]);

            if (count($payloads) < $this->pageSize()) {
                return $this->completeBackfill($state, $imported, $pages);
            }

            if ($this->runIsOver($pages, $runStartedAt)) {
                break;
            }
        }

        return new ShopSyncResult(
            status: ShopSyncStatus::Backfilling,
            importedCount: $imported,
            pagesFetched: $pages,
            hasMore: true,
            message: 'Imported '.$imported.' more historical '.Str::plural('order', $imported).'.',
        );
    }

    /**
     * Fetch everything that changed since the last run.
     */
    private function runIncrementalPass(Shop $shop, ShopSyncState $state, CarbonImmutable $startedAt): ShopSyncResult
    {
        $since = ($state->last_synced_at ?? $state->backfill_completed_at ?? $startedAt)
            ->subMinutes($this->overlapMinutes());

        $imported = 0;
        $pages = 0;
        $caughtUp = false;
        $furthestModified = null;
        $runStartedAt = microtime(true);

        while (true) {
            $payloads = $this->fetchPage($shop, [
                'orderby' => 'modified',
                'order' => 'asc',
                'modified_after' => $since->utc()->toIso8601String(),
                'page' => $pages + 1,
                ...$this->baseQuery(),
            ]);

            $pages++;

            if ($payloads === []) {
                $caughtUp = true;

                break;
            }

            $imported += $this->importer->import($shop, $payloads);
            $furthestModified = $this->furthestTimestamp($payloads, 'date_modified_gmt') ?? $furthestModified;

            if (count($payloads) < $this->pageSize()) {
                $caughtUp = true;

                break;
            }

            if ($this->runIsOver($pages, $runStartedAt)) {
                break;
            }
        }

        if ($caughtUp) {
            // The high-water mark is when the run started, not when it ended,
            // so an order modified during the run is picked up next time.
            $state->update(['last_synced_at' => $startedAt]);
        } elseif ($furthestModified !== null) {
            // The run hit its page limit with more still to come. Moving the
            // mark to the last order actually seen lets the next run carry on
            // from there; moving it to now would lose everything after it.
            $state->update(['last_synced_at' => $furthestModified]);
        }

        return new ShopSyncResult(
            status: $caughtUp ? ShopSyncStatus::Synced : ShopSyncStatus::Syncing,
            importedCount: $imported,
            pagesFetched: $pages,
            hasMore: ! $caughtUp,
            message: $imported === 0
                ? 'Already up to date.'
                : 'Updated '.$imported.' '.Str::plural('order', $imported).'.',
        );
    }

    /**
     * Mark the historical import as done and hand over to the incremental pass.
     */
    private function completeBackfill(ShopSyncState $state, int $imported, int $pages): ShopSyncResult
    {
        // `last_synced_at` is deliberately left where the first backfill run
        // put it; see handle().
        $state->update(['backfill_completed_at' => now(), 'backfill_offset' => 0]);

        return new ShopSyncResult(
            status: ShopSyncStatus::Synced,
            importedCount: $imported,
            pagesFetched: $pages,
            message: 'Imported '.$imported.' historical '.Str::plural('order', $imported).'.',
        );
    }

    /**
     * Ask the shop for one page of orders.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, mixed> raw order payloads, straight from JSON
     */
    private function fetchPage(Shop $shop, array $query): array
    {
        $response = $this->client
            ->withTimeout((int) config('services.woocommerce.sync_timeout'))
            ->get($shop, 'orders', $query);

        if (! $response->successful()) {
            $detail = $response->json('message');

            throw new RuntimeException(Str::limit(
                'The shop returned HTTP '.$response->status().
                (is_string($detail) && $detail !== '' ? ': '.Str::squish($detail) : '.'),
                250,
            ));
        }

        $payloads = $response->json();

        return is_array($payloads) ? $payloads : [];
    }

    /**
     * Get the newest value of a timestamp field across a page of orders.
     *
     * @param  array<int, mixed>  $payloads
     */
    private function furthestTimestamp(array $payloads, string $field): ?CarbonImmutable
    {
        $furthest = null;

        foreach ($payloads as $payload) {
            $value = is_array($payload) ? ($payload[$field] ?? null) : null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $candidate = CarbonImmutable::parse($value, 'UTC');

            if ($furthest === null || $candidate->greaterThan($furthest)) {
                $furthest = $candidate;
            }
        }

        return $furthest;
    }

    /**
     * Record that the sync could not finish.
     */
    private function recordFailure(ShopSyncState $state, string $message): ShopSyncResult
    {
        $message = Str::limit(Str::squish($message), 250);

        $state->update([
            'status' => ShopSyncStatus::Failed,
            'last_error' => $message,
            'last_finished_at' => now(),
        ]);

        return new ShopSyncResult(status: ShopSyncStatus::Failed, message: $message);
    }

    /**
     * Get the query parameters every order request shares.
     *
     * @return array<string, mixed>
     */
    private function baseQuery(): array
    {
        return [
            'per_page' => $this->pageSize(),
            'status' => 'any',
            // Without this, WooCommerce reads the date filters in the shop's
            // own timezone while returning timestamps in GMT.
            'dates_are_gmt' => 'true',
        ];
    }

    private function pageSize(): int
    {
        return (int) config('services.woocommerce.sync_page_size');
    }

    private function maxPages(): int
    {
        return (int) config('services.woocommerce.sync_max_pages_per_run');
    }

    /**
     * Decide whether this run has done enough and should hand over.
     *
     * A long history is walked across several runs so that no single job
     * holds a worker for an hour, and so that the run ends on its own terms
     * rather than being killed mid-page by the queue's timeout. The check
     * comes after a page rather than before one, so a run always makes
     * progress even if the budget is set absurdly low.
     */
    private function runIsOver(int $pages, float $runStartedAt): bool
    {
        return $pages >= $this->maxPages()
            || (microtime(true) - $runStartedAt) >= $this->maxSeconds();
    }

    private function maxSeconds(): int
    {
        return (int) config('services.woocommerce.sync_max_seconds_per_run');
    }

    private function overlapMinutes(): int
    {
        return (int) config('services.woocommerce.sync_overlap_minutes');
    }
}
