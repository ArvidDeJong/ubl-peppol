<?php

declare(strict_types=1);

use Darvis\UblPeppol\UblPeppolConfig;

beforeEach(function () {
    // The command touches the log table, which is opt-in; load it the way a host app would.
    $migration = require dirname(__DIR__, 2).'/database/migrations/create_peppol_logs_table.php.stub';
    $migration->up();
});

it('reads every key through the accessor, with the shipped defaults', function () {
    expect(UblPeppolConfig::logRetentionDays())->toBe(60)
        ->and(UblPeppolConfig::url())->toBe('')
        ->and(UblPeppolConfig::username())->toBe('')
        ->and(UblPeppolConfig::password())->toBe('');
});

it('follows a changed retention setting', function () {
    config(['ubl-peppol.log_retention_days' => 90]);

    expect(UblPeppolConfig::logRetentionDays())->toBe(90);
});

it('uses the configured retention when the command runs without --days', function () {
    config(['ubl-peppol.log_retention_days' => 90]);

    $this->artisan('peppol:cleanup')
        ->expectsOutputToContain('older than 90 days')
        ->assertSuccessful();
});

it('lets --days win over the config', function () {
    config(['ubl-peppol.log_retention_days' => 90]);

    $this->artisan('peppol:cleanup', ['--days' => 7])
        ->expectsOutputToContain('older than 7 days')
        ->assertSuccessful();
});
