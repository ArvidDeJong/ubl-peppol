<?php

namespace Darvis\UblPeppol;

use Illuminate\Support\ServiceProvider;

class UblPeppolServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('ubl-peppol', function ($app) {
            return new UblBis3Service();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Eventuele config of resources hier laden indien nodig
    }
}
