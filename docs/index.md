---
title: Home
nav_order: 1
description: darvis/ubl-peppol builds UBL 2.1 invoices and credit notes that pass PEPPOL BIS Billing 3.0 and EN 16931 validation, in plain PHP or inside Laravel.
---

# UBL PEPPOL

Builds UBL 2.1 invoices and credit notes that pass **PEPPOL BIS Billing 3.0** and **EN 16931** validation, with separate rule sets for the Netherlands and Belgium. It is a plain PHP library: Laravel is optional and lives in its own layer.

```bash
composer require darvis/ubl-peppol
```

PHP 8.2 or newer with the DOM extension. Nothing else is required to generate invoices.

## What it does

- Builds Dutch (NLCIUS) and Belgian (EN 16931) invoices from your own data, element by element or in one call
- Builds credit notes with the billing reference the rules demand
- Validates a document against the business rules before you send it, so a receiver does not reject it
- Checks European VAT numbers against VIES, and company registration numbers such as the KvK number and the Belgian ondernemingsnummer
- Sends the result to the PEPPOL network through your access point provider, and logs what came back

## Where to start

- [Getting started](getting-started.md) builds your first invoice, with and without Laravel
- [Dutch invoices](netherlands.md) and [Belgian invoices](belgium.md) cover what each country expects
- [Validation](validation.md) explains how to check a document before it leaves your application
- [Troubleshooting](troubleshooting.md) is the place to look when a receiver rejects one

## Reading the PEPPOL rules

Every rule this package enforces comes from the [PEPPOL BIS Billing 3.0 specification](https://docs.peppol.eu/poacc/billing/3.0/bis/). When a receiver rejects an invoice, that document is the authority, and the rule code in the rejection (`BR-CO-11`, `PEPPOL-EN16931-R010`) points straight at the paragraph that explains why.

Before going live, run a generated document through an official validator:

- [Dutch PEPPOL validator](https://test.peppolautoriteit.nl/validate)
- [Ecosio validator](https://ecosio.com/en/peppol-and-xml-document-validator/) for Belgian documents

## Support

Bugs and questions go to [GitHub issues](https://github.com/ArvidDeJong/ubl-peppol/issues). A vulnerability goes to the [security advisories](https://github.com/ArvidDeJong/ubl-peppol/security/advisories/new) instead.
