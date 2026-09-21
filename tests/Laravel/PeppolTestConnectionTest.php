<?php

declare(strict_types=1);

use Darvis\UblPeppol\PeppolService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'ubl-peppol.url' => 'https://access-point.test/send',
        'ubl-peppol.username' => 'user',
        'ubl-peppol.password' => 'secret',
    ]);

    Http::preventStrayRequests();
});

it('reports a working connection', function (int $status) {
    Http::fake(['access-point.test/*' => Http::response('', $status)]);

    $result = (new PeppolService)->testConnection();

    expect($result['success'])->toBeTrue()
        ->and($result['status_code'])->toBe($status);
})->with([
    'ok' => [200],
    'no content' => [204],
    // The send URL only takes a POST at most providers, so a GET is answered with 405 or 400.
    // The provider was reached and did not refuse the credentials.
    'method not allowed' => [405],
    'bad request' => [400],
]);

it('reports a failure instead of "Connection successful"', function (int $status, string $message) {
    Http::fake(['access-point.test/*' => Http::response('', $status)]);

    $result = (new PeppolService)->testConnection();

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe($status)
        ->and($result['message'])->toBe($message);
})->with([
    'wrong credentials' => [401, 'Authentication failed - check credentials'],
    'credentials not allowed here' => [403, 'Access denied - the credentials are not allowed to use this URL'],
    'wrong url' => [404, 'Peppol URL not found - check PEPPOL_URL'],
    'provider error' => [500, 'The Peppol provider answered with a server error (HTTP 500)'],
    'provider unavailable' => [503, 'The Peppol provider answered with a server error (HTTP 503)'],
]);

it('says which status it got when the provider was reached but did not answer 2xx', function () {
    Http::fake(['access-point.test/*' => Http::response('', 405)]);

    expect((new PeppolService)->testConnection()['message'])
        ->toBe('Peppol provider reached (HTTP 405); the credentials were not refused');
});

it('keeps the message for a plain success', function () {
    Http::fake(['access-point.test/*' => Http::response('', 200)]);

    expect((new PeppolService)->testConnection()['message'])->toBe('Connection successful');
});

it('reports a provider that cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    $result = (new PeppolService)->testConnection();

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(0)
        ->and($result['message'])->toBe('Cannot connect to Peppol provider');
});
