---
title: "Troubleshooting"
nav_order: 14
description: "Error messages of darvis/ubl-peppol quoted literally, each with cause and fix: building a document, validate(), credit notes, sending, the log table, VIES."
---

# Troubleshooting

Each entry is a symptom, its cause and the fix. Messages are quoted as the package writes them, so you can search this page for the text you see.

## Installation

### `Class "Darvis\UblPeppol\UblNlBis3Service" not found`

**Cause:** Composer's autoloader is not loaded, or the package is not installed.
**Fix:** run `composer require darvis/ubl-peppol`. In a plain PHP script, add `require __DIR__.'/vendor/autoload.php';` at the top.

### `Class "DOMDocument" not found`

**Cause:** PHP's `dom` extension is missing.
**Fix:** install it (`php-xml` on Debian and Ubuntu) and restart PHP. `php -m | grep dom` must print `dom`.

### `Call to undefined function Darvis\UblPeppol\bcmod()`

**Cause:** the `bcmath` extension is missing. The Dutch `addPaymentMeans()` and `UblValidator::validateIban()` use it to check an IBAN. From `UblValidator` the function in the message is `Darvis\UblPeppol\Validation\bcmod()`.
**Fix:** install `bcmath` (`php-bcmath` on Debian and Ubuntu).

### `Class "SoapClient" not found`

**Cause:** the `soap` extension is missing. Only `ViesService` needs it.
**Fix:** install `soap` (`php-soap` on Debian and Ubuntu).

### `Command "peppol:cleanup" is not defined.`

**Cause:** Laravel did not load the service provider. Usually package discovery is turned off for this package in `composer.json` (`extra.laravel.dont-discover`), or the cached package list is old.
**Fix:** run `php artisan package:discover`. When discovery is off on purpose, add `Darvis\UblPeppol\UblPeppolServiceProvider::class` to `bootstrap/providers.php`.

## Building a document

### `Document is already initialized. Avoid initializing the document multiple times.`

**Cause:** `createDocument()` or `createCreditNoteDocument()` was called twice on one builder. In Laravel this happens on the second invoice when you take the Dutch builder from the container, because `app(UblNlBis3Service::class)` and `app('ubl-peppol')` always return the same instance.
**Fix:** create a builder per document with `new UblNlBis3Service()`. See [Laravel integration](laravel.md#do-not-take-the-dutch-builder-from-the-container-twice).

### `Root element is not initialized. Call createDocument() before adding elements.`

**Cause:** an `add...()` method ran before `createDocument()`.
**Fix:** call `createDocument()` first.

### `generateXml()` throws `Root element is not initialized.`

**Cause:** `generateXml()` was called on a Dutch builder without `createDocument()`. (In 1.8 and 1.9 this was the PHP error `Typed property Darvis\UblPeppol\UblNlBis3Service::$rootElement must not be accessed before initialization`.) The Belgian builder returns an empty XML declaration in the same situation.
**Fix:** call `createDocument()` and the `add...()` methods before `generateXml()`.

### `Validation error(s) in invoice header:`

The lines that follow name the problem:

| Line | Fix |
| --- | --- |
| `Invoice number is required and cannot be empty` | Pass a number |
| `Invoice number cannot exceed 35 characters` | Shorten it |
| `Invalid invoice date. Please use YYYY-MM-DD format`, `Invalid due date. Please use YYYY-MM-DD format` | Pass `2026-01-15`, not `15-01-2026` |
| `Invoice date cannot be in the future` | The invoice date must be today or earlier. Check the timezone of your server when this happens around midnight |
| `Due date must be after the invoice date` | A due date equal to the invoice date is refused as well |
| `Invoice date must be a string (YYYY-MM-DD) or DateTime object` | You passed a `DateTimeImmutable` or `CarbonImmutable`. Pass `$date->format('Y-m-d')` |

### `Validation error(s) in addAccountingSupplierParty():` or `Validation error(s) in customer information:`

**Cause:** a required argument of the Dutch builder is empty, or the country code does not have two characters. The lines that follow name each field.
**Fix:** fill the field. The arguments are positional; compare your call with the [API reference](api-reference.md#parties).

### `VAT number must start with a 2-letter ISO 3166-1 alpha-2 country code (e.g., 'NL', 'BE'). Got: '...'`

**Cause:** the customer's `vatNumber` has no country prefix (BR-CO-09).
**Fix:** pass `NL123456789B01` or `BE0999000228`. Both builders write it in upper case.

### `Invalid IBAN format` or `Invalid BIC/SWIFT code format`

**Cause:** the Dutch `addPaymentMeans()` verifies the IBAN checksum and the shape of the BIC. An IBAN with spaces fails, and so does a lower case BIC.
**Fix:** `str_replace(' ', '', $iban)` and `strtoupper($bic)`. To find out what is wrong with an IBAN, call `UblValidator::validateIban($iban)`; it returns `IBAN is too short`, `IBAN is too long`, `Invalid IBAN format` or `Invalid IBAN checksum`.

### `Payment means code must be a numeric value`

**Fix:** pass a code from UNCL 4461 as a string of digits, for example `'30'` for a bank transfer or `'58'` for a SEPA transfer.

### `Payment terms note is required and cannot be empty`

**Fix:** pass a text to the Dutch `addPaymentTerms()`, or leave the call out.

### `PEPPOL BR-27 Validation Error: Item net price (BT-146) shall NOT be negative.`

Also: `PEPPOL BR-27 Validation Error: Line extension amount shall NOT be negative.` The rest of the message is in Dutch.

**Cause:** an invoice line has a negative price or a negative line amount.
**Fix:** a discount is not a negative line. Add it with `addAllowanceCharge(false, $amount, $reason, ...)`. To credit an invoice, build a [credit note](credit-notes.md).

### `Invoice line requires line_extension_amount or both price_amount and quantity to derive it.`

**Fix:** pass `price_amount` and `quantity` in the line.

### `Warning: Undefined array key "currency"` (or `"name"`, `"description"`, `"unit_code"`)

**Cause:** a required key is missing from the array you passed to `addInvoiceLine()`.
**Fix:** add the key. The lists are under [Dutch invoices](netherlands.md#addinvoicelinearray-linedata) and [Belgian invoices](belgium.md#the-calls).

### `Tax entry #1: Tax scheme ID is required`

**Cause:** an entry in the Dutch `addTaxTotal()` misses one of its six keys. The number is the position of the entry.
**Fix:** pass `taxable_amount`, `tax_amount`, `currency`, `tax_category_id`, `tax_percent` and `tax_scheme_id`.

### `Line extension amount is required`, `Charge total amount is required`, ...

**Cause:** the Dutch `addLegalMonetaryTotal()` misses a key, or the value is not numeric.
**Fix:** pass all five: `line_extension_amount`, `tax_exclusive_amount`, `tax_inclusive_amount`, `charge_total_amount` and `payable_amount`. Use `0.00` for no charges.

### `Amount cannot be negative`, `Reason for allowance/charge is required`

**Fix:** the Dutch `addAllowanceCharge()` wants a positive amount and a reason. Whether it is a discount is decided by the first argument (`false`), not by the sign.

### The invoice contains `BUYER_REF` or `PO-001`

**Cause:** they are the defaults of `addBuyerReference()` and `addOrderReference()`.
**Fix:** always pass your own reference.

### The invoice contains `SupplierOfficialName Ltd`, `4025:123:4343`, or your VAT number with `schemeID="0106"`

**Cause:** versions before 1.10.0 wrote these fixed values.
**Fix:** upgrade to 1.10.0 or newer. The supplier's legal name is then the name you pass, the accounting cost is only written through `addAccountingCost()`, and the KvK number goes in with `addSupplierLegalRegistration()`; see [Dutch invoices](netherlands.md#the-legal-registration-of-the-supplier-and-the-customer).

### The receiver reports NL-R-003 or NL-R-005

**Cause:** the legal registration of a Dutch party (`PartyLegalEntity/CompanyID`) has a scheme other than `0106` or `0190`.
**Fix:** pass the KvK number with `addSupplierLegalRegistration('12345678')` or `addCustomerLegalRegistration('87654321')`, or an OIN with `'0190'` as the second argument.

### `Legal registration scheme must be a 4 digit ISO 6523 ICD code`

**Fix:** pass `'0106'` for a KvK number, `'0190'` for an OIN, `'0208'` for a Belgian enterprise number.

### `Add the header before the accounting cost.`

**Cause:** the Belgian `addAccountingCost()` places the element behind the currency, which the header writes.
**Fix:** call `addInvoiceHeader()` or `addCreditNoteHeader()` first.

### A `&` or `<` in a name

Nothing to fix. The builders escape text for you. Do not call `htmlspecialchars()` on your data first; that would put `&amp;amp;` in the invoice.

## Validation

### `UBL/Peppol validation failed:`

**Cause:** `generateXml(validateFirst: true)` found errors. The lines that follow are the errors of `validate()`.
**Fix:** read the rule code at the start of each line.

| Error starts with | Meaning |
| --- | --- |
| `No invoice lines found.`, `No totals found.`, `No VAT totals found.` | Belgian builder: call `addInvoiceLine()`, `addLegalMonetaryTotal()` and `addTaxTotal()` before `validate()` |
| `Line 1: LineExtensionAmount (...) does not match PriceAmount (...) × Quantity (...)` | The line total is not price times quantity |
| `BR-CO-10` | The lines do not add up to `line_extension_amount` |
| `BR-CO-13` | `tax_exclusive_amount` is not lines minus allowances plus charges |
| `BR-S-08` or `TaxSubtotal 1: TaxAmount (...) does not match calculation` | A tax entry does not match its lines or its rate |
| `BR-CO-15` | `tax_inclusive_amount` is not `tax_exclusive_amount` plus VAT |
| `BR-CO-16` | `payable_amount` is not `tax_inclusive_amount` minus `prepaid_amount` |
| `BR-CO-11`, `BR-CO-12` | The allowance or charge total is not the sum of your `addAllowanceCharge()` calls; see the next entry |
| `NL-R-003`, `NL-R-005` | A Dutch party has an endpoint scheme other than `0106` or `0190` |
| `NL-R-008` | Both parties are Dutch and the payment means code is not `30`, `48`, `49`, `57`, `58` or `59` |
| `NL-R-009` | A line has `order_line_id` and `addOrderReference()` was not called |
| `Invalid currency code format`, `Invalid schemeID format`, `Invalid payment means code format`, `Invalid tax category ID` | A code has the wrong shape |

### `BR-CO-12: Sum of document charges (10.00) does not match ChargeTotalAmount (15.00)`

Also `BR-CO-11: Sum of document allowances (...) does not match AllowanceTotalAmount (...)`.

**Cause:** `charge_total_amount` (or `allowance_total_amount`) in `addLegalMonetaryTotal()` is not the sum of the amounts you passed to `addAllowanceCharge()`.
**Fix:** make them equal. If the first number is `0.00` while you did add a charge, you are on a version before 1.10.0, where the Belgian `validate()` did not see `addAllowanceCharge()`; upgrade.

### `validate()` passes and the receiver still rejects the invoice

**Cause:** `validate()` checks the rules this package implements. The Dutch `validate()` does not check amounts at all.
**Fix:** upload the XML to an [official validator](validation.md#check-a-document-with-an-official-validator) and look up the rule code it reports in the [PEPPOL BIS Billing 3.0 rules](https://docs.peppol.eu/poacc/billing/3.0/bis/). If the package writes something the specification forbids, [open an issue](https://github.com/ArvidDeJong/ubl-peppol/issues).

### The receiver reports an element in the wrong place

**Cause:** with the Belgian builder, the calls were made in another order than the schema.
**Fix:** follow [the order of the calls](belgium.md#the-order-of-the-calls-matters). The Dutch builder sorts the elements itself.

## Credit notes

### `Credit Note Validation Failed (PEPPOL BIS Billing 3.0 / EN 16931):`

| Code in the message | Fix |
| --- | --- |
| `[BR-55] PEPPOL Credit Note MUST have a BillingReference.` | Call `addBillingReference($invoiceNumber, $invoiceDate)` |
| `[BR-CN-03] LineExtensionAmount in totals is negative.` | Pass positive totals to `addLegalMonetaryTotal()` |
| `[BR-CN-04] PayableAmount is negative.` | The same |

The Belgian `validate()` does not report these; only `generateXml()` does. See [Credit notes](credit-notes.md#the-rules-generatexml-enforces).

### `BuyerReference is not supported on credit notes by this package.`

**Cause:** the Belgian builder cannot place a buyer reference on a credit note. The Dutch builder can.
**Fix:** leave `addBuyerReference()` out on a Belgian credit note and call `addOrderReference()` before `addBillingReference()`.

### `Call to undefined method Darvis\UblPeppol\UblNlBis3Service::createCreditNoteDocument()`

**Cause:** the Dutch builder builds credit notes since 1.10.0; an older version is installed.
**Fix:** `composer update darvis/ubl-peppol`.

## Sending

### `Peppol URL is not configured (PEPPOL_URL)`

Also `Peppol username is not configured (PEPPOL_USERNAME)` and `Peppol password is not configured (PEPPOL_PASSWORD)`.

**Cause:** the value is missing from `.env`, or Laravel still uses a cached config from before you added it.
**Fix:** add the value, then run `php artisan config:clear`. Check what the service sees with `app(\Darvis\UblPeppol\PeppolService::class)->getConfig()`. If you published `config/ubl-peppol.php` long ago, compare it with the [current keys](laravel.md#the-config-file): the keys are `url`, `username`, `password` and `log_retention_days`.

### `Authentication failed - check credentials`, `Access denied - ...`, `Peppol URL not found - check PEPPOL_URL`

These come from `testConnection()`. The table under [Test the connection](peppol-service.md#test-the-connection) explains each.

### `'success' => false` with `'status_code' => 0`

**Cause:** the request did not get an answer, or something threw after the answer. `error` holds the reason.

- `cURL error 6: Could not resolve host`: the host in `PEPPOL_URL` is wrong.
- A database error that names `peppol_sent_at`: the document **was sent**, and `sendInvoice()` then failed to update your model. Do not send it again. Add the column or switch to `sendUblXml()`; see [the pitfall](peppol-service.md#sendinvoice-updates-your-model).

### `'success' => false` with a 4xx `status_code`

**Cause:** your provider refused the document. `error` holds the provider's answer.
**Fix:** read that answer. A 400 or 422 is usually a rule the document breaks; a 401 is a wrong username or password.

### `log_id` is `null`

**Cause:** there is no `peppol_logs` table. This is not an error.
**Fix:** only if you want the log: `php artisan vendor:publish --tag=ubl-peppol-migrations` and `php artisan migrate`.

### `SQLSTATE[...]: no such table: peppol_logs` (or `Table '...peppol_logs' doesn't exist`)

**Cause:** your own code queries `PeppolLog` and the migration was not run. `PeppolService` and `peppol:cleanup` check for the table; a direct `PeppolLog::...` call does not.
**Fix:** run the migration, or ask `PeppolLog::tableExists()` first.

### `peppol:cleanup` deletes nothing

**Cause:** it deletes rows whose `created_at` is older than `log_retention_days`, 60 by default.
**Fix:** pass `--days=30`, or set `PEPPOL_LOG_RETENTION_DAYS` and run `php artisan config:clear`.

## VAT numbers

### `valid` is `false` for a number you know is right

**Cause:** look at `error`. When it is not `null`, VIES did not answer the question: `Member state service unavailable`, `VIES service temporarily unavailable`, `Connection timeout`.
**Fix:** treat the number as unknown and try again later. See [Tell "invalid" from "VIES is down"](vat-numbers.md#tell-invalid-from-vies-is-down).

## Still stuck

Open an [issue](https://github.com/ArvidDeJong/ubl-peppol/issues) with the code that builds the document and invented data. Never paste real customer data. Report a vulnerability through the [security advisories](https://github.com/ArvidDeJong/ubl-peppol/security/advisories/new) instead.
