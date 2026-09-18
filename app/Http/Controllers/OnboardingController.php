<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Show the first-run page for a user who does not belong to an organization yet.
     *
     * Users are no longer given an organization when they register, so this is
     * where they create one or go and accept an invitation.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($organization = $user->currentOrganization ?? $user->fallbackOrganization()) {
            return to_route('dashboard', ['current_organization' => $organization->slug]);
        }

        return Inertia::render('onboarding');
    }
}
