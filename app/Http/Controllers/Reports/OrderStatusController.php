<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SaveOrderStatusesRequest;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets an organization say what its order statuses mean.
 *
 * WooCommerce ships with seven statuses and a store can register as many more
 * as it likes. Nothing here guesses at those: the statuses come from the
 * orders themselves, and what each one means to the books is answered by the
 * people keeping them.
 */
class OrderStatusController extends Controller
{
    /**
     * Show every status the organization's orders use.
     */
    public function index(Organization $currentOrganization): Response
    {
        Gate::authorize('viewReports', $currentOrganization);

        $counts = $currentOrganization->observedOrderStatuses();
        $settings = $currentOrganization->orderStatusSettings->keyBy('status');

        // A status that has been decided on but has no orders left still
        // belongs in the list, or the decision would silently vanish.
        $slugs = array_keys($counts + $settings->all());

        return Inertia::render('reports/statuses', [
            'statuses' => collect($slugs)
                ->map(fn (string $slug) => [
                    'status' => $slug,
                    'suggestedLabel' => Order::statusLabel($slug),
                    'label' => $settings->get($slug)?->label,
                    'countsAsRevenue' => (bool) $settings->get($slug)?->counts_as_revenue,
                    'decided' => $settings->has($slug),
                    'orderCount' => $counts[$slug] ?? 0,
                ])
                ->values(),
            // What a report falls back to while nothing has been decided.
            'defaultStatuses' => Order::SETTLED_STATUSES,
            'anyDecided' => $currentOrganization->orderStatusSettings->isNotEmpty(),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $currentOrganization),
            ],
        ]);
    }

    /**
     * Save what the statuses mean.
     */
    public function update(SaveOrderStatusesRequest $request, Organization $currentOrganization): RedirectResponse
    {
        foreach ($request->statuses() as $status) {
            $currentOrganization->orderStatusSettings()->updateOrCreate(
                ['status' => $status['status']],
                ['label' => $status['label'], 'counts_as_revenue' => $status['countsAsRevenue']],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order statuses saved.')]);

        return back();
    }
}
