<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Log Retention
    |--------------------------------------------------------------------------
    |
    | Number of days the peppol:cleanup command keeps a log row before deleting
    | it. The log table itself is opt-in: publish and run the migration with
    | php artisan vendor:publish --tag=ubl-peppol-migrations
    |
    */

    'log_retention_days' => env('PEPPOL_LOG_RETENTION_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Access Point Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials of the PEPPOL access point provider that PeppolService sends
    | invoices to. Leave them empty when you only generate XML.
    |
    */

    'password' => env('PEPPOL_PASSWORD'),

    'url' => env('PEPPOL_URL'),

    'username' => env('PEPPOL_USERNAME'),
];
