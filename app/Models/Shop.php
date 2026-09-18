<?php

namespace App\Models;

use App\Enums\ShopPlatform;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $url
 * @property ShopPlatform $platform
 * @property string $consumer_key
 * @property string $consumer_secret
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
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
     * Get the host of the shop URL, for display next to the shop name.
     */
    public function host(): string
    {
        return parse_url($this->url, PHP_URL_HOST) ?: $this->url;
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
        ];
    }
}
