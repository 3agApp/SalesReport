<?php

use App\Enums\OrganizationRole;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('the profile page shows the identity accounts holds, read only', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@3ag.local']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.name', 'Ada Lovelace')
            ->where('auth.user.email', 'ada@3ag.local'),
        );
});

test('there is no route left to edit the profile with', function () {
    expect(Route::has('profile.update'))->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->patch('/settings/profile', ['name' => 'Someone Else', 'email' => 'someone-else@3ag.local'])
        ->assertMethodNotAllowed();
});

test('the email address a user signs in with cannot be changed from here', function () {
    $user = User::factory()->create(['email' => 'ada@3ag.local']);

    $this->actingAs($user)
        ->post('/settings/profile', ['name' => 'Someone Else', 'email' => 'victim@3ag.local'])
        ->assertMethodNotAllowed();

    expect($user->fresh()->email)->toBe('ada@3ag.local');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'confirmation' => 'DELETE',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('delete confirmation must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'confirmation' => 'nope',
        ]);

    $response
        ->assertSessionHasErrors('confirmation')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('deleting an account hands its organizations to the longest standing admin', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    $newestAdmin = User::factory()->create();
    $oldestAdmin = User::factory()->create();
    $member = User::factory()->create();

    // Created out of order, so the test proves it picks by standing rather
    // than by whichever row happens to come back first.
    $organization->memberships()->create(['user_id' => $member->id, 'role' => OrganizationRole::Member]);
    $organization->memberships()->create(['user_id' => $newestAdmin->id, 'role' => OrganizationRole::Admin])
        ->forceFill(['created_at' => now()->addDay()])->save();
    $organization->memberships()->create(['user_id' => $oldestAdmin->id, 'role' => OrganizationRole::Admin])
        ->forceFill(['created_at' => now()->subDay()])->save();

    $this
        ->actingAs($owner)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect('/');

    expect($organization->fresh()->owner()?->id)->toBe($oldestAdmin->id)
        ->and($oldestAdmin->fresh()->ownsOrganization($organization))->toBeTrue();
});

test('deleting an account falls back to the longest standing member', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    $first = User::factory()->create();
    $second = User::factory()->create();

    $organization->memberships()->create(['user_id' => $second->id, 'role' => OrganizationRole::Member])
        ->forceFill(['created_at' => now()->addDay()])->save();
    $organization->memberships()->create(['user_id' => $first->id, 'role' => OrganizationRole::Member])
        ->forceFill(['created_at' => now()->subDay()])->save();

    $this
        ->actingAs($owner)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect('/');

    expect($organization->fresh()->owner()?->id)->toBe($first->id);
});

test('deleting an account winds up an organization nobody else is in', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;
    $shop = Shop::factory()->for($organization)->create();
    Order::factory()->for($shop)->create();

    $this
        ->actingAs($owner)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect('/');

    expect(Organization::withTrashed()->find($organization->id)->trashed())->toBeTrue()
        ->and(Shop::count())->toBe(0)
        ->and(Order::count())->toBe(0);

    // And nothing is left for the scheduler to keep calling.
    $this->artisan('shops:sync-orders');
    Queue::assertNothingPushed();
});

test('deleting an account leaves organizations it only belonged to alone', function () {
    $owner = User::factory()->create();
    $organization = $owner->currentOrganization;

    $member = User::factory()->create();
    $organization->memberships()->create(['user_id' => $member->id, 'role' => OrganizationRole::Member]);

    $this
        ->actingAs($member)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect('/');

    expect($organization->fresh()->owner()?->id)->toBe($owner->id)
        ->and($organization->fresh()->members()->count())->toBe(1);
});
