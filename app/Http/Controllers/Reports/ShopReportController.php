<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ShopReportFigure;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ShopReportRequest;
use App\Models\Organization;
use App\Services\Reports\SalesReport;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Each shop's revenue, its VAT or both, laid out to be printed or saved as a PDF.
 *
 * It is the sheet a bookkeeper works a VAT return out from, so it carries
 * only what that needs: the range, what was counted, the figures per shop
 * and the sums of them.
 */
class ShopReportController extends Controller
{
    /**
     * Show the printable by-shop report.
     */
    public function __invoke(ShopReportRequest $request, Organization $currentOrganization): Response
    {
        $filters = $request->filters();
        $statusLabels = $currentOrganization->orderStatuses();

        return Inertia::render('reports/print/by-shop', [
            'organizationName' => $currentOrganization->name,
            'filters' => $filters->toArray(),
            'figures' => array_map(fn (ShopReportFigure $figure) => $figure->value, $request->figures()),
            'countedStatuses' => collect($filters->statuses)
                ->map(fn (string $status) => $statusLabels[$status] ?? $status)
                ->values(),
            'report' => (new SalesReport($filters))->totalsByShop(),
            'generatedAt' => now($filters->timezone)->format('j M Y, H:i'),
        ]);
    }
}
