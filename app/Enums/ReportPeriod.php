<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * The ready-made date ranges a bookkeeper reaches for.
 *
 * Every range is resolved in the organization's own timezone, so "this month"
 * means their month rather than a UTC one.
 */
enum ReportPeriod: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisQuarter = 'this_quarter';
    case LastQuarter = 'last_quarter';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';
    case Last30Days = 'last_30_days';
    case Last12Months = 'last_12_months';
    case Custom = 'custom';

    /**
     * Get the display label for the period.
     */
    public function label(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Yesterday => 'Yesterday',
            self::ThisMonth => 'This month',
            self::LastMonth => 'Last month',
            self::ThisQuarter => 'This quarter',
            self::LastQuarter => 'Last quarter',
            self::ThisYear => 'This year',
            self::LastYear => 'Last year',
            self::Last30Days => 'Last 30 days',
            self::Last12Months => 'Last 12 months',
            self::Custom => 'Custom',
        };
    }

    /**
     * Resolve the period into a start and end instant, in the given timezone.
     *
     * The end is the last moment of the final day rather than midnight, so a
     * range never silently drops the orders placed on its closing day.
     *
     * A period still running ends today rather than at the end of the month,
     * quarter or year. Carrying empty future days would flatten the chart and,
     * worse, make a part-finished month look like a fall against a whole one.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function resolve(string $timezone, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);

        return match ($this) {
            self::Today => [$now->startOfDay(), $now->endOfDay()],
            self::Yesterday => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            self::ThisMonth => [$now->startOfMonth(), $now->endOfDay()],
            self::LastMonth => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            self::ThisQuarter => [$now->startOfQuarter(), $now->endOfDay()],
            self::LastQuarter => [$now->subQuarterNoOverflow()->startOfQuarter(), $now->subQuarterNoOverflow()->endOfQuarter()],
            self::ThisYear => [$now->startOfYear(), $now->endOfDay()],
            self::LastYear => [$now->subYearNoOverflow()->startOfYear(), $now->subYearNoOverflow()->endOfYear()],
            self::Last30Days => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            self::Last12Months => [$now->subMonthsNoOverflow(11)->startOfMonth(), $now->endOfDay()],
            // A custom range carries its own dates; this is only a fallback.
            self::Custom => [$now->startOfMonth(), $now->endOfDay()],
        };
    }

    /**
     * Get the periods offered in the interface, custom last.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $period) => ['value' => $period->value, 'label' => $period->label()])
            ->values()
            ->toArray();
    }
}
