<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Organization;
use App\Models\Shop;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The number of recently updated shops shown on the dashboard.
     */
    private const int RECENT_SHOPS = 5;

    public function __invoke(Request $request, Organization $currentOrganization): Response
    {
        return Inertia::render('dashboard', [
            'stats' => [
                'shops' => $currentOrganization->shops()->count(),
                'members' => $currentOrganization->members()->count(),
                'shopsNeedingAttention' => $currentOrganization->shops()->needingAttention()->count(),
                'orders' => Order::query()
                    ->whereIn('shop_id', $currentOrganization->shops()->select('id'))
                    ->count(),
            ],
            'recentShops' => $currentOrganization->shops()
                ->latest('updated_at')
                ->latest('id')
                ->take(self::RECENT_SHOPS)
                ->get()
                ->map(fn (Shop $shop) => [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'url' => $shop->url,
                    'host' => $shop->host(),
                    'platformLabel' => $shop->platform->label(),
                    'updatedAtDiff' => $shop->updated_at?->diffForHumans(),
                    'connection' => [
                        'status' => $shop->connection_status->value,
                        'statusLabel' => $shop->connection_status->label(),
                        'tone' => $shop->connection_status->tone(),
                        'message' => $shop->connection_message,
                        'checkedAtDiff' => $shop->connection_checked_at?->diffForHumans(),
                    ],
                ]),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }
}
