<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('guests visiting login are sent to the accounts sso redirect', function () {
    $this->get(route('login'))
        ->assertRedirect(route('auth.accounts.redirect'));
});

test('guests visiting register are sent to the accounts sso redirect', function () {
    $this->get(route('register'))
        ->assertRedirect(route('auth.accounts.redirect'));
});

test('login with an invitation stores it for after sso', function () {
    $this->get(route('login', ['invitation' => 'invite-code-123']))
        ->assertRedirect(route('auth.accounts.redirect'))
        ->assertSessionHas('organization_invitation', 'invite-code-123');
});

test('the accounts sso button redirects guests to the identity provider', function () {
    $redirect = redirect()->away('http://127.0.0.1:8000/oauth/authorize');

    Socialite::shouldReceive('driver')
        ->with('oidc_accounts')
        ->andReturnSelf();
    Socialite::shouldReceive('redirect')->andReturn($redirect);

    $this->get(route('auth.accounts.redirect'))
        ->assertRedirect('http://127.0.0.1:8000/oauth/authorize');
});

test('the accounts callback creates a local user and signs them in', function () {
    $socialiteUser = (new SocialiteUser)->map([
        'id' => 'oidc-sub-42',
        'name' => 'SSO Tester',
        'email' => 'sso-tester@3ag.local',
    ]);

    Socialite::shouldReceive('driver')
        ->with('oidc_accounts')
        ->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialiteUser);

    $this->get(route('auth.accounts.callback'))
        ->assertRedirect();

    $user = User::query()->where('email', 'sso-tester@3ag.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->sso_id)->toBe('oidc-sub-42')
        ->and($user->name)->toBe('SSO Tester')
        ->and(Auth::id())->toBe($user->id);
});

test('the accounts callback links an existing user by email', function () {
    $existing = User::factory()->create([
        'email' => 'existing@3ag.local',
        'name' => 'Existing User',
        'sso_id' => null,
    ]);

    $socialiteUser = (new SocialiteUser)->map([
        'id' => 'oidc-sub-99',
        'name' => 'Existing User',
        'email' => 'existing@3ag.local',
    ]);

    Socialite::shouldReceive('driver')
        ->with('oidc_accounts')
        ->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialiteUser);

    $this->get(route('auth.accounts.callback'))
        ->assertRedirect();

    expect($existing->fresh()->sso_id)->toBe('oidc-sub-99')
        ->and(Auth::id())->toBe($existing->id)
        ->and(User::query()->where('email', 'existing@3ag.local')->count())->toBe(1);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});
