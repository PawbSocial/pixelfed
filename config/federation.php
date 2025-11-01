<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ActivityPub
    |--------------------------------------------------------------------------
    |
    | ActivityPub configuration
    |
    */
    'activitypub' => [
        'enabled' => env('ACTIVITY_PUB', false),
        'outbox' => env('AP_OUTBOX', true),
        'inbox' => env('AP_INBOX', true),
        'sharedInbox' => env('AP_SHAREDINBOX', true),

        'remoteFollow' => env('AP_REMOTE_FOLLOW', true),

        'delivery' => [
            'timeout' => env('ACTIVITYPUB_DELIVERY_TIMEOUT', 30),
            'concurrency' => env('ACTIVITYPUB_DELIVERY_CONCURRENCY', 10),
            'logger' => [
                'enabled' => env('AP_LOGGER_ENABLED', false),
                'driver' => 'log'
            ]
        ],

        'ingest' => [
            'store_notes_without_followers' => env('AP_INGEST_STORE_NOTES_WITHOUT_FOLLOWERS', false),
        ],

        'authorized_fetch' => env('AUTHORIZED_FETCH', false),

        'relay' => [
            'enabled' => env('AP_RELAY_ENABLED', false),
            'auto_follow' => env('AP_RELAY_AUTO_FOLLOW', true),
            'delivery_timeout' => env('AP_RELAY_DELIVERY_TIMEOUT', 30),
            'max_failed_deliveries' => env('AP_RELAY_MAX_FAILED_DELIVERIES', 10),
            'retry_interval' => env('AP_RELAY_RETRY_INTERVAL', 3600), // seconds
        ],
    ],

    'atom' => [
        'enabled' => env('ATOM_FEEDS', true),
    ],

    'avatars' => [
        'store_local' => env('REMOTE_AVATARS', true),
    ],

    'nodeinfo' => [
        'enabled' => env('NODEINFO', true),
    ],

    'webfinger' => [
        'enabled' => env('WEBFINGER', true)
    ],

    'network_timeline' => env('PF_NETWORK_TIMELINE', true),
    'network_timeline_days_falloff' => env('PF_NETWORK_TIMELINE_DAYS_FALLOFF', 90),

    'custom_emoji' => [
        'enabled' => env('CUSTOM_EMOJI', false),

        // max size in bytes, default is 2mb
        'max_size' => env('CUSTOM_EMOJI_MAX_SIZE', 2000000),
    ],

    'migration' => env('PF_ACCT_MIGRATION_ENABLED', true),
];
