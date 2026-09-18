---
title: Getting started
nav_order: 2
description: Install darvis/ubl-peppol and build your first UBL invoice, in plain PHP or in a Laravel application.
---

# Getting started

```bash
composer require darvis/ubl-peppol
```

**Requirements:** PHP 8.2 or newer with the DOM extension. Laravel is not needed to generate invoices.

## Your first invoice

Pick the builder for the country whose rules apply: `UblNlBis3Service` for the Netherlands, `UblBeBis3Service` for Belgium. The two have the same shape.

```php
use Darvis\UblPeppol\UblBeBis3Service;

$ubl = new UblBeBis3Service();

$ubl->createDocument();
$ubl->addInvoiceHeader('INV-001', '2026-01-15', '2026-02-14');
// ... supplier, customer, lines and totals

$xml = $ubl->generateXml();

file_put_contents('invoice.xml', $xml);
```

Build the document element by element in the order the specification expects. [Dutch invoices](netherlands.md) and [Belgian invoices](belgium.md) walk through a complete invoice for each country, and the [API reference](api-reference.md) lists every method.

## Check it before you send it

A receiver that rejects an invoice tells you little more than a rule code. Validating first turns that into a readable error while you can still fix it:

```php
$result = $ubl->validate();

if (! $result->isValid()) {
    throw new RuntimeException($result->getErrorsAsString());
}
```

Or let the builder do both in one step, which throws when the document does not hold up:

```php
$xml = $ubl->generateXml(validateFirst: true);
```

See [Validation](validation.md) for what is checked and what the rule codes mean.

## In a Laravel application

The service provider registers itself, so after installing you can resolve the builders from the container:

```php
$ubl = app(Darvis\UblPeppol\UblNlBis3Service::class);
```

Sending invoices, the log table and the cleanup command need a little configuration. [Laravel integration](laravel.md) covers that, and [Sending invoices](peppol-service.md) covers the access point.

## Working examples

The repository has runnable examples for both countries under `examples/`, with the invoice data separated from the code that builds the XML. They are the quickest way to see a complete document.
