<?php

namespace App\Http\Responses;

use App\Http\Middleware\ThreeAgSingleSignOn;
use App\Services\Auth\ThreeAgProvider;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session at 3AG Accounts as well as here.
 *
 * Without this, signing out of this app and then signing back in would silently
 * reuse the still-open session at the identity provider, which is not what
 * anyone means by "log out".
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        $idToken = $request->attributes->get(ThreeAgSingleSignOn::ID_TOKEN);

        if (! config('services.3ag.sso_only') || $idToken === null) {
            return redirect('/');
        }

        return redirect()->away(ThreeAgProvider::resolve()->logoutUrl($idToken));
    }
}
