<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'woocommerce' => [
        // Lets a shop live on a private or loopback address, for a fake shop
        // during local development. Never enable it where the app runs next
        // to anything a user should not be able to reach through it.
        'allow_private_hosts' => (bool) env('WOOCOMMERCE_ALLOW_PRIVATE_HOSTS', env('APP_ENV') === 'local'),

        'connect_timeout' => (int) env('WOOCOMMERCE_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('WOOCOMMERCE_TIMEOUT', 10),
        'recheck_after_minutes' => (int) env('WOOCOMMERCE_RECHECK_AFTER_MINUTES', 60),

        // Pulling a page of orders is far heavier than a connection check, so
        // the sync gets its own, longer timeout.
        'sync_timeout' => (int) env('WOOCOMMERCE_SYNC_TIMEOUT', 60),
        'sync_page_size' => (int) env('WOOCOMMERCE_SYNC_PAGE_SIZE', 100),
        'sync_max_pages_per_run' => (int) env('WOOCOMMERCE_SYNC_MAX_PAGES_PER_RUN', 50),
        // A run stops on whichever comes first, this or the page cap.
        // Page latency varies threefold between these shops, so a page
        // count alone is a poor guess at how long a run will take.
        'sync_max_seconds_per_run' => (int) env('WOOCOMMERCE_SYNC_MAX_SECONDS_PER_RUN', 300),
        'sync_overlap_minutes' => (int) env('WOOCOMMERCE_SYNC_OVERLAP_MINUTES', 10),
        'sync_after_minutes' => (int) env('WOOCOMMERCE_SYNC_AFTER_MINUTES', 15),
    ],

];
