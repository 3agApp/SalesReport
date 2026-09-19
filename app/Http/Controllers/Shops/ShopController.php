<?php

namespace App\Http\Controllers\Shops;

use App\Enums\ShopPlatform;
use App\Enums\ShopSyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shops\SaveShopRequest;
use App\Jobs\Shops\CheckShopConnection;
use App\Models\Organization;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
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
            ->with('syncState')
            ->withCount('orders')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($search).'%'])
                ->orWhereRaw("url LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($search).'%'])))
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
            // A second currency makes a cross-shop total impossible, so it is
            // said here rather than discovered halfway down a report.
            'currencies' => $currentOrganization->shopCurrencies(),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    /**
     * Store a newly created shop.
     */
    public function store(SaveShopRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $shop = $currentOrganization->shops()->create($request->shopAttributes());

        CheckShopConnection::dispatch($shop);

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
        $attributes = $request->shopAttributes();
        $credentialsChanged = isset($attributes['consumer_key']) || isset($attributes['consumer_secret']);

        if ($credentialsChanged) {
            $shop->forgetConnectionStatus();
        }

        $shop->update($attributes);

        if ($credentialsChanged) {
            CheckShopConnection::dispatch($shop);
        }

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
     * Escape the wildcards in a search term.
     *
     * Someone looking for "50%" means the characters, not "anything at all",
     * and an underscore in a shop name should match an underscore.
     *
     * The escape character is named explicitly, because SQLite has none by
     * default while MySQL and Postgres assume a backslash. It is an
     * exclamation mark rather than a backslash because a backslash cannot be
     * written as a one-character SQL literal on MySQL, where it would escape
     * the closing quote.
     */
    private function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /**
     * Build the payload for a shop's order sync.
     *
     * @return array{status: string, statusLabel: string, tone: string, message: string|null, checkedAtDiff: string|null, orderCount: int}
     */
    private function toSyncPayload(Shop $shop): array
    {
        $state = $shop->syncState;
        $status = $state === null ? ShopSyncStatus::Pending : $state->status;

        return [
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'tone' => $status->tone(),
            'message' => $state?->last_error,
            'checkedAtDiff' => $state?->last_finished_at?->diffForHumans(),
            'orderCount' => (int) ($shop->orders_count ?? 0),
        ];
    }

    /**
     * Build the payload for a shop.
     *
     * Credentials are write-only, so only a masked hint of the consumer key
     * ever reaches the browser.
     *
     * @return array<string, mixed>
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
            'currency' => $shop->currency,
            'consumerKeyHint' => $shop->consumerKeyHint(),
            'updatedAtDiff' => $shop->updated_at?->diffForHumans(),
            'connection' => [
                'status' => $shop->connection_status->value,
                'statusLabel' => $shop->connection_status->label(),
                'tone' => $shop->connection_status->tone(),
                'message' => $shop->connection_message,
                'checkedAtDiff' => $shop->connection_checked_at?->diffForHumans(),
            ],
            'sync' => $this->toSyncPayload($shop),
        ];
    }
}
