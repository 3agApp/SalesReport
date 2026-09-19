<?php

namespace App\Data;

use App\Enums\ShopConnectionStatus;

readonly class ShopConnectionResult
{
    public function __construct(
        public ShopConnectionStatus $status,
        public string $message,
        public ?int $responseTimeMs = null,
    ) {
        //
    }

    /**
     * Build a result for a status, optionally adding detail the shop reported.
     */
    public static function for(ShopConnectionStatus $status, ?string $detail = null, ?int $responseTimeMs = null): self
    {
        $message = $detail === null || trim($detail) === ''
            ? $status->description()
            : $status->description().' ('.trim($detail).')';

        return new self($status, mb_substr($message, 0, 255), $responseTimeMs);
    }
}
