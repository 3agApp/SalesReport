<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('an unverified user is sent to the verification prompt', function (Closure $route) {
    $user = User::factory()->unverified()->create();

    $this
        ->actingAs($user)
        ->get($route($user))
        ->assertRedirect(route('verification.notice'));
})->with([
    // The user's own organization, so this is the verification check
    // answering and not the membership check behind it.
    'dashboard' => [fn (User $user) => route('dashboard', $user->currentOrganization)],
    'reports' => [fn (User $user) => route('reports.index', $user->currentOrganization)],
    'onboarding' => [fn () => route('onboarding')],
    'invitations' => [fn () => route('invitations.index')],
    'appearance settings' => [fn () => route('appearance.edit')],
    'organization list' => [fn () => route('organizations.index')],
    'bare dashboard path' => [fn () => '/dashboard'],
]);

test('an unverified user can still reach the profile page to correct their address', function () {
    $user = User::factory()->unverified()->create();

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('a verified user reaches their own dashboard', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard', $user->currentOrganization))
        ->assertOk();
});

test('registering sends the verification mail and leaves the account unverified', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Rita Hasler',
        'email' => 'rita@example.com',
        'password' => 'password-that-is-long-enough',
        'password_confirmation' => 'password-that-is-long-enough',
    ]);

    $user = User::whereEmail('rita@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo(
        $user,
        VerifyEmail::class,
    );
});

test('the bare dashboard path lands on the current organization', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect("/{$user->currentOrganization->slug}/dashboard");
});

test('the bare dashboard path sends a user with no organization to onboarding', function () {
    $user = User::factory()->withoutOrganization()->create();

    $this
        ->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('onboarding', absolute: false));
});
