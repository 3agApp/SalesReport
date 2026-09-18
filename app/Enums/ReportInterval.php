<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How finely a report's time series is bucketed.
 */
enum ReportInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /**
     * Choose a sensible bucket size for the length of a range.
     *
     * The aim is a readable number of points: daily detail for short ranges,
     * months for anything spanning years.
     */
    public static function forRange(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $days = $from->diffInDays($to);

        return match (true) {
            $days <= 62 => self::Day,
            $days <= 180 => self::Week,
            default => self::Month,
        };
    }

    /**
     * Move an instant back to the start of the bucket it falls in.
     */
    public function startOf(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Day => $moment->startOfDay(),
            self::Week => $moment->startOfWeek(),
            self::Month => $moment->startOfMonth(),
        };
    }

    /**
     * Step forward to the next bucket.
     */
    public function next(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Day => $moment->addDay(),
            self::Week => $moment->addWeek(),
            self::Month => $moment->addMonthNoOverflow(),
        };
    }

    /**
     * Get the label shown on the axis and in the tooltip for a bucket.
     */
    public function labelFor(CarbonImmutable $moment): string
    {
        return match ($this) {
            self::Day => $moment->format('j M Y'),
            self::Week => 'Week of '.$moment->format('j M Y'),
            self::Month => $moment->format('M Y'),
        };
    }
}
