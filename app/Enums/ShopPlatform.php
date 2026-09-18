<?php

namespace App\Enums;

enum ShopPlatform: string
{
    case WooCommerce = 'woocommerce';

    /**
     * Get the display label for the platform.
     */
    public function label(): string
    {
        return match ($this) {
            self::WooCommerce => 'WooCommerce',
        };
    }

    /**
     * Get the platforms that can be picked when saving a shop.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $platform) => ['value' => $platform->value, 'label' => $platform->label()])
            ->values()
            ->toArray();
    }
}
