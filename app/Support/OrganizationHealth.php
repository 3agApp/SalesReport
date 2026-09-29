<?php

namespace App\Support;

use App\Data\OrganizationPermissions;
use App\Models\Organization;

/**
 * The short list of things on the organization settings page that someone
 * has to act on. Worked out here rather than on the page so that "needs
 * attention" means the same thing everywhere it is shown.
 */
class OrganizationHealth
{
    public function __construct(
        private readonly Organization $organization,
        private readonly OrganizationPermissions $permissions,
    ) {}

    /**
     * The things someone should act on, most urgent first.
     *
     * @return array<int, array{key: string, tone: string, title: string, description: string, target: string}>
     */
    public function attention(): array
    {
        $items = [];

        $needingAttention = $this->organization->shops()->needingAttention()->count();

        if ($needingAttention > 0) {
            $items[] = [
                'key' => 'shops-needing-attention',
                'tone' => 'danger',
                'title' => trans_choice('{1} A shop has a connection problem|[2,*] :count shops have connection problems', $needingAttention),
                'description' => __('New orders from it do not come in until the connection is fixed.'),
                'target' => 'shops',
            ];
        } elseif ($this->permissions->canCreateShop && ! $this->organization->shops()->exists()) {
            $items[] = [
                'key' => 'no-shops',
                'tone' => 'warning',
                'title' => __('No shop is connected yet'),
                'description' => __('Connect a WooCommerce store so its orders show up in reports.'),
                'target' => 'shops',
            ];
        }

        if ($this->permissions->canCreateInvitation || $this->permissions->canCancelInvitation) {
            $expired = $this->organization->invitations()
                ->whereNull('accepted_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->count();

            if ($expired > 0) {
                $items[] = [
                    'key' => 'expired-invitations',
                    'tone' => 'warning',
                    'title' => trans_choice('{1} An invitation has expired|[2,*] :count invitations have expired', $expired),
                    'description' => __('Resend it so the person can still join, or cancel it.'),
                    'target' => 'invitations',
                ];
            }
        }

        return $items;
    }
}
