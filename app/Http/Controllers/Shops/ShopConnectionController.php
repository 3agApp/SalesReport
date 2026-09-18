<?php

namespace App\Http\Controllers\Shops;

use App\Actions\Shops\TestShopConnection;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ShopConnectionController extends Controller
{
    /**
     * Test the shop's WooCommerce credentials.
     *
     * This runs in the request rather than on the queue so the person who
     * pressed the button gets an answer straight away; the timeouts on the
     * client keep a slow shop from holding up the page. The hourly re-checks
     * are queued instead.
     *
     * The organization is resolved first so the shop binding is scoped to it.
     */
    public function __invoke(Organization $currentOrganization, Shop $shop, TestShopConnection $action): RedirectResponse
    {
        Gate::authorize('testConnection', $shop);

        $result = $action->handle($shop);

        Inertia::flash('toast', [
            'type' => $result->status->isHealthy() ? 'success' : 'error',
            'message' => $result->message,
        ]);

        return back();
    }
}
