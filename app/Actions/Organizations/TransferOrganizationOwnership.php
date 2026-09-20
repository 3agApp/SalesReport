<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferOrganizationOwnership
{
    /**
     * Make the user the organization's owner, keeping the previous owner on
     * as an admin.
     */
    public function handle(Organization $organization, User $newOwner): void
    {
        DB::transaction(function () use ($organization, $newOwner) {
            $organization->memberships()
                ->where('role', OrganizationRole::Owner->value)
                ->where('user_id', '!=', $newOwner->id)
                ->update(['role' => OrganizationRole::Admin->value]);

            $organization->memberships()->updateOrCreate(
                ['user_id' => $newOwner->id],
                ['role' => OrganizationRole::Owner],
            );
        });
    }
}
