<?php

declare(strict_types=1);

namespace Darvis\UblPeppol;

use Darvis\UblPeppol\Console\CleanupPeppolLogsCommand;
use Illuminate\Support\ServiceProvider;

/**
 * The Laravel layer of the package. The invoice builders and the validator work without it;
 * this provider only wires them into an application and adds the log table and the command.
 */
final class UblPeppolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ubl-peppol.php', 'ubl-peppol');

        $this->app->singleton(UblNlBis3Service::class);
        $this->app->alias(UblNlBis3Service::class, 'ubl-peppol');

        $this->app->singleton(PeppolService::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/ubl-peppol.php' => config_path('ubl-peppol.php'),
        ], 'ubl-peppol-config');

        // The log table is opt-in: an application that only builds XML does not need it.
        // Publish it, then run the migration.
        $this->publishes([
            __DIR__.'/../database/migrations/create_peppol_logs_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_peppol_logs_table.php'),
        ], 'ubl-peppol-migrations');

        $this->commands([
            CleanupPeppolLogsCommand::class,
        ]);
    }
}
