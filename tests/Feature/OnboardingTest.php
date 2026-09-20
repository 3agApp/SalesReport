<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot visit onboarding', function () {
    $this->get(route('onboarding'))->assertRedirect(route('login'));
});

test('users without an organization see the onboarding page', function () {
    $user = User::factory()->withoutOrganization()->create();

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('onboarding')
            ->where('currentOrganization', null)
            ->where('pendingInvitationsCount', 0),
        );
});

test('onboarding shows how many invitations are waiting', function () {
    $owner = User::factory()->create();
    $user = User::factory()->withoutOrganization()->create(['email' => 'invited@example.com']);

    OrganizationInvitation::factory()->create([
        'organization_id' => $owner->currentOrganization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('onboarding')
            ->where('pendingInvitationsCount', 1),
        );
});

test('users with an organization are sent from onboarding to their dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertRedirect(route('dashboard', ['current_organization' => $user->currentOrganization->slug]));
});

test('users whose current organization is unset are sent to an organization they belong to', function () {
    $user = User::factory()->withoutOrganization()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($user, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertRedirect(route('dashboard', ['current_organization' => $organization->slug]));
});

test('creating a first organization makes it the current organization', function () {
    $user = User::factory()->withoutOrganization()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'First Organization'])
        ->assertRedirect(route('organizations.edit', ['organization' => 'first-organization']));

    $organization = Organization::where('name', 'First Organization')->firstOrFail();

    expect($user->fresh()->current_organization_id)->toBe($organization->id)
        ->and($user->fresh()->ownsOrganization($organization))->toBeTrue();
});

test('users without an organization are sent to onboarding after signing in via sso', function () {
    User::factory()->withoutOrganization()->create([
        'email' => 'signer@example.com',
        'sso_id' => null,
    ]);

    signInThroughAccounts();

    $this->get(route('auth.accounts.callback', ['code' => 'the-code', 'state' => 'state']))
        ->assertRedirect(route('onboarding'));
});

test('declining the last invitation without an organization returns to onboarding', function () {
    $owner = User::factory()->create();
    $user = User::factory()->withoutOrganization()->create(['email' => 'invited@example.com']);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $owner->currentOrganization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($user)
        ->delete(route('invitations.decline', $invitation))
        ->assertRedirect(route('onboarding'));
});
