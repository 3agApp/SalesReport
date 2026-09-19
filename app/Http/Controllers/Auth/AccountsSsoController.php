<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\Concerns\RedirectsToCurrentOrganization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class AccountsSsoController extends Controller
{
    use RedirectsToCurrentOrganization;

    /**
     * Send the guest to 3AG Accounts for login.
     *
     * prompt=consent forces Accounts to show the continue-as / switch-account
     * screen even when the browser already has an Accounts session.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('oidc_accounts')
            ->with(['prompt' => 'consent'])
            ->redirect();
    }

    /**
     * Finish the OIDC callback and start a local SalesReport session.
     */
    public function callback(): RedirectResponse
    {
        try {
            $oidcUser = Socialite::driver('oidc_accounts')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/')
                ->with('status', __('Could not sign in with 3AG Accounts. Please try again.'));
        }

        $email = $oidcUser->getEmail();

        if (! filled($email)) {
            return redirect('/')
                ->with('status', __('3AG Accounts did not return an email address.'));
        }

        $user = User::query()->where('sso_id', $oidcUser->getId())->first()
            ?? User::query()->where('email', Str::lower($email))->first();

        if ($user) {
            $user->forceFill([
                'sso_id' => $oidcUser->getId(),
                'name' => $oidcUser->getName() ?: $user->name,
                'email' => Str::lower($email),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::query()->create([
                'sso_id' => $oidcUser->getId(),
                'name' => $oidcUser->getName() ?: Str::before($email, '@'),
                'email' => Str::lower($email),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::password(32)),
            ]);
        }

        Auth::login($user, remember: true);

        $invitation = request()->session()->pull('organization_invitation');

        if (is_string($invitation) && $invitation !== '') {
            return redirect()->route('invitations.index');
        }

        return redirect()->intended(
            $this->redirectPathForCurrentOrganization(request(), '/dashboard'),
        );
    }
}
