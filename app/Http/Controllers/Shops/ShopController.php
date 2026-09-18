<?php

namespace App\Http\Controllers\Shops;

use App\Enums\ShopPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shops\SaveShopRequest;
use App\Models\Organization;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    /**
     * The number of shops shown per page.
     */
    private const int PER_PAGE = 10;

    /**
     * Show the organization's shops.
     */
    public function index(Request $request, Organization $currentOrganization): Response|RedirectResponse
    {
        Gate::authorize('viewAny', [Shop::class, $currentOrganization]);

        $search = $request->string('search')->trim()->toString();

        $shops = $currentOrganization->shops()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('url', 'like', "%{$search}%")))
            ->orderByRaw('LOWER(name)')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        if ($shops->isEmpty() && $shops->currentPage() > 1) {
            return redirect($shops->url($shops->lastPage()));
        }

        return Inertia::render('shops/index', [
            'shops' => $shops->through(fn (Shop $shop) => $this->toShopPayload($shop)),
            'filters' => ['search' => $search],
            'platforms' => ShopPlatform::options(),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    /**
     * Store a newly created shop.
     */
    public function store(SaveShopRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->shops()->create($request->shopAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shop added.')]);

        return back();
    }

    /**
     * Update the specified shop.
     *
     * The organization is resolved first so the shop binding is scoped to it.
     */
    public function update(SaveShopRequest $request, Organization $currentOrganization, Shop $shop): RedirectResponse
    {
        $shop->update($request->shopAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shop updated.')]);

        return back();
    }

    /**
     * Delete the specified shop.
     *
     * The organization is resolved first so the shop binding is scoped to it.
     */
    public function destroy(Organization $currentOrganization, Shop $shop): RedirectResponse
    {
        Gate::authorize('delete', $shop);

        $shop->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shop removed.')]);

        return back();
    }

    /**
     * Build the payload for a shop.
     *
     * Credentials are write-only, so only a masked hint of the consumer key
     * ever reaches the browser.
     *
     * @return array{id: int, name: string, url: string, host: string, platform: string, platformLabel: string, consumerKeyHint: string, updatedAtDiff: string|null}
     */
    private function toShopPayload(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'url' => $shop->url,
            'host' => $shop->host(),
            'platform' => $shop->platform->value,
            'platformLabel' => $shop->platform->label(),
            'consumerKeyHint' => $shop->consumerKeyHint(),
            'updatedAtDiff' => $shop->updated_at?->diffForHumans(),
        ];
    }
}
