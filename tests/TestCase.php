<?php

declare(strict_types=1);

namespace Darvis\UblPeppol\Tests;

use Darvis\UblPeppol\UblPeppolServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Only the tests under tests/Laravel need this. The invoice builders and the validator
 * are plain PHP and are tested without booting an application, which is the point of
 * keeping Laravel optional.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            UblPeppolServiceProvider::class,
        ];
    }

    /**
     * An in-memory database for the PeppolLog model and a silent log channel. The package
     * config keeps its defaults, so the tests exercise what a host app gets.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('logging.default', 'null');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
