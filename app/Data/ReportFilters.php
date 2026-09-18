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
