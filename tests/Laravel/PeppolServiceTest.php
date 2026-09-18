<?php

declare(strict_types=1);

use Darvis\UblPeppol\PeppolService;

/**
 * An application that only generates XML still resolves this service, for example through the
 * container when something type-hints it. Without credentials that used to fail with a TypeError
 * in the constructor, before the readable "not configured" message could ever be thrown.
 */
it('can be constructed without any credentials configured', function () {
    config([
        'ubl-peppol.url' => null,
        'ubl-peppol.username' => null,
        'ubl-peppol.password' => null,
    ]);

    $service = new PeppolService;

    expect($service->getConfig())->toBe([
        'url' => '',
        'username' => '',
        'password_configured' => false,
    ]);
});

it('explains which credential is missing instead of failing obscurely', function () {
    config([
        'ubl-peppol.url' => null,
        'ubl-peppol.username' => null,
        'ubl-peppol.password' => null,
    ]);

    expect(fn () => (new PeppolService)->testConnection())
        ->toThrow(RuntimeException::class, 'Peppol URL is not configured');
});

it('reads the credentials from the config', function () {
    config([
        'ubl-peppol.url' => 'https://example.test/peppol',
        'ubl-peppol.username' => 'arvid',
        'ubl-peppol.password' => 'secret',
    ]);

    expect((new PeppolService)->getConfig())->toBe([
        'url' => 'https://example.test/peppol',
        'username' => 'arvid',
        'password_configured' => true,
    ]);
});

it('never puts the password in what getConfig returns', function () {
    config(['ubl-peppol.password' => 'secret']);

    expect(json_encode((new PeppolService)->getConfig()))->not->toContain('secret');
});
