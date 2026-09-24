---
title: "Testing"
nav_order: 14
description: "Test code that uses darvis/ubl-peppol without calling your provider or VIES: Http::fake(), with and without the peppol_logs table, and a ViesService mock."
---

# Testing

Your tests must never post a document to a real provider or call VIES. This page shows how to test each part of the package in a Laravel application. The examples use [Pest](https://pestphp.com); with PHPUnit the same calls go inside test methods.

## Test the XML you build

The builders are plain PHP and need no fake. Build the document and assert on the result:

```php
// tests/Unit/BuildInvoiceXmlTest.php
use Darvis\UblPeppol\UblBeBis3Service;

it('builds a Belgian invoice that adds up', function () {
    $ubl = new UblBeBis3Service();

    $ubl->createDocument();
    $ubl->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14');
    $ubl->addBuyerReference('CLIENT-001');
    $ubl->addInvoiceLine([
        'id' => '1',
        'quantity' => 2,
        'unit_code' => 'C62',
        'price_amount' => 100.00,
        'currency' => 'EUR',
        'name' => 'Consultancy',
        'description' => 'IT consultancy, 2 days',
        'tax_category_id' => 'S',
        'tax_percent' => 21.0,
        'tax_scheme_id' => 'VAT',
    ]);

    $calculated = $ubl->calculateTotals();
    $ubl->addTaxTotal($calculated['tax_totals']);
    $ubl->addLegalMonetaryTotal($calculated['totals'], 'EUR');

    expect($ubl->validate()->isValid())->toBeTrue();

    $xml = $ubl->generateXml();

    expect($xml)
        ->toContain('<cbc:ID>INV-2026-001</cbc:ID>')
        ->toContain('<cbc:PayableAmount currencyID="EUR">242.00</cbc:PayableAmount>');
});
```

In your own application you test the class that maps your invoice model to the builder, for example `BuildInvoiceXml` from [Laravel integration](laravel.md#build-an-invoice-inside-laravel), and assert on the XML it returns.

Use a fixed invoice date in the past. `addInvoiceHeader()` refuses a date after today, so a test with `now()->addDay()` fails.

## Test sending with `Http::fake()`

`PeppolService` uses Laravel's HTTP client, so `Http::fake()` replaces the provider ([Laravel docs](https://laravel.com/docs/http-client#testing)). `Http::preventStrayRequests()` makes any request you did not fake throw, which guards against a test that reaches a real provider.

```php
// tests/Feature/SendToPeppolTest.php
use Darvis\UblPeppol\PeppolService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Set the config before the service is resolved: it reads the credentials when it is created.
    config([
        'ubl-peppol.url' => 'https://provider.test/send',
        'ubl-peppol.username' => 'user',
        'ubl-peppol.password' => 'secret',
    ]);

    Http::preventStrayRequests();
});

it('posts the XML to the provider', function () {
    Http::fake(['provider.test/*' => Http::response(['id' => 'abc'], 200)]);

    $result = app(PeppolService::class)->sendUblXml('<Invoice/>', 'INV-2026-001');

    expect($result['success'])->toBeTrue()
        ->and($result['status_code'])->toBe(200)
        ->and($result['response'])->toBe(['id' => 'abc']);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://provider.test/send'
            && $request->hasHeader('Content-Type', 'application/xml')
            && $request->body() === '<Invoice/>';
    });
});

it('reports a refusal', function () {
    Http::fake(['provider.test/*' => Http::response('BR-CO-15 failed', 422)]);

    $result = app(PeppolService::class)->sendUblXml('<Invoice/>', 'INV-2026-001');

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(422)
        ->and($result['error'])->toBe('BR-CO-15 failed');
});

it('reports a provider that cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    $result = app(PeppolService::class)->sendUblXml('<Invoice/>');

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(0)
        ->and($result['error'])->toBe('Could not resolve host');
});

it('throws without credentials', function () {
    config(['ubl-peppol.password' => null]);

    app(PeppolService::class)->sendUblXml('<Invoice/>');
})->throws(RuntimeException::class, 'Peppol password is not configured (PEPPOL_PASSWORD)');
```

Each test gets a fresh application, so each `app(PeppolService::class)` reads the config of that test. Inside one test, change the config **before** the first `app(PeppolService::class)`, or use `new PeppolService()`.

In a real feature test you call your own route or job instead of the service, and keep the same `Http::fake()` and assertions.

## With and without the log table

Whether a test has a `peppol_logs` table depends on your application.

**You published the migration.** With the `RefreshDatabase` trait, Laravel runs your migrations for the test, so the table exists and you can assert on the row:

```php
// tests/Feature/PeppolLogTest.php
use Darvis\UblPeppol\PeppolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('logs a send', function () {
    config([
        'ubl-peppol.url' => 'https://provider.test/send',
        'ubl-peppol.username' => 'user',
        'ubl-peppol.password' => 'secret',
    ]);

    Http::fake(['provider.test/*' => Http::response(['id' => 'abc'], 200)]);

    $result = app(PeppolService::class)->sendUblXml('<Invoice/>', 'INV-2026-001');

    $this->assertDatabaseHas('peppol_logs', [
        'id' => $result['log_id'],
        'invoice_nr' => 'INV-2026-001',
        'status' => 'success',
        'http_status_code' => 200,
    ]);
});
```

**You did not publish the migration.** Sending works the same and the result has no log row:

```php
expect($result['log_id'])->toBeNull();
```

Code that must work in both situations can ask `Darvis\UblPeppol\Models\PeppolLog::tableExists()`.

## Test `sendInvoice()` with your own model

`sendInvoice()` calls `$invoice->update(['peppol_sent_at' => now()])` after a successful send. Test it with a real model and a real table, so a missing `peppol_sent_at` column shows up in the test and not in production:

```php
it('marks the invoice as sent', function () {
    Http::fake(['provider.test/*' => Http::response(['id' => 'abc'], 200)]);

    $invoice = Invoice::factory()->create();

    $result = app(PeppolService::class)->sendInvoice($invoice, '<Invoice/>');

    expect($result['success'])->toBeTrue()
        ->and($invoice->fresh()->peppol_sent_at)->not->toBeNull();
});
```

Without the column this test fails on `success`, with the database error in `$result['error']`. See [the pitfall](peppol-service.md#sendinvoice-updates-your-model).

## Replace `ViesService`

`ViesService` makes a SOAP call, which `Http::fake()` does not catch. Replace the class in the container instead. This works when your code resolves it with `app(ViesService::class)` or through dependency injection, not with `new ViesService()`.

```php
// tests/Feature/CustomerVatNumberTest.php
use Darvis\UblPeppol\ViesService;
use Mockery\MockInterface;

it('accepts a VAT number VIES knows', function () {
    $this->mock(ViesService::class, function (MockInterface $mock) {
        $mock->shouldReceive('checkFullVatNumber')
            ->with('BE0999000228')
            ->andReturn([
                'valid' => true,
                'name' => 'Test Company NV',
                'address' => 'Kerkstraat 123, 2000 Antwerpen',
                'countryCode' => 'BE',
                'vatNumber' => '0999000228',
                'fullVatNumber' => 'BE0999000228',
                'checked_at' => '2026-01-15 10:30:00',
                'error' => null,
            ]);
    });

    $this->post('/customers', ['vat_number' => 'BE0999000228'])
        ->assertSessionHasNoErrors();
});
```

`/customers` stands for your own route. Test the outage as well: return `'valid' => false` with `'error' => 'Member state service unavailable'`, and assert that your code does not refuse the customer.

`CompanyRegistrationService` and `UblValidator` make no network call and need no fake.

## The package's own test suite

```bash
composer test      # Pest
composer lint      # Pint, check only
composer analyse   # Larastan
```

The tests under `tests/Laravel` boot an application through Orchestra Testbench; the rest runs as plain PHP.
