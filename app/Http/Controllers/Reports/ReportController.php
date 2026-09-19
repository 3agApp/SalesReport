<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Models\Organization;
use App\Models\Shop;
use App\Services\Reports\SalesComparison;
use App\Services\Reports\SalesReport;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Show the organization's sales report.
     */
    public function __invoke(
        ReportFilterRequest $request,
        Organization $currentOrganization,
        SalesComparison $comparison,
    ): Response {
        $filters = $request->filters();
        $report = new SalesReport($filters);

        return Inertia::render('reports/index', [
            'filters' => $filters->toArray(),
            'periods' => ReportPeriod::options(),
            'shops' => $currentOrganization->shops()
                ->orderByRaw('LOWER(name)')
                ->get()
                ->map(fn (Shop $shop) => ['id' => $shop->id, 'name' => $shop->name])
                ->values(),
            // What the shops themselves say they have, not a list we guessed
            // at, so a status a store invented can still be counted.
            'statusOptions' => collect($currentOrganization->orderStatuses())
                ->map(fn (string $label, string $status) => [
                    'value' => $status,
                    'label' => $label,
                ])
                ->values(),
            'summary' => $report->summary(),
            // The heavier breakdowns are deferred so the page paints with its
            // headline numbers first rather than waiting on all of them.
            'series' => Inertia::defer(fn () => $report->series()),
            'byShop' => Inertia::defer(fn () => $report->byShop()),
            'topProducts' => Inertia::defer(fn () => $report->topProducts()),
            'byStatus' => Inertia::defer(fn () => $report->byStatus()),
            'comparison' => Inertia::defer(fn () => $comparison->handle($filters, $report)->toArray()),
        ]);
    }
}
