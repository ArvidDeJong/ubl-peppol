<?php

declare(strict_types=1);

use Darvis\UblPeppol\ViesService;

/**
 * VIES can be down, slow or unreachable. Every failure is "unknown" (valid false, error set),
 * never an uncaught exception and never mistaken for "this number does not exist".
 */
it('reports any failure to reach VIES as unknown, not only a SOAP fault', function () {
    $service = new class extends ViesService
    {
        protected function createClient(): SoapClient
        {
            throw new RuntimeException('network is unreachable');
        }
    };

    $result = $service->checkVat('NL', '123456789B01');

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->toBe('VIES check failed: network is unreachable');
});

it('restores the socket timeout after a check', function () {
    $before = ini_get('default_socket_timeout');

    $service = new class extends ViesService
    {
        protected function createClient(): SoapClient
        {
            throw new RuntimeException('down');
        }
    };
    $service->checkVat('NL', '123456789B01');

    expect(ini_get('default_socket_timeout'))->toBe($before);
});
