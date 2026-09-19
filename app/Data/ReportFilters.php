<?php

namespace App\Data;

use App\Enums\ReportInterval;
use App\Enums\ReportPeriod;
use App\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Everything that narrows a report: when, which shops, which order statuses.
 */
readonly class ReportFilters
{
    /**
     * @param  array<int>  $shopIds  the shops in scope, already checked to belong to the organization
     * @param  array<string>  $statuses  the WooCommerce statuses counted as revenue
     */
    public function __construct(
        public ReportPeriod $period,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $timezone,
        public array $shopIds,
        public array $statuses,
    ) {}

    /**
     * Get the range boundaries as UTC instants.
     *
     * The boundaries are local to the organization, but `placed_at` is stored
     * in UTC and Eloquent formats a date using whatever timezone it carries.
     * Anything comparing against the column has to convert first, or a range
     * silently drifts by the offset.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function utcRange(): array
    {
        return [$this->from->utc(), $this->to->utc()];
    }

    /**
     * Get the same filters over the period immediately before this one.
     *
     * Calendar periods step back a calendar step rather than a fixed number of
     * days, so a part-finished month compares against the same days of the
     * month before rather than against a full one. Rolling and custom ranges
     * shift back by their own length.
     */
    public function forPreviousPeriod(): self
    {
        [$from, $to] = match ($this->period) {
            ReportPeriod::Today, ReportPeriod::Yesterday => [$this->from->subDay(), $this->to->subDay()],
            ReportPeriod::ThisMonth, ReportPeriod::LastMonth => $this->shiftMonths(1),
            ReportPeriod::ThisQuarter, ReportPeriod::LastQuarter => $this->shiftMonths(3),
            ReportPeriod::ThisYear, ReportPeriod::LastYear => $this->shiftMonths(12),
            default => $this->shiftByLength(),
        };

        return new self(
            period: ReportPeriod::Custom,
            from: $from,
            to: $to,
            timezone: $this->timezone,
            shopIds: $this->shopIds,
            statuses: $this->statuses,
        );
    }

    /**
     * Step the range back by whole months.
     *
     * A range that ended on the last day of its month keeps ending on the last
     * day, so a 31 day month never compares against 30 days of a shorter one.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function shiftMonths(int $months): array
    {
        $endsOnLastDay = $this->to->isSameDay($this->to->endOfMonth());

        $from = $this->from->subMonthsNoOverflow($months);
        $to = $this->to->subMonthsNoOverflow($months);

        return [$from, $endsOnLastDay ? $to->endOfMonth() : $to];
    }

    /**
     * Step the range back by its own length, leaving no gap between the two.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function shiftByLength(): array
    {
        $days = (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;

        return [$this->from->subDays($days), $this->to->subDays($days)];
    }

    /**
     * Get the bucket size the time series should use.
     */
    public function interval(): ReportInterval
    {
        return ReportInterval::forRange($this->from, $this->to);
    }

    /**
     * Get the statuses a report counts unless told otherwise.
     *
     * Only orders whose payment went through: an on-hold order is money the
     * shop hopes for, not money it has taken.
     *
     * @return array<string>
     */
    public static function defaultStatuses(): array
    {
        return Order::SETTLED_STATUSES;
    }

    /**
     * Describe the range for the interface and for export filenames.
     */
    public function rangeLabel(): string
    {
        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period->value,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'timezone' => $this->timezone,
            'shopIds' => $this->shopIds,
            'statuses' => $this->statuses,
            'interval' => $this->interval()->value,
            'rangeLabel' => $this->rangeLabel(),
        ];
    }
}
