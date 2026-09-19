<?php

use App\Enums\OrganizationRole;
use App\Enums\ShopConnectionStatus;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Shop;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();

    $this->get(route('dashboard', ['current_organization' => $user->currentOrganization->slug]))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('dashboard summarises the current organization', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    Shop::factory()->count(3)->for($organization)->create();
    Shop::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.shops', 3)
            ->where('stats.members', 1)
            ->has('recentShops', 3)
        );
});

test('dashboard counts the shops whose connection needs attention', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    Shop::factory()->for($organization)->connected()->create();
    Shop::factory()->for($organization)->create();
    Shop::factory()->for($organization)->failing()->create();
    Shop::factory()->for($organization)->failing(ShopConnectionStatus::Unreachable)->create();

    // A shop belonging to another organization is never counted here.
    Shop::factory()->failing()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.shops', 4)
            ->where('stats.shopsNeedingAttention', 2)
            ->where('recentShops.0.connection.status', ShopConnectionStatus::Unreachable->value)
        );
});

test('dashboard shows at most five recent shops', function () {
    $user = User::factory()->create();

    Shop::factory()->count(7)->for($user->currentOrganization)->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('recentShops', 5));
});

test('dashboard does not include a pending invitations list', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);

    OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->missing('pendingInvitations')
            ->where('pendingInvitationsCount', 1),
        );
});
