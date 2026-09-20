<?php

use App\Enums\OrganizationRole;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\Pages\ViewOrganization;
use App\Filament\Resources\Organizations\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    config(['admin.email' => 'admin@example.com']);

    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
});

describe('access', function () {
    test('guests are sent to the app login', function () {
        $this->get('/admin')->assertRedirect(route('login'));
    });

    test('users other than the admin are forbidden', function () {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    });

    test('the admin can open every page', function () {
        $user = User::factory()->create();
        $organization = $user->currentOrganization;

        $this->actingAs($this->admin);

        $this->get('/admin')->assertOk();
        $this->get(UserResource::getUrl('index'))->assertOk();
        $this->get(UserResource::getUrl('view', ['record' => $user]))->assertOk();
        $this->get(UserResource::getUrl('edit', ['record' => $user]))->assertOk();
        $this->get(OrganizationResource::getUrl('index'))->assertOk();
        $this->get(OrganizationResource::getUrl('view', ['record' => $organization]))->assertOk();
        $this->get(OrganizationResource::getUrl('edit', ['record' => $organization]))->assertOk();
    });

    test('the admin email is matched case-insensitively', function () {
        config(['admin.email' => 'ADMIN@Example.com']);

        expect($this->admin->isAdmin())->toBeTrue();
    });

    test('nobody is the admin when no email is configured', function () {
        config(['admin.email' => null]);

        $this->actingAs($this->admin)->get('/admin')->assertForbidden();
    });

    test('the app tells the frontend who the admin is', function () {
        $this->actingAs($this->admin)
            ->get(route('dashboard', ['current_organization' => $this->admin->currentOrganization->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', true));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_organization' => $user->currentOrganization->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', false));
    });
});

describe('users', function () {
    beforeEach(fn () => $this->actingAs($this->admin));

    test('users can be listed and searched', function () {
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$alice, $bob])
            ->searchTable('alice')
            ->assertCanSeeTableRecords([$alice])
            ->assertCanNotSeeTableRecords([$bob]);
    });

    test('a user can be edited without changing their password', function () {
        $user = User::factory()->unverified()->create();
        $password = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm([
                'name' => 'Renamed',
                'email' => 'renamed@example.com',
                'email_verified_at' => '2026-09-01 10:00:00',
                'password' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        expect($user->name)->toBe('Renamed')
            ->and($user->email)->toBe('renamed@example.com')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->password)->toBe($password);
    });

    test('a user can be given a new password', function () {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['password' => 'a-new-Password-123'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Hash::check('a-new-Password-123', $user->fresh()->password))->toBeTrue();
    });

    test('deleting a user hands on the organizations they own', function () {
        $user = User::factory()->create();
        $ownedOrganization = $user->currentOrganization;

        $member = User::factory()->create();
        $ownedOrganization->members()->attach($member, ['role' => OrganizationRole::Member->value]);
        $member->update(['current_organization_id' => $ownedOrganization->id]);

        $otherOrganization = Organization::factory()->create();
        $otherOrganization->members()->attach($user, ['role' => OrganizationRole::Member->value]);

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($user);
        // The member is still working in it, so the organization stays and they take
        // it on. Removing one account should not take a catalogue with it.
        $this->assertNotSoftDeleted($ownedOrganization);
        $this->assertNotSoftDeleted($otherOrganization);

        expect($ownedOrganization->fresh()->owner()?->id)->toBe($member->id)
            ->and($member->fresh()->current_organization_id)->toBe($ownedOrganization->id);
    });

    test('deleting a user winds up a organization nobody else is in', function () {
        $user = User::factory()->create();
        $ownedOrganization = $user->currentOrganization;

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($user);
        $this->assertSoftDeleted($ownedOrganization);
    });

    test('the admin cannot delete or impersonate themselves', function () {
        Livewire::test(ViewUser::class, ['record' => $this->admin->getKey()])
            ->assertActionHidden(DeleteAction::class)
            ->assertActionHidden('impersonate');
    });

    test('two-factor authentication and passkeys can be reset', function () {
        $user = User::factory()->withTwoFactor()->create();
        $user->passkeys()->create([
            'name' => 'Laptop',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]);

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction('resetTwoFactor');

        $user->refresh();

        expect($user->two_factor_secret)->toBeNull()
            ->and($user->two_factor_confirmed_at)->toBeNull()
            ->and($user->passkeys()->count())->toBe(0);
    });
});

describe('impersonation', function () {
    test('the admin can sign in as a user and return', function () {
        $user = User::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction('impersonate')
            ->assertRedirect(route('onboarding'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard', ['current_organization' => $user->currentOrganization->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('impersonating', true)
                ->where('auth.user.id', $user->id)
                ->where('auth.isAdmin', false));

        $this->get('/admin')->assertForbidden();

        $this->delete(route('impersonation.destroy'))
            ->assertRedirect(UserResource::getUrl('view', ['record' => $user]));

        $this->assertAuthenticatedAs($this->admin);

        $this->get('/admin')->assertOk();
    });

    test('stopping without impersonating is forbidden', function () {
        $this->actingAs(User::factory()->create())
            ->delete(route('impersonation.destroy'))
            ->assertForbidden();
    });
});

describe('organizations', function () {
    beforeEach(fn () => $this->actingAs($this->admin));

    test('organizations can be listed', function () {
        $organizations = Organization::factory()->count(2)->create();

        Livewire::test(ListOrganizations::class)->assertCanSeeTableRecords($organizations);
    });

    test('a organization can be edited', function () {
        $organization = User::factory()->create()->currentOrganization;
        $slug = $organization->slug;

        Livewire::test(EditOrganization::class, ['record' => $organization->getRouteKey()])
            ->fillForm([
                'name' => 'Renamed Organization',
                'timezone' => 'Europe/Berlin',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $organization->refresh();

        expect($organization->name)->toBe('Renamed Organization')
            // The slug is the organization's address and deliberately does
            // not follow a rename, so every link already sent still works.
            ->and($organization->slug)->toBe($slug)
            ->and($organization->timezone)->toBe('Europe/Berlin')
            ->and($organization->reportingTimezone())->toBe('Europe/Berlin');
    });

    test('a organization can be deleted and restored with a new owner', function () {
        $owner = User::factory()->withoutOrganization()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);
        $owner->update(['current_organization_id' => $organization->id]);

        Livewire::test(ViewOrganization::class, ['record' => $organization->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($organization);
        expect($organization->memberships()->count())->toBe(0)
            ->and($owner->fresh()->current_organization_id)->toBeNull();

        $newOwner = User::factory()->create();

        Livewire::test(ViewOrganization::class, ['record' => $organization->getRouteKey()])
            ->callAction('restore', data: ['owner_id' => $newOwner->id])
            ->assertHasNoFormErrors();

        $this->assertNotSoftDeleted($organization);
        expect($newOwner->fresh()->ownsOrganization($organization))->toBeTrue();
    });

    test('members can be added, have their role changed and be removed', function () {
        $owner = User::factory()->create();
        $organization = $owner->currentOrganization;
        $user = User::factory()->create();

        $manager = Livewire::test(MembershipsRelationManager::class, [
            'ownerRecord' => $organization,
            'pageClass' => ViewOrganization::class,
        ]);

        $manager->callAction(TestAction::make('addMember')->table(), data: [
            'user_id' => $user->id,
            'role' => OrganizationRole::Member->value,
        ])->assertHasNoFormErrors();

        expect($user->organizationRole($organization))->toBe(OrganizationRole::Member);

        $membership = $organization->memberships()->where('user_id', $user->id)->first();

        $manager->callAction(TestAction::make('changeRole')->table($membership), data: [
            'role' => OrganizationRole::Admin->value,
        ])->assertHasNoFormErrors();

        expect($user->organizationRole($organization))->toBe(OrganizationRole::Admin);

        $user->update(['current_organization_id' => $organization->id]);

        $manager->callAction(TestAction::make('remove')->table($membership));

        expect($user->fresh()->belongsToOrganization($organization))->toBeFalse()
            ->and($user->fresh()->current_organization_id)->toBe($user->ownedOrganizations()->first()->id);
    });

    test('the owner cannot be removed, only replaced', function () {
        $owner = User::factory()->create();
        $organization = $owner->currentOrganization;
        $admin = User::factory()->create();
        $organization->members()->attach($admin, ['role' => OrganizationRole::Admin->value]);

        $ownerMembership = $organization->ownerMembership;
        $adminMembership = $organization->memberships()->where('user_id', $admin->id)->first();

        Livewire::test(MembershipsRelationManager::class, [
            'ownerRecord' => $organization,
            'pageClass' => ViewOrganization::class,
        ])
            ->assertActionHidden(TestAction::make('remove')->table($ownerMembership))
            ->callAction(TestAction::make('transferOwnership')->table($adminMembership));

        expect($admin->organizationRole($organization))->toBe(OrganizationRole::Owner)
            ->and($owner->organizationRole($organization))->toBe(OrganizationRole::Admin);
    });
});
