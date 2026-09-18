<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    /**
     * Determine whether the user can view the organization's shops.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can create shops in the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::CreateShop);
    }

    /**
     * Determine whether the user can update the shop.
     */
    public function update(User $user, Shop $shop): bool
    {
        return $user->hasOrganizationPermission($shop->organization, OrganizationPermission::UpdateShop);
    }

    /**
     * Determine whether the user can test the shop's connection.
     *
     * Testing writes a new status and calls out to the shop, so it takes the
     * same permission as editing rather than merely viewing.
     */
    public function testConnection(User $user, Shop $shop): bool
    {
        return $user->hasOrganizationPermission($shop->organization, OrganizationPermission::UpdateShop);
    }

    /**
     * Determine whether the user can delete the shop.
     */
    public function delete(User $user, Shop $shop): bool
    {
        return $user->hasOrganizationPermission($shop->organization, OrganizationPermission::DeleteShop);
    }
}
