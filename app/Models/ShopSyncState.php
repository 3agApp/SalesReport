<?php

namespace App\Models;

use App\Enums\ShopSyncStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ShopSyncStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a shop's order sync has got to.
 *
 * @property int $id
 * @property int $shop_id
 * @property ShopSyncStatus $status
 * @property CarbonImmutable|null $backfill_cursor
 * @property int $backfill_offset
 * @property CarbonImmutable|null $backfill_completed_at
 * @property CarbonImmutable|null $last_synced_at
 * @property CarbonImmutable|null $last_finished_at
 * @property string|null $last_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Shop $shop
 */
#[Fillable([
    'status', 'backfill_cursor', 'backfill_offset', 'backfill_completed_at',
    'last_synced_at', 'last_finished_at', 'last_error',
])]
class ShopSyncState extends Model
{
    /** @use HasFactory<ShopSyncStateFactory> */
    use HasFactory;

    /**
     * Get the shop the state belongs to.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Determine if the historical import has finished.
     */
    public function hasBackfilled(): bool
    {
        return $this->backfill_completed_at !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShopSyncStatus::class,
            'backfill_cursor' => 'datetime',
            'backfill_offset' => 'integer',
            'backfill_completed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_finished_at' => 'datetime',
        ];
    }
}
