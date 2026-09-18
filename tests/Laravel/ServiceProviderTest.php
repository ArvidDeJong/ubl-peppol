<?php

declare(strict_types=1);

use Darvis\UblPeppol\Models\PeppolLog;
use Darvis\UblPeppol\PeppolService;
use Darvis\UblPeppol\UblNlBis3Service;
use Darvis\UblPeppol\UblPeppolServiceProvider;
use Illuminate\Support\Facades\Schema;

it('ships the defaults a host app gets without publishing the config', function () {
    expect(config('ubl-peppol.log_retention_days'))->toBe(60);
    expect(config('ubl-peppol'))->toHaveKeys(['log_retention_days', 'password', 'url', 'username']);
});

it('binds the services as singletons', function () {
    expect(app('ubl-peppol'))->toBeInstanceOf(UblNlBis3Service::class);
    expect(app(PeppolService::class))->toBe(app(PeppolService::class));
});

it('publishes the config and the migration under their own tags', function () {
    $config = UblPeppolServiceProvider::pathsToPublish(UblPeppolServiceProvider::class, 'ubl-peppol-config');
    $migrations = UblPeppolServiceProvider::pathsToPublish(UblPeppolServiceProvider::class, 'ubl-peppol-migrations');

    expect($config)->not->toBeEmpty()
        ->and(array_values($config)[0])->toEndWith('config/ubl-peppol.php');
    expect($migrations)->not->toBeEmpty()
        ->and(array_values($migrations)[0])->toContain('create_peppol_logs_table');
});

it('registers the cleanup command', function () {
    expect(array_keys(app('Illuminate\Contracts\Console\Kernel')->all()))->toContain('peppol:cleanup');
});

it('creates the peppol_logs table when the published migration runs', function () {
    // The migration is opt-in, so load the packaged file the way a host app would after publishing.
    $migration = require dirname(__DIR__, 2).'/database/migrations/create_peppol_logs_table.php.stub';
    $migration->up();

    expect(Schema::hasTable('peppol_logs'))->toBeTrue();
    expect(PeppolLog::query()->count())->toBe(0);
});
