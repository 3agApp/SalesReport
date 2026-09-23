<?php

namespace App\Enums;

/**
 * A figure the by-shop report can show for each shop.
 */
enum ShopReportFigure: string
{
    case Revenue = 'revenue';
    case Tax = 'tax';

    /**
     * Get the name the figure goes by on the sheet and in the CSV.
     */
    public function label(): string
    {
        return match ($this) {
            self::Revenue => 'Net revenue',
            self::Tax => 'VAT',
        };
    }
}
