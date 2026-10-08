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

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | Registers a local (stdio) MCP server when laravel/mcp is installed, so an
    | AI assistant can look up what a PEPPOL rule demands. It reads nothing from
    | your application and sends nothing anywhere.
    | Start it with: php artisan mcp:start <handle>
    |
    */

    'mcp' => [
        'enabled' => (bool) env('UBL_PEPPOL_MCP_ENABLED', true),
        'handle' => env('UBL_PEPPOL_MCP_HANDLE', 'ubl-peppol'),
    ],
];
