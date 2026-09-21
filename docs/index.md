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
- Builds credit notes with the billing reference the rules demand, with the Belgian builder; the Dutch builder does invoices only
- Validates a document against the business rules before you send it, so a receiver does not reject it
- Checks European VAT numbers against VIES, and company registration numbers such as the KvK number and the Belgian ondernemingsnummer
- Sends the result to the PEPPOL network through your access point provider, and logs what came back

## New to PEPPOL?

Three things are worth knowing before you write any code, because they explain why this package does what it does.

**PEPPOL is a network, and you cannot reach it directly.** Sending an invoice means handing it to an *access point provider*: a company you have a contract with, that is connected to the network and delivers on your behalf (Storecove and SupplyDrive are examples). This package builds the document and can hand it to your provider's API. It is not an access point itself, and without a provider you can still generate and validate invoices, you just cannot send them.

**An e-invoice is not a PDF, it is a document that must obey rules.** The rules come in layers: EN 16931 is the European standard, PEPPOL BIS Billing 3.0 is the profile built on it that the network requires, and each country adds its own on top (NLCIUS for the Netherlands). A receiver checks your document against all of them and rejects it as a whole if one rule fails, which is why this package has a separate builder per country.

**A rejection tells you a rule code, not a sentence.** Something like `BR-CO-11` or `PEPPOL-EN16931-R010`. That code is a lookup key into the [specification](https://docs.peppol.eu/poacc/billing/3.0/bis/), which is the authority on what went wrong. [Troubleshooting](troubleshooting.md) lists the ones that come up most.

## Where to start

- [Getting started](getting-started.md) builds your first invoice, with and without Laravel
- [Dutch invoices](netherlands.md) and [Belgian invoices](belgium.md) cover what each country expects
- [Validation](validation.md) explains how to check a document before it leaves your application
- [Troubleshooting](troubleshooting.md) is the place to look when a receiver rejects one

## Before you go live

This package checks the rules it implements, which is not the same as the full check a receiver runs. Put one real document through an official validator before the first invoice goes out:

- [Dutch PEPPOL validator](https://test.peppolautoriteit.nl/validate)
- [Ecosio validator](https://ecosio.com/en/peppol-and-xml-document-validator/) for Belgian documents

It costs ten minutes and it is the difference between finding a problem yourself and hearing about it from a customer whose invoice bounced.

## Support

Bugs and questions go to [GitHub issues](https://github.com/ArvidDeJong/ubl-peppol/issues). A vulnerability goes to the [security advisories](https://github.com/ArvidDeJong/ubl-peppol/security/advisories/new) instead.
