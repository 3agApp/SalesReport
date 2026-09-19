<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DeleteOrganization
{
    /**
     * Delete an organization and everything only it owned.
     *
     * @param  User|null  $keeping  a user to leave alone, because the caller
     *                              switches them somewhere else afterwards
     */
    public function handle(Organization $organization, ?User $keeping = null): void
    {
        DB::transaction(function () use ($organization, $keeping) {
            User::query()
                ->where('current_organization_id', $organization->id)
                ->when($keeping, fn (Builder $query, User $user) => $query->where('id', '!=', $user->id))
                ->each(fn (User $affectedUser) => $affectedUser->switchToFallbackOrganization($organization));

            $organization->invitations()->delete();
            $organization->memberships()->delete();
            // Shops go for real. The organization is only soft deleted, so
            // the database's cascade never fires, and what would be left
            // behind is a set of live WooCommerce credentials that the
            // scheduler keeps using to call somebody's shop every quarter of
            // an hour. Orders, line items and sync state cascade from here.
            $organization->shops()->delete();
            $organization->delete();
        });
    }
}
