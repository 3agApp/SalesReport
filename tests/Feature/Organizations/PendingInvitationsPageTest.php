<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot visit the invitations page', function () {
    $this->get(route('invitations.index'))->assertRedirect(route('login'));
});

test('the invitations page lists pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create(['name' => 'Acme Organization']);

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('invitations.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('invitations/index')
        ->has('invitations', 1)
        ->where('invitations.0.code', $invitation->code)
        ->where('invitations.0.inviterName', 'Taylor Otwell')
        ->where('invitations.0.organization.name', 'Acme Organization')
        ->where('invitations.0.organization.slug', $organization->slug)
        ->where('invitations.0.roleLabel', $invitation->role->label())
        ->where('pendingInvitationsCount', 1)
        ->missing('invitations.0.organizationName'),
    );
});

test('the invitations page does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);

    OrganizationInvitation::factory()->accepted()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('invitations.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('invitations/index')
        ->has('invitations', 0),
    );
});

test('the invitations page excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('invitations.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('invitations/index')
        ->has('invitations', 0),
    );

    $this->assertDatabaseHas('organization_invitations', [
        'id' => $invitation->id,
    ]);
});

test('the invitations page does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $organization->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('invitations.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('invitations/index')
        ->has('invitations', 0),
    );

    $this->assertDatabaseHas('organization_invitations', [
        'id' => $invitation->id,
    ]);
});

test('the pending invitation count is shared with every page', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'Invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);

    OrganizationInvitation::factory()->count(2)->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('pendingInvitationsCount', 2)
            ->missing('pendingInvitations'),
        );
});
