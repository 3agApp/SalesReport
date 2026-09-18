<?php

namespace App\Http\Controllers\Shops;

use App\Http\Controllers\Controller;
use App\Jobs\Shops\SyncShopOrders;
use App\Models\Organization;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ShopOrderSyncController extends Controller
{
    /**
     * Queue an order sync for the shop.
     *
     * Unlike a connection test, this can take minutes on a shop with years of
     * history, so it goes on the queue rather than running in the request.
     *
     * The organization is resolved first so the shop binding is scoped to it.
     */
    public function __invoke(Organization $currentOrganization, Shop $shop): RedirectResponse
    {
        Gate::authorize('syncOrders', $shop);

        SyncShopOrders::dispatch($shop);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order sync queued. This page will show the result once it finishes.'),
        ]);

        return back();
    }
}
