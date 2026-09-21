<?php

namespace App\Models;

use App\Data\ShopConnectionResult;
use App\Enums\ShopConnectionStatus;
use App\Enums\ShopPlatform;
use Carbon\CarbonImmutable;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $url
 * @property ShopPlatform $platform
 * @property string|null $currency
 * @property string $consumer_key
 * @property string $consumer_secret
 * @property ShopConnectionStatus $connection_status
 * @property string|null $connection_message
 * @property CarbonImmutable|null $connection_checked_at
 * @property int|null $connection_response_time_ms
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read int|null $orders_count
 * @property-read ShopSyncState|null $syncState
 * @property-read Collection<int, Order> $orders
 */
#[Fillable(['name', 'url', 'platform', 'consumer_key', 'consumer_secret'])]
#[Hidden(['consumer_key', 'consumer_secret'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * Normalise a shop URL down to its scheme and host, so the same site
     * cannot be added twice under slightly different spellings.
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        $parts = parse_url(Str::lower($url));

        if ($parts === false || ! isset($parts['host'])) {
            return rtrim($url, '/');
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * Get the organization that owns the shop.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the orders imported from the shop.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get where the shop's order sync has got to.
     *
     * @return HasOne<ShopSyncState, $this>
     */
    public function syncState(): HasOne
    {
        return $this->hasOne(ShopSyncState::class);
    }

    /**
     * Get the shop's sync state, creating it the first time it is needed.
     */
    public function syncStateOrCreate(): ShopSyncState
    {
        return $this->syncState()->firstOrCreate([]);
    }

    /**
     * Get the host of the shop URL, for display next to the shop name.
     */
    public function host(): string
    {
        return parse_url($this->url, PHP_URL_HOST) ?: $this->url;
    }

    /**
     * Scope the query to shops whose connection needs someone's attention.
     *
     * A shop that has never been tested is not yet a problem.
     *
     * @param  Builder<Shop>  $query
     */
    public function scopeNeedingAttention(Builder $query): void
    {
        $query->whereIn('connection_status', ShopConnectionStatus::needingAttention());
    }

    /**
     * Record the outcome of a connection check.
     *
     * Timestamps are left alone on purpose: an automatic hourly check is not
     * someone updating the shop, and letting it move `updated_at` would
     * reshuffle "recently updated" into "recently checked".
     */
    public function recordConnectionResult(ShopConnectionResult $result): void
    {
        $attributes = [
            'connection_status' => $result->status,
            'connection_message' => $result->message,
            'connection_checked_at' => now(),
            'connection_response_time_ms' => $result->responseTimeMs,
        ];

        // Set only when the check actually read it, so a shop that has gone
        // unreachable keeps the currency we already knew it sells in.
        if ($result->currency !== null) {
            $attributes['currency'] = $result->currency;
        }

        static::withoutTimestamps(fn () => $this->forceFill($attributes)->save());
    }

    /**
     * Forget the recorded connection status.
     *
     * Called when the credentials change, so a stale "Connected" badge never
     * outlives the key that earned it.
     */
    public function forgetConnectionStatus(): void
    {
        $this->forceFill([
            'connection_status' => ShopConnectionStatus::Unknown,
            'connection_message' => null,
            'connection_checked_at' => null,
            'connection_response_time_ms' => null,
            // Re-read along with everything else: new credentials can point
            // at a different store than the old ones did.
            'currency' => null,
        ])->save();
    }

    /**
     * Get a masked hint of the consumer key, so the UI can confirm which
     * credentials are stored without ever exposing them in full.
     */
    public function consumerKeyHint(): string
    {
        return 'ck_…'.Str::substr($this->consumer_key, -4);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => ShopPlatform::class,
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'connection_status' => ShopConnectionStatus::class,
            'connection_checked_at' => 'datetime',
        ];
    }
}
