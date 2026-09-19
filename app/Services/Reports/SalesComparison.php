<?php

namespace App\Services\Reports;

use App\Data\ReportComparison;
use App\Data\ReportFilters;

/**
 * Sets one period's figures against the period immediately before it.
 */
class SalesComparison
{
    /**
     * Compare a report against the period before its range.
     */
    public function handle(ReportFilters $filters, SalesReport $report): ReportComparison
    {
        $previousFilters = $filters->forPreviousPeriod();
        $previous = new SalesReport($previousFilters);
        $earliest = $report->earliestOrderAt();

        return ReportComparison::between(
            current: $report->summary(),
            previous: $previous->summary(),
            series: $previous->series(),
            rangeLabel: $previousFilters->rangeLabel(),
            // Nothing was imported before this, so a rise against it would say
            // more about when the sync started than about the shop.
            partial: $earliest !== null && $previousFilters->from->lessThan($earliest),
        );
    }
}
