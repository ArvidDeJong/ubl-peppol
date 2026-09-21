---
title: "Home"
nav_order: 1
permalink: /
description: "darvis/ubl-peppol builds UBL 2.1 invoices and credit notes for PEPPOL BIS Billing 3.0 with Dutch and Belgian rules, in plain PHP or Laravel. Start here."
---

# UBL PEPPOL

`darvis/ubl-peppol` is a PHP library that builds UBL 2.1 e-invoices for **PEPPOL BIS Billing 3.0** (the invoice format the PEPPOL network requires, built on the European standard EN 16931). It has one builder for Dutch invoices and one for Belgian invoices and credit notes, and an optional Laravel layer that posts the XML to your access point provider.

```bash
composer require darvis/ubl-peppol
```

## Who it is for

Developers who already have invoice data (in a database, an ERP or a webshop) and need to turn it into an XML document a PEPPOL receiver accepts. You write the code that maps your data to the builder; the package writes the XML.

## What it does

- Builds Dutch invoices with `UblNlBis3Service` and Belgian invoices with `UblBeBis3Service`
- Builds credit notes with both builders
- Checks a document before you send it with `validate()`, and tells you which rule failed
- Checks a European VAT number against VIES, and the format of a company registration number (KvK, KBO, RCS, SIREN/SIRET, Handelsregister)
- In Laravel: posts the XML to your access point provider with `PeppolService`, and can log each attempt in a `peppol_logs` table

## What it does not do

- **It is not an access point.** PEPPOL is a closed network. You reach it through an *access point provider*: a company you have a contract with that delivers documents for you. Without one you can build and check invoices, but not send them.
- **It does not run the official Schematron.** `validate()` checks the rules this package implements. A receiver checks more. Put a document through an [official validator](validation.md#check-a-document-with-an-official-validator) before you go live.
- **It does not calculate your invoice.** You pass the line amounts, the VAT and the totals. The Belgian `validate()` checks that they add up; it does not change them.
- **It does not create PDFs**, and it does not receive invoices.

## Requirements

- PHP 8.2 or newer with the `dom` and `libxml` extensions
- The `bcmath` extension when you pass an IBAN to the Dutch builder or call `UblValidator::validateIban()`
- The `soap` extension when you use `ViesService`
- Laravel 11, 12 or 13, only when you use the Laravel layer

## Pages

- [Installation](installation.md): install, configure and check that it works
- [Your first invoice](getting-started.md): one complete Dutch invoice you can copy and run
- [Dutch invoices](netherlands.md): the Dutch rules, the fields of each call and the limits of the Dutch builder
- [Belgian invoices](belgium.md): a complete Belgian invoice, with a charge or discount
- [Credit notes](credit-notes.md): a complete credit note and the rules that make it throw
- [Validation](validation.md): what `validate()` checks per builder and how to read the result
- [VAT numbers](vat-numbers.md): checking a VAT number with VIES and telling "invalid" from "VIES is down"
- [Company numbers](company-numbers.md): checking the format of a registration number in five countries
- [Laravel integration](laravel.md): the container bindings, the config file, the log table and the cleanup command
- [Sending invoices](peppol-service.md): posting the XML to your provider and reading the result
- [Testing](testing.md): testing your own code without calling a provider or VIES
- [API reference](api-reference.md): every public method with its arguments
- [Troubleshooting](troubleshooting.md): error messages, quoted literally, with cause and fix
- [FAQ](faq.md): short answers to common questions

## Support

Bugs and questions go to [GitHub issues](https://github.com/ArvidDeJong/ubl-peppol/issues). A vulnerability goes to the [security advisories](https://github.com/ArvidDeJong/ubl-peppol/security/advisories/new) instead.
