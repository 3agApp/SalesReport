<?php

namespace App\Data;

use App\Enums\ShopSyncStatus;

readonly class ShopSyncResult
{
    public function __construct(
        public ShopSyncStatus $status,
        public int $importedCount = 0,
        public int $pagesFetched = 0,
        public bool $hasMore = false,
        public ?string $message = null,
    ) {
        //
    }

    /**
     * Determine if the sync ran to completion without an error.
     */
    public function succeeded(): bool
    {
        return $this->status !== ShopSyncStatus::Failed;
    }
}
