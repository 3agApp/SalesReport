<?php

use App\Http\Middleware\ThreeAgSingleSignOn;
use App\Models\User;
use App\Services\Auth\ThreeAgProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

/**
 * Stand in for a completed round trip to 3AG Accounts.
 */
function fakeIdentity(array $claims = []): SocialiteUser
{
    $claims = [
        'sub' => '01J0ABCDEFGHJKMNPQRSTVWXYZ',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'email_verified' => true,
        ...$claims,
    ];

    $user = new SocialiteUser;

    return $user->setRaw($claims)->map([
        'id' => $claims['sub'],
        'name' => $claims['name'],
        'email' => $claims['email'],
    ]);
}

/**
 * Bind a provider that returns the given identity instead of calling out.
 */
function fakeProvider(SocialiteUser $identity, ?string $idToken = 'stub.id.token'): void
{
    Socialite::shouldReceive('driver')->with('3ag')->andReturn(
        Mockery::mock(ThreeAgProvider::class, function (MockInterface $mock) use ($identity, $idToken): void {
            $mock->shouldReceive('user')->andReturn($identity);
            $mock->shouldReceive('idToken')->andReturn($idToken);
        })
    );
}

test('the redirect route hands the user to 3AG Accounts', function () {
    config()->set('services.3ag.base_url', 'https://accounts.3ag.app');
    config()->set('services.3ag.client_id', 'the-client-id');

    $location = $this->get(route('auth.accounts.redirect'))
        ->assertRedirectContains('https://accounts.3ag.app/oauth/authorize')
        ->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['client_id'])->toBe('the-client-id')
        ->and($query['response_type'])->toBe('code')
        ->and($query['scope'])->toBe('openid profile email')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['code_challenge'])->not->toBeEmpty()
        ->and($query['state'])->not->toBeEmpty();
});

test('a new person gets an account on their first sign in', function () {
    fakeProvider(fakeIdentity());

    $this->get(route('auth.accounts.callback'))->assertRedirect(route('onboarding', absolute: false));

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($user->oidc_sub)->toBe('01J0ABCDEFGHJKMNPQRSTVWXYZ')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('an existing account is matched by subject, not email', function () {
    $user = User::factory()->create([
        'email' => 'renamed@example.com',
        'oidc_sub' => '01J0ABCDEFGHJKMNPQRSTVWXYZ',
    ]);

    fakeProvider(fakeIdentity(['email' => 'ada@example.com']));

    $this->get(route('auth.accounts.callback'))->assertRedirect();

    $this->assertAuthenticatedAs($user);

    expect(User::query()->count())->toBe(1);
});

test('an existing local account is linked by email exactly once', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'oidc_sub' => null]);

    fakeProvider(fakeIdentity());

    $this->get(route('auth.accounts.callback'))->assertRedirect();

    expect($user->fresh()->oidc_sub)->toBe('01J0ABCDEFGHJKMNPQRSTVWXYZ')
        ->and(User::query()->count())->toBe(1);
});

test('an unverified email is never linked to an existing account', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'oidc_sub' => null]);

    fakeProvider(fakeIdentity(['email_verified' => false]));

    $this->get(route('auth.accounts.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    expect($user->fresh()->oidc_sub)->toBeNull();
});

test('a tampered callback is rejected', function () {
    Socialite::shouldReceive('driver')->with('3ag')->andReturn(
        Mockery::mock(ThreeAgProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('user')->andThrow(new InvalidStateException);
        })
    );

    $this->get(route('auth.accounts.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the id token is kept so the session can be ended at the provider', function () {
    fakeProvider(fakeIdentity(), idToken: 'the.id.token');

    $this->get(route('auth.accounts.callback'));

    expect(session(ThreeAgSingleSignOn::ID_TOKEN))->toBe('the.id.token');
});

test('logging out ends the session at the provider when SSO is the only way in', function () {
    config()->set('services.3ag.sso_only', true);

    $user = User::factory()->create(['oidc_sub' => '01J0ABCDEFGHJKMNPQRSTVWXYZ']);

    $this->actingAs($user)->withSession([ThreeAgSingleSignOn::ID_TOKEN => 'the.id.token']);

    $this->post(route('logout'))->assertRedirectContains('/oauth/logout');

    $this->assertGuest();
});

test('logging out keeps Fortify\'s JSON response for clients that want one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession([ThreeAgSingleSignOn::ID_TOKEN => 'the.id.token']);

    $this->postJson(route('logout'))->assertNoContent();

    $this->assertGuest();
});

test('logging out stays local while local sign in is still allowed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession([ThreeAgSingleSignOn::ID_TOKEN => 'the.id.token']);

    $this->post(route('logout'))->assertRedirect('/');
});

test('SSO-only mode sends the login page to the provider', function () {
    config()->set('services.3ag.sso_only', true);

    $this->get(route('login'))->assertRedirect(route('auth.accounts.redirect'));
});

test('SSO-only mode removes the local password endpoint', function () {
    config()->set('services.3ag.sso_only', true);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});

test('local sign in still works while SSO-only mode is off', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});
