<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Shop;
use App\Services\Reports\SalesReport;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Show the organization's sales report.
     */
    public function __invoke(ReportFilterRequest $request, Organization $currentOrganization): Response
    {
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
            'statusOptions' => collect(Order::OPEN_STATUSES + ['cancelled', 'failed'])
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => ucfirst(str_replace('-', ' ', $status)),
                ])
                ->values(),
            'summary' => $report->summary(),
            // The heavier breakdowns are deferred so the page paints with its
            // headline numbers first rather than waiting on all of them.
            'series' => Inertia::defer(fn () => $report->series()),
            'byShop' => Inertia::defer(fn () => $report->byShop()),
            'topProducts' => Inertia::defer(fn () => $report->topProducts()),
            'byStatus' => Inertia::defer(fn () => $report->byStatus()),
        ]);
    }
}
