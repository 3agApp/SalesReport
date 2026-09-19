<?php

namespace App\Http\Responses;

use App\Http\Middleware\ThreeAgSingleSignOn;
use App\Services\Auth\ThreeAgProvider;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session at 3AG Accounts as well as here.
 *
 * Without this, signing out of this app and then signing back in would silently
 * reuse the still-open session at the identity provider, which is not what
 * anyone means by "log out".
 *
 * Only sign-ins that came from the provider divert; everything else keeps
 * Fortify's own behaviour, JSON branch included.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        $idToken = $request->attributes->get(ThreeAgSingleSignOn::ID_TOKEN);

        if (config('services.3ag.sso_only') && $idToken !== null) {
            return redirect()->away(ThreeAgProvider::resolve()->logoutUrl($idToken));
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect(Fortify::redirects('logout', '/'));
    }
}
