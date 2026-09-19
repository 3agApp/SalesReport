<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps this app's session in step with 3AG Accounts.
 *
 * Two jobs, both about the boundary between the local session and the identity
 * provider's: carrying the ID token past the point where logging out destroys
 * the session, and — once SSO is the only way in — closing the local routes
 * that would let someone sign in without the provider.
 */
class ThreeAgSingleSignOn
{
    /**
     * The session key holding the ID token from the last sign-in.
     */
    public const ID_TOKEN = '3ag.id_token';

    /**
     * Routes that offer a local alternative to signing in with 3AG.
     *
     * @var array<int, string>
     */
    protected const REDIRECTED = [
        'login',
        'register',
        'password.request',
        'password.reset',
    ];

    /**
     * Routes that would authenticate or enrol someone without the provider.
     *
     * @var array<int, string>
     */
    protected const BLOCKED = [
        'login.store',
        'register.store',
        'password.email',
        'password.update',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Fortify invalidates the session before the logout response is built,
        // so the ID token has to be lifted out of it first.
        if ($request->routeIs('logout')) {
            $request->attributes->set(self::ID_TOKEN, $request->session()->get(self::ID_TOKEN));
        }

        if (! config('services.3ag.sso_only')) {
            return $next($request);
        }

        if ($request->routeIs(...self::REDIRECTED)) {
            return redirect()->route('auth.accounts.redirect');
        }

        abort_if($request->routeIs(...self::BLOCKED), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
