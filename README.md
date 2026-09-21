# darvis/ubl-peppol

[![Latest version](https://img.shields.io/packagist/v/darvis/ubl-peppol.svg)](https://packagist.org/packages/darvis/ubl-peppol)
[![Tests](https://github.com/ArvidDeJong/ubl-peppol/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/ubl-peppol/actions/workflows/tests.yml)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/ubl-peppol/php.svg)](https://packagist.org/packages/darvis/ubl-peppol)
[![License](https://img.shields.io/packagist/l/darvis/ubl-peppol.svg)](LICENSE)

A PHP library that builds **UBL 2.1** e-invoices for **PEPPOL BIS Billing 3.0**, the format the PEPPOL network requires (built on EN 16931). It has one builder for Dutch invoices and one for Belgian invoices and credit notes. The builders are plain PHP; an optional Laravel layer posts the XML to your access point provider.

## Features

- Dutch invoices with `UblNlBis3Service`, which puts the elements in schema order for you
- Belgian invoices and credit notes with `UblBeBis3Service`, which can add up the lines with `calculateTotals()`
- `validate()` before sending: code formats and the Dutch NL-R rules in the Dutch builder, the totals (BR-CO-10, 13, 15, 16, BR-S-08) in the Belgian builder
- `ViesService` checks a European VAT number against VIES and tells "invalid" apart from "VIES did not answer"
- `CompanyRegistrationService` checks the format of a KvK, KBO, RCS, SIREN/SIRET or Handelsregister number
- In Laravel: `PeppolService` posts the XML to your provider, with an optional `peppol_logs` table and a `peppol:cleanup` command

It is not an access point, and `validate()` is not the full Schematron a receiver runs: check a document with an [official validator](https://arviddejong.github.io/ubl-peppol/validation.html) before you go live.

## Requirements

- PHP 8.2 or newer with the `dom` and `libxml` extensions
- The `bcmath` extension to check an IBAN, the `soap` extension for `ViesService`
- Laravel 11, 12 or 13, only for the optional Laravel layer

## Installation

```bash
composer require darvis/ubl-peppol
```

In Laravel the service provider registers itself. To send invoices, set `PEPPOL_URL`, `PEPPOL_USERNAME` and `PEPPOL_PASSWORD` in `.env`. The config file and the log table are optional:

```bash
php artisan vendor:publish --tag=ubl-peppol-config
php artisan vendor:publish --tag=ubl-peppol-migrations
```

## Quick start

```php
<?php
// invoice.php

require __DIR__.'/vendor/autoload.php';

use Darvis\UblPeppol\UblNlBis3Service;

$ubl = new UblNlBis3Service();

$ubl->createDocument();
$ubl->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14');
$ubl->addBuyerReference('CLIENT-001');

$ubl->addAccountingSupplierParty(
    '12345678', '0106', '12345678', 'My Dutch Company BV',
    'Damrak 1', '1012 JS', 'Amsterdam', 'NL', 'NL123456789B01'
);
$ubl->addAccountingCustomerParty(
    '87654321', '0106', '87654321', 'Customer Company BV',
    'Nieuwezijds Voorburgwal 123', '1012 RJ', 'Amsterdam', 'NL',
    null, '87654321', null, null, null, 'NL987654321B01'
);

$ubl->addPaymentMeans('30', 'Credit transfer', 'INV-2026-001', 'NL91ABNA0417164300', null, 'ABNANL2A');
$ubl->addPaymentTerms('Payment within 30 days');

$ubl->addTaxTotal([[
    'taxable_amount' => 425.00,
    'tax_amount' => 89.25,
    'currency' => 'EUR',
    'tax_category_id' => 'S',
    'tax_percent' => 21.0,
    'tax_scheme_id' => 'VAT',
]]);

$ubl->addLegalMonetaryTotal([
    'line_extension_amount' => 425.00,
    'tax_exclusive_amount' => 425.00,
    'tax_inclusive_amount' => 514.25,
    'charge_total_amount' => 0.00,
    'payable_amount' => 514.25,
], 'EUR');

$ubl->addInvoiceLine([
    'id' => '1',
    'quantity' => 5,
    'unit_code' => 'HUR',
    'price_amount' => 85.00,
    'currency' => 'EUR',
    'name' => 'Software development',
    'description' => 'Frontend development, 5 hours',
    'tax_category_id' => 'S',
    'tax_percent' => 21.0,
]);

// Throws an InvalidArgumentException when validate() finds an error.
file_put_contents(__DIR__.'/invoice.xml', $ubl->generateXml(validateFirst: true));
```

Use `UblBeBis3Service` when the receiver is Belgian. Create a new builder for every document. The [documentation](https://arviddejong.github.io/ubl-peppol/getting-started.html) explains every argument.

## Documentation

Full documentation at **[arviddejong.github.io/ubl-peppol](https://arviddejong.github.io/ubl-peppol/)**:

- [Installation](https://arviddejong.github.io/ubl-peppol/installation.html): requirements, configuration and a check that it works
- [Your first invoice](https://arviddejong.github.io/ubl-peppol/getting-started.html): the example above, line by line
- [Dutch invoices](https://arviddejong.github.io/ubl-peppol/netherlands.html) and [Belgian invoices](https://arviddejong.github.io/ubl-peppol/belgium.html): the fields of every call and what each builder checks
- [Credit notes](https://arviddejong.github.io/ubl-peppol/credit-notes.html): with the Belgian builder
- [Validation](https://arviddejong.github.io/ubl-peppol/validation.html): what `validate()` checks and how to read the result
- [VAT numbers](https://arviddejong.github.io/ubl-peppol/vat-numbers.html) and [company numbers](https://arviddejong.github.io/ubl-peppol/company-numbers.html)
- [Laravel integration](https://arviddejong.github.io/ubl-peppol/laravel.html) and [sending invoices](https://arviddejong.github.io/ubl-peppol/peppol-service.html)
- [Testing](https://arviddejong.github.io/ubl-peppol/testing.html): `Http::fake()`, with and without the log table
- [API reference](https://arviddejong.github.io/ubl-peppol/api-reference.html), [troubleshooting](https://arviddejong.github.io/ubl-peppol/troubleshooting.html) and the [FAQ](https://arviddejong.github.io/ubl-peppol/faq.html)

Runnable examples for both countries are in `examples/`.

## Laravel Boost

The package ships a [Laravel Boost](https://github.com/laravel/boost) guideline and skill in `resources/boost/`, so an AI assistant in your project knows how the builders work. Run `php artisan boost:install`, or `php artisan boost:update --discover` in a project that already uses Boost.

## Testing

```bash
composer test      # Pest
composer lint      # Pint, check only; composer format fixes
composer analyse   # Larastan
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Found a way to inject XML through invoice data, or another vulnerability? Please report it privately; see [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE). A package by [ARVID.NL](https://arvid.nl).
