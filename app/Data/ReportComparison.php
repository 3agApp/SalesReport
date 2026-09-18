<?php

namespace App\Data;

/**
 * How this period's figures stand against the one before it.
 */
readonly class ReportComparison
{
    /**
     * @param  array<string, float|null>  $deltas  percentage change per figure, null where there is nothing to compare against
     * @param  array<string, mixed>  $summary  the previous period's figures
     * @param  array<int, array<string, mixed>>  $series  the previous period's time series
     */
    public function __construct(
        public string $rangeLabel,
        public array $summary,
        public array $series,
        public array $deltas,
        public bool $partial,
    ) {}

    /**
     * The figures a delta is shown for.
     *
     * @var array<string>
     */
    private const array COMPARED = ['netRevenue', 'orderCount', 'averageOrderValue', 'itemsSold'];

    /**
     * Work out the change between two periods.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @param  array<int, array<string, mixed>>  $series
     * @param  bool  $partial  whether the earlier period reaches back further than the orders we hold
     */
    public static function between(array $current, array $previous, array $series, string $rangeLabel, bool $partial): self
    {
        $deltas = [];

        foreach (self::COMPARED as $key) {
            $deltas[$key] = self::percentageChange(
                (float) ($previous[$key] ?? 0),
                (float) ($current[$key] ?? 0),
            );
        }

        return new self($rangeLabel, $previous, $series, $deltas, $partial);
    }

    /**
     * Get the change from one figure to another as a percentage.
     *
     * Growth from nothing has no percentage worth showing, so it is left null
     * rather than reported as an infinite rise.
     */
    private static function percentageChange(float $from, float $to): ?float
    {
        if ($from === 0.0) {
            return null;
        }

        return round((($to - $from) / abs($from)) * 100, 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rangeLabel' => $this->rangeLabel,
            'summary' => $this->summary,
            'series' => $this->series,
            'deltas' => $this->deltas,
            'partial' => $this->partial,
        ];
    }
}
