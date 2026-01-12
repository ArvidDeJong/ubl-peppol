<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Peppol Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Peppol access point provider credentials here.
    | These settings are used by the PeppolService to send invoices
    | to the Peppol network.
    |
    */

    'url' => env('PEPPOL_URL'),
    'username' => env('PEPPOL_USERNAME'),
    'password' => env('PEPPOL_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Log Cleanup
    |--------------------------------------------------------------------------
    |
    | Number of days to keep Peppol logs before automatic cleanup.
    |
    */

    'log_retention_days' => env('PEPPOL_LOG_RETENTION_DAYS', 60),
];
