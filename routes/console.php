<?php

use App\Models\OrganizationInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    OrganizationInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()
    // A closure needs a name before it can be pinned to one server.
    ->description('Delete expired organization invitations')
    ->onOneServer();

Schedule::command('shops:check-connections')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Re-check WooCommerce shop connections');

Schedule::command('shops:sync-orders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Sync WooCommerce orders');
