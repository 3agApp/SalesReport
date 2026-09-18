<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Shops\ShopConnectionController;
use App\Http\Controllers\Shops\ShopController;
use App\Http\Controllers\Shops\ShopOrderSyncController;
use App\Http\Middleware\EnsureOrganizationMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('onboarding', OnboardingController::class)
    ->middleware(['auth', 'verified'])
    ->name('onboarding');

Route::prefix('{current_organization}')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('shops', [ShopController::class, 'index'])->name('shops.index');
        Route::post('shops', [ShopController::class, 'store'])->name('shops.store');
        Route::patch('shops/{shop}', [ShopController::class, 'update'])->name('shops.update');
        Route::delete('shops/{shop}', [ShopController::class, 'destroy'])->name('shops.destroy');

        Route::post('shops/{shop}/connection', ShopConnectionController::class)
            ->middleware('throttle:10,1')
            ->name('shops.connection.test');

        Route::post('shops/{shop}/sync', ShopOrderSyncController::class)
            ->middleware('throttle:10,1')
            ->name('shops.sync.store');
    });

Route::get('invitations', [OrganizationInvitationController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('invitations.index');

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [OrganizationInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [OrganizationInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
