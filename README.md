# darvis/ubl-peppol

[![Latest version](https://img.shields.io/packagist/v/darvis/ubl-peppol.svg)](https://packagist.org/packages/darvis/ubl-peppol)
[![Tests](https://github.com/ArvidDeJong/ubl-peppol/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/ubl-peppol/actions/workflows/tests.yml)
[![Total downloads](https://img.shields.io/packagist/dt/darvis/ubl-peppol.svg)](https://packagist.org/packages/darvis/ubl-peppol)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/ubl-peppol/php.svg)](https://packagist.org/packages/darvis/ubl-peppol)
[![License](https://img.shields.io/packagist/l/darvis/ubl-peppol.svg)](LICENSE)

Builds **UBL 2.1** invoices and credit notes that pass **PEPPOL BIS Billing 3.0** and **EN 16931** validation, with separate rule sets for the Netherlands and Belgium. A plain PHP library: Laravel is optional.

## Features

- 🇳🇱 🇧🇪 Dutch (NLCIUS) and Belgian (EN 16931) invoices, each with its own rule set, because a field one country requires the other rejects
- 🧾 Credit notes (Belgian builder) with the billing reference BR-55 demands, and positive amounts as the specification wants them
- ✅ Validation before sending, with the rule that fired and the corrections that were applied
- 🔎 VAT numbers against VIES, and company registration numbers such as the KvK number and the Belgian ondernemingsnummer
- 📮 Sending to the PEPPOL network through your access point provider, with an optional log of what came back
- 🐘 Works without a framework; the Laravel layer is four files you can ignore

## Requirements

PHP 8.2 or newer with the DOM extension. Laravel 11, 12 or 13 only if you use the Laravel layer.

## Installation

```bash
composer require darvis/ubl-peppol
```

## Quick start

```php
use Darvis\UblPeppol\UblNlBis3Service;

$ubl = new UblNlBis3Service();

$ubl->createDocument();
$ubl->addInvoiceHeader('INV-001', '2026-01-15', '2026-02-14');
// ... supplier, customer, lines, tax total, monetary total

$xml = $ubl->generateXml(validateFirst: true);
```

Use `UblBeBis3Service` when the receiver is Belgian. Pick the builder by the receiver's country, never the sender's.

## Quick start: check before you send

```php
$result = $ubl->validate();

if (! $result->isValid()) {
    throw new RuntimeException($result->getErrorsAsString());
}
```

This checks the rules the package implements, which is not the receiver's full Schematron. Run a document through an [official validator](https://test.peppolautoriteit.nl/validate) once before going live.

## Quick start: Laravel

The service provider registers itself:

```php
$ubl = app(Darvis\UblPeppol\UblNlBis3Service::class);
```

Sending invoices needs `PEPPOL_URL`, `PEPPOL_USERNAME` and `PEPPOL_PASSWORD`. The `peppol_logs` table is opt-in:

```bash
php artisan vendor:publish --tag=ubl-peppol-config
php artisan vendor:publish --tag=ubl-peppol-migrations
```

## Documentation

Full documentation at **[arviddejong.github.io/ubl-peppol](https://arviddejong.github.io/ubl-peppol/)**:

- [Getting started](https://arviddejong.github.io/ubl-peppol/getting-started.html): your first invoice, with and without Laravel
- [Dutch invoices](https://arviddejong.github.io/ubl-peppol/netherlands.html) and [Belgian invoices](https://arviddejong.github.io/ubl-peppol/belgium.html)
- [Credit notes](https://arviddejong.github.io/ubl-peppol/credit-notes.html)
- [Validation](https://arviddejong.github.io/ubl-peppol/validation.html): what is checked and what the rule codes mean
- [VAT numbers](https://arviddejong.github.io/ubl-peppol/vat-numbers.html) and [company numbers](https://arviddejong.github.io/ubl-peppol/company-numbers.html)
- [Laravel integration](https://arviddejong.github.io/ubl-peppol/laravel.html) and [sending invoices](https://arviddejong.github.io/ubl-peppol/peppol-service.html)
- [API reference](https://arviddejong.github.io/ubl-peppol/api-reference.html), [troubleshooting](https://arviddejong.github.io/ubl-peppol/troubleshooting.html) and the [FAQ](https://arviddejong.github.io/ubl-peppol/faq.html)

Runnable examples for both countries are in `examples/`.

## Security

Found a way to inject XML through invoice data, or another vulnerability? Please report it privately; see [SECURITY.md](SECURITY.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Changes are listed in the [CHANGELOG](CHANGELOG.md).

## License

MIT, see [LICENSE](LICENSE). A package by [ARVID.NL](https://arvid.nl).
