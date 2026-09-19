<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /**
     * Send guests to 3AG Accounts. Local email/password login is disabled.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $invitation = $request->query('invitation');

        if (is_string($invitation) && $invitation !== '') {
            $request->session()->put('organization_invitation', $invitation);
        }

        return redirect()->route('auth.accounts.redirect');
    }
}
