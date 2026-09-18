<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                ]),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
            'pendingInvitations' => $this->pendingInvitations($request),
        ]);
    }

    /**
     * Get the pending organization invitations for the authenticated user.
     *
     * @return Collection<int, array{code: string, inviterName: string, organization: array{name: string, slug: string}}>
     */
    private function pendingInvitations(Request $request): Collection
    {
        $email = strtolower($request->user()->email);

        return OrganizationInvitation::query()
            ->with(['inviter', 'organization'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (OrganizationInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'organization' => [
                    'name' => $invitation->organization->name,
                    'slug' => $invitation->organization->slug,
                ],
            ]);
    }
}
