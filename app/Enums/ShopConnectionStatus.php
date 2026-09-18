<?php

namespace App\Enums;

enum ShopConnectionStatus: string
{
    case Unknown = 'unknown';
    case Connected = 'connected';
    case InvalidCredentials = 'invalid_credentials';
    case InsufficientPermissions = 'insufficient_permissions';
    case NotFound = 'not_found';
    case RequiresHttps = 'requires_https';
    case Unreachable = 'unreachable';
    case Failed = 'failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Not tested yet',
            self::Connected => 'Connected',
            self::InvalidCredentials => 'Invalid credentials',
            self::InsufficientPermissions => 'Missing permissions',
            self::NotFound => 'API not found',
            self::RequiresHttps => 'HTTPS required',
            self::Unreachable => 'Unreachable',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the explanation shown to the bookkeeper when no message came back
     * from the shop itself.
     */
    public function description(): string
    {
        return match ($this) {
            self::Unknown => 'This shop has not been tested yet.',
            self::Connected => 'Connected. Orders are readable.',
            self::InvalidCredentials => 'WooCommerce rejected the consumer key and secret. Regenerate them under WooCommerce → Settings → Advanced → REST API.',
            self::InsufficientPermissions => 'The consumer key works but is not allowed to read orders. Give it Read access in WooCommerce.',
            self::NotFound => 'The WooCommerce REST API was not found at this URL. Check that WooCommerce is active and permalinks are not set to Plain.',
            self::RequiresHttps => 'WooCommerce only accepts a consumer key and secret over HTTPS. Update the shop URL to https://.',
            self::Unreachable => 'The shop could not be reached. It may be offline, or the domain or certificate may be wrong.',
            self::Failed => 'The shop returned an error.',
        };
    }

    /**
     * Get the colour tone the badge should use in the interface.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Connected => 'positive',
            self::Unknown => 'neutral',
            self::InsufficientPermissions, self::RequiresHttps => 'warning',
            self::InvalidCredentials, self::NotFound, self::Unreachable, self::Failed => 'negative',
        };
    }

    /**
     * Determine if the shop is usable as a source of sales data.
     */
    public function isHealthy(): bool
    {
        return $this === self::Connected;
    }

    /**
     * Determine if the status is a failure someone has to act on.
     *
     * A shop that has never been tested is not yet a problem.
     */
    public function needsAttention(): bool
    {
        return $this !== self::Connected && $this !== self::Unknown;
    }

    /**
     * Get the statuses that mean something is wrong with the shop.
     *
     * @return array<string>
     */
    public static function needingAttention(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status) => $status->needsAttention())
            ->map(fn (self $status) => $status->value)
            ->values()
            ->toArray();
    }
}
