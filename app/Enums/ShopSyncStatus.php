<?php

namespace App\Enums;

enum ShopSyncStatus: string
{
    case Pending = 'pending';
    case Backfilling = 'backfilling';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Failed = 'failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not synced yet',
            self::Backfilling => 'Importing history',
            self::Syncing => 'Syncing',
            self::Synced => 'Synced',
            self::Failed => 'Sync failed',
        };
    }

    /**
     * Get the colour tone the interface should use.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Synced => 'positive',
            self::Backfilling, self::Syncing => 'warning',
            self::Failed => 'negative',
            self::Pending => 'neutral',
        };
    }

    /**
     * Determine if a sync is currently running.
     */
    public function isRunning(): bool
    {
        return $this === self::Backfilling || $this === self::Syncing;
    }
}
