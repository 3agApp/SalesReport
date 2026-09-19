<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Sends the user to 3AG Accounts to sign in.
 */
class ThreeAgRedirectController
{
    public function __invoke(): RedirectResponse|SymfonyRedirectResponse
    {
        return Socialite::driver('3ag')->redirect();
    }
}
