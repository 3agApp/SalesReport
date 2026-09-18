<?php

use App\Models\Organization;
use App\Models\User;

test('a user without an organization creates their first one through the onboarding form', function () {
    $user = User::factory()->withoutOrganization()->create();

    $this->actingAs($user);

    $page = visit(route('onboarding'));

    $page->assertSee('Create your first organization')
        ->fill('@onboarding-organization-name', 'Toys Online Group')
        ->click('@onboarding-organization-submit')
        ->assertPathIs('/settings/organizations/toys-online-group')
        ->assertSee('Toys Online Group')
        ->assertNoJavaScriptErrors();

    expect(Organization::sole())
        ->name->toBe('Toys Online Group')
        ->slug->toBe('toys-online-group');

    expect($user->fresh()->currentOrganization->name)->toBe('Toys Online Group');
});

test('the onboarding form shows the validation message for a reserved organization name', function () {
    $user = User::factory()->withoutOrganization()->create();

    $this->actingAs($user);

    $page = visit(route('onboarding'));

    $page->fill('@onboarding-organization-name', 'Settings')
        ->click('@onboarding-organization-submit')
        ->assertPathIs('/onboarding')
        ->assertSee('This organization name is reserved and cannot be used.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('organizations', 0);
});
