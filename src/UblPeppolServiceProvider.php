<?php

namespace Darvis\UblPeppol;

use Darvis\UblPeppol\Console\CleanupPeppolLogsCommand;
use Illuminate\Support\ServiceProvider;

class UblPeppolServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ubl-peppol.php', 'ubl-peppol');

        $this->app->singleton('ubl-peppol', function ($app) {
            return new UblNLBis3Service();
        });

        $this->app->singleton(PeppolService::class, function ($app) {
            return new PeppolService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupPeppolLogsCommand::class,
            ]);
        }

        // Publish config
        $this->publishes([
            __DIR__.'/../config/ubl-peppol.php' => config_path('ubl-peppol.php'),
        ], 'ubl-peppol-config');

        // Load migrations automatically
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publish migrations (optional, for customization)
        $this->publishes([
            __DIR__.'/../database/migrations/create_peppol_logs_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_peppol_logs_table.php'),
        ], 'ubl-peppol-migrations');
    }
}
