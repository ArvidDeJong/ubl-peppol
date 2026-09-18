# CLAUDE.md

Specific to `darvis/ubl-peppol`. The shared conventions are in `~/Sites/Packages/CLAUDE.md`; only what differs or what this package adds is written here.

## Overview

Builds UBL 2.1 invoices and credit notes that pass PEPPOL BIS Billing 3.0 and EN 16931 validation, for the Netherlands and Belgium. It also validates European VAT numbers against VIES and company registration numbers such as the Dutch KvK number and the Belgian ondernemingsnummer, and can hand a finished document to a PEPPOL access point provider.

## Laravel is optional here

This is the one deliberate departure from the shared layout: `laravel/framework` is **not** a runtime requirement, only a dev dependency through Testbench. The package is a plain PHP library that happens to ship a Laravel layer.

Exactly four files may import `Illuminate\...`:

- `src/UblPeppolServiceProvider.php`
- `src/PeppolService.php`
- `src/Models/PeppolLog.php`
- `src/Console/CleanupPeppolLogsCommand.php`

`tests/Unit/StandaloneCoreTest.php` fails on an import anywhere else in `src/`, and it also checks that this list only names files that exist. If a file legitimately joins the Laravel layer, add it to the list in that test and say why in the pull request. Do not solve a failure by widening the rule.

`tests/Pest.php` therefore binds the Testbench `TestCase` only to `tests/Laravel`. Everything else runs without booting an application, which is both the point and the reason the suite is fast.

## Architecture

- `UblNlBis3Service` and `UblBeBis3Service` are separate classes on purpose: a field the Dutch NLCIUS rules require is rejected under the Belgian EN 16931 profile and the other way around. Never merge them behind a country flag. A fix in one is not automatically right in the other.
- Elements are written in the order the UBL schema fixes. A document with correct values in the wrong order is rejected, and the receiver's error names the element, not the order, so this is the first thing to check when correct-looking XML fails.
- `Validation\UblValidator` holds **static** helpers for single values (unit codes, currency, tax categories, IBAN, VAT format). Validating a whole document is the builder's `validate()`, which returns an `InvoiceValidationResult`.
- `Validation\CodelistRegistry` carries the code lists; `ValidationTrackingTrait` records the corrections a builder applied so `getCorrections()` can report them.
- The `peppol_logs` table is opt-in: it arrives by publishing `--tag=ubl-peppol-migrations`, not through `loadMigrationsFrom`. Never write code that assumes the table exists.

## The specification wins

Every rule comes from the [PEPPOL BIS Billing 3.0 specification](https://docs.peppol.eu/poacc/billing/3.0/bis/). When this file, the documentation or a test disagrees with that specification, the specification is right and we are wrong. Rule codes in a rejection (`BR-CO-11`, `PEPPOL-EN16931-R010`, national `NL-` and `BE-` prefixes) point straight at the paragraph that explains why.

`validate()` covers the rules this package implements, never the receiver's full Schematron. Output is verified against an official validator ([Dutch](https://test.peppolautoriteit.nl/validate), [Ecosio](https://ecosio.com/en/peppol-and-xml-document-validator/)) before a release that changes the generated XML.

## Backwards compatibility

The usual rule that the public API stays stable within 1.x applies, and here the **generated XML is part of that API**. A document a receiver accepted before and rejects now is a breaking change, whatever the specification says about it. When a rule turns out to have been implemented wrongly, the fix is a minor release with the change spelled out in the CHANGELOG, not a patch.

## Static analysis

Larastan runs at level 8, with a `phpstan-baseline.neon` holding the errors that predate it. Nearly all of them are missing array value types in docblocks. The baseline exists so level 8 applies in full to new code; it is not a place to park a new error. When a change makes an entry disappear, regenerate it with `vendor/bin/phpstan analyse --generate-baseline` and the file shrinks. An empty baseline is the goal.

## Test data

Never put real customer data in a test, an example or an issue. The `examples/` directory keeps invented data in its own `test_data.php` per country for exactly this reason.
