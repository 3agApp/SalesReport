<?php

namespace App\Data;

use App\Enums\ShopConnectionStatus;

readonly class ShopConnectionResult
{
    public function __construct(
        public ShopConnectionStatus $status,
        public string $message,
        public ?int $responseTimeMs = null,
        /** The currency the store sells in, when the check managed to read it. */
        public ?string $currency = null,
    ) {
        //
    }

    /**
     * Build a result for a status, optionally adding detail the shop reported.
     */
    public static function for(
        ShopConnectionStatus $status,
        ?string $detail = null,
        ?int $responseTimeMs = null,
        ?string $currency = null,
    ): self {
        $message = $detail === null || trim($detail) === ''
            ? $status->description()
            : $status->description().' ('.trim($detail).')';

        return new self($status, mb_substr($message, 0, 255), $responseTimeMs, $currency);
    }
}
