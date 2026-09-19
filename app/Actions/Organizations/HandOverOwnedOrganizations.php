<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;

/**
 * Makes sure no organization is left ownerless when a user goes.
 *
 * Memberships cascade away with the user row, so an owner who deletes their
 * account would otherwise take the only account that can manage members,
 * change the organization or delete it — leaving everyone else with a
 * workspace nobody can administer and shops nobody can stop syncing.
 */
class HandOverOwnedOrganizations
{
    public function __construct(private DeleteOrganization $deleteOrganization) {}

    /**
     * Hand on, or wind up, every organization the user owns.
     */
    public function handle(User $user): void
    {
        foreach ($user->ownedOrganizations()->get() as $organization) {
            $successor = $this->successorTo($user, $organization);

            // Nobody else is in it, so there is nothing to hand over and
            // nobody left to miss it.
            if ($successor === null) {
                $this->deleteOrganization->handle($organization);

                continue;
            }

            $successor->update(['role' => OrganizationRole::Owner]);
        }
    }

    /**
     * Pick who should take the organization on.
     *
     * The longest-standing admin, since they already hold every permission
     * but the two an owner keeps. Failing that the longest-standing member,
     * who is at least someone rather than nobody.
     */
    private function successorTo(User $user, Organization $organization): ?Membership
    {
        return $organization->memberships()
            ->where('user_id', '!=', $user->id)
            ->oldest()
            ->oldest('id')
            ->get()
            // A stable sort, so the ordering above still decides between two
            // members holding the same role.
            ->sortBy(fn (Membership $membership) => $membership->role === OrganizationRole::Admin ? 0 : 1)
            ->first();
    }
}
