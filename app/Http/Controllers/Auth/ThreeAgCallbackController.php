<?php

namespace App\Http\Controllers\Auth;

use App\Http\Middleware\ThreeAgSingleSignOn;
use App\Http\Responses\Concerns\RedirectsToCurrentOrganization;
use App\Models\User;
use App\Services\Auth\ThreeAgProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Completes a sign-in that started at 3AG Accounts.
 */
class ThreeAgCallbackController
{
    use RedirectsToCurrentOrganization;

    public function __invoke(Request $request): RedirectResponse
    {
        $provider = ThreeAgProvider::resolve();

        try {
            $identity = $provider->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')->withErrors([
                'email' => 'That sign-in attempt expired. Please try again.',
            ]);
        }

        $user = $this->resolveUser($identity);

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your 3AG Accounts email address has not been verified yet.',
            ]);
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();
        $request->session()->put(ThreeAgSingleSignOn::ID_TOKEN, $provider->idToken());

        return redirect()->intended(
            $this->redirectPathForCurrentOrganization($request, Fortify::redirects('login'))
        );
    }

    /**
     * Find, link or create the local account behind the given identity.
     *
     * Returns null when the account cannot be trusted to belong to this
     * person.
     */
    protected function resolveUser(SocialiteUser $identity): ?User
    {
        $existing = User::query()->where('oidc_sub', $identity->getId())->first();

        if ($existing instanceof User) {
            return tap($existing)->update(['name' => $identity->getName()]);
        }

        // Matching an existing local account by email is only safe once the
        // provider says it owns that address; otherwise anyone who can set an
        // unverified email at the provider could claim someone else's account.
        if (! ($identity->user['email_verified'] ?? false)) {
            return null;
        }

        $byEmail = User::query()->where('email', $identity->getEmail())->first();

        if ($byEmail instanceof User) {
            $byEmail->forceFill([
                'oidc_sub' => $identity->getId(),
                'email_verified_at' => $byEmail->email_verified_at ?? now(),
            ])->save();

            return $byEmail;
        }

        return tap(User::query()->create([
            'name' => $identity->getName() ?? $identity->getEmail(),
            'email' => $identity->getEmail(),
            'password' => Str::password(32),
        ]), function (User $user) use ($identity): void {
            $user->forceFill([
                'oidc_sub' => $identity->getId(),
                'email_verified_at' => now(),
            ])->save();
        });
    }
}
