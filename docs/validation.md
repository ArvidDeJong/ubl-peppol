---
title: "Validation"
nav_order: 7
description: "What validate() checks in the Dutch and the Belgian builder, how to read InvoiceValidationResult, why corrections are only suggestions, and strict code lists."
---

# Validation

`validate()` checks a document before you send it, so you read a sentence instead of a rejection from the receiver. It checks the rules this package implements. That is less than a receiver checks, so also [use an official validator](#check-a-document-with-an-official-validator).

## Check a document in your code

```php
// anywhere after the add...() calls
$result = $ubl->validate();   // Darvis\UblPeppol\Validation\InvoiceValidationResult

if (! $result->isValid()) {
    throw new RuntimeException($result->getErrorsAsString());
}

$xml = $ubl->generateXml();
```

Or in one call:

```php
$xml = $ubl->generateXml(validateFirst: true);
```

With `validateFirst: true`, a document with errors throws an `InvalidArgumentException`. The message starts with `UBL/Peppol validation failed:` and lists the errors. The Belgian builder adds a block `Suggested corrections:` with JSON; the Dutch builder does not.

`validate()` never throws and never changes the document.

## The two builders check different things

| | `UblNlBis3Service` | `UblBeBis3Service` |
| --- | --- | --- |
| An empty document | Valid | Invalid: `No invoice lines found. Add lines first using addInvoiceLine().` |
| No totals | Not checked | Invalid: `No totals found. Add totals first using addLegalMonetaryTotal().` |
| No tax total | Not checked | Invalid: `No VAT totals found. Add VAT first using addTaxTotal().` |
| Code formats (currency, scheme ID, payment means, VAT category, unit code) | Yes | Yes |
| The amounts add up (BR-CO-10, BR-CO-11, BR-CO-12, BR-CO-13, BR-CO-15, BR-CO-16, BR-S-08), document level allowances and charges included | **No** | Yes |
| VAT categories: the breakdown has every category of the lines, the rates fit, no VAT on a category without VAT, the VAT numbers and delivery a category needs, the exemption reason. See [VAT categories](vat-categories.md#what-validate-checks) | Yes | Yes |
| Dutch rules NL-R-002 to NL-R-005 (addresses and legal registrations), NL-R-007, NL-R-008, NL-R-009 | Yes | No |
| Credit note rules (BR-55, positive totals) | The billing reference (BR-55, NL-R-001); not the totals | **No**: only `generateXml()` checks them, see [Credit notes](credit-notes.md#the-rules-generatexml-enforces) |
| Suggested corrections | Never | When the amounts do not add up |

So a Dutch invoice with wrong totals passes `validate()`. Check the arithmetic in your own code, and with an official validator.

The exact rules are listed under [Dutch invoices](netherlands.md#what-validate-checks) and [Belgian invoices](belgium.md#what-validate-checks).

## Read the result

`InvoiceValidationResult` has public read-only properties and a few helpers:

| Member | Returns |
| --- | --- |
| `isValid()` or `$result->isValid` | `true` when there are no errors. Warnings do not count |
| `hasErrors()`, `$result->errors`, `getErrorsAsString(string $separator = "\n")` | The errors, each a sentence that starts with the rule code when there is one |
| `hasWarnings()`, `$result->warnings`, `getWarningsAsString(string $separator = "\n")` | The warnings |
| `getCorrections()`, `getCorrection(string $key)`, `$result->corrections` | The suggested totals, or an empty array |
| `toArray()` | `is_valid`, `errors`, `warnings`, `corrections` |

Two checks produce a **warning**, not an error: a unit code the package does not know (`Unknown unit code: 'XYZ'. Ensure it exists in UN/ECE Rec 20/21.`) and a Dutch supplier without payment means (`NL-R-007`). Log warnings; they do not stop `generateXml(validateFirst: true)`.

## Corrections are suggestions

`getCorrections()` does not list things the builder fixed. Nothing is fixed. It returns the totals the Belgian validator calculated from your lines, and only when the amounts do not add up:

```php
$result = $ubl->validate();

$result->getCorrection('tax_inclusive_amount'); // 242.0, or null when there is nothing to suggest
```

The keys are `line_extension_amount`, `allowance_total_amount`, `charge_total_amount`, `tax_exclusive_amount`, `total_tax_amount`, `tax_inclusive_amount`, `payable_amount` and `tax_subtotals`. To use them you build a new document with those numbers. The Dutch builder always returns an empty array.

## Checks that run while you build

The `add...()` methods check their own input and throw an `InvalidArgumentException` at once. Examples: an empty invoice number, a date that is not `YYYY-MM-DD`, a negative line, an IBAN with a wrong checksum in the Dutch builder. The Dutch builder checks far more input than the Belgian one. [Troubleshooting](troubleshooting.md) quotes every message.

## Check single values with `UblValidator`

`Darvis\UblPeppol\Validation\UblValidator` has static helpers for one value at a time. It does not validate a document.

```php
use Darvis\UblPeppol\Validation\UblValidator;

UblValidator::validateVatNumber('NL123456789B01'); // null: fine
UblValidator::validateVatNumber('123456789');      // "VAT number must start with a 2-letter country code ..."
UblValidator::validateIban('NL91ABNA0417164300');  // null: fine
UblValidator::validateIban('NL12ABNA0123456789');  // "Invalid IBAN checksum"
UblValidator::isValidUnitCode('HUR');              // true
UblValidator::isValidTaxCategory('S');             // true
```

| Method | Checks |
| --- | --- |
| `validateVatNumber(?string)`: `?string` | Two-letter prefix of an EU member state (or `XI`, `GB`), then letters and digits only. Returns the error, or `null`. It does not check the national format and does not ask VIES. An empty value is accepted |
| `isValidVatNumber(string)`: `bool` | The same check as a boolean |
| `validateIban(?string)`: `?string` | Length, format and the mod 97 checksum. Needs the `bcmath` extension. An empty value is accepted |
| `isValidUnitCode(string)`: `bool` | The code is in the package's UN/ECE Recommendation 20 list |
| `isValidTaxCategory(string)`: `bool` | One of `S`, `Z`, `E`, `AE`, `K`, `G`, `O` |
| `isValidCurrencyCodeFormat(string)`: `bool` | Three letters. It does not check that the currency exists |
| `isValidSchemeIdFormat(string)`: `bool` | Four digits |
| `isValidPaymentMeansCodeFormat(string)`: `bool` | One to three digits |
| `isValidClassificationScheme(string)`: `bool` | A UNTDID 7143 item classification scheme, or `CPV` |
| `validateInvoiceData(array)`: `array` | A quick check of your own data before you build. Reads the keys `invoice_number`, `issue_date`, `due_date`, `supplier_name`, `customer_name` (all required), `supplier_vat_number`, `customer_vat_number` and `iban`. Returns a list of messages |

## Strict code lists

By default the builders check the *format* of a code: `XXX` passes as a currency. With strict code lists they check the code against lists you supply.

1. Create a JSON file with the lists. The package ships no lists; download them from the [PEPPOL code lists](https://docs.peppol.eu/poacc/billing/3.0/codelist/) and keep them up to date yourself.

   ```json
   {
     "iso4217": ["EUR", "USD"],
     "eas": ["0088", "0106", "0190", "0208"],
     "icd": ["0088", "0106", "0190", "0208"],
     "uncl4461": ["30", "48", "49", "57", "58", "59"],
     "uncl5305": ["S", "Z", "E", "AE", "K", "G", "O"]
   }
   ```

2. Turn it on before you call `validate()`:

   ```php
   $ubl->enableStrictCodelistValidation('/path/to/codelists.json');
   ```

   Or pass a registry you built yourself:

   ```php
   use Darvis\UblPeppol\Validation\CodelistRegistry;

   $ubl->enableStrictCodelistValidation(registry: new CodelistRegistry([
       'iso4217' => ['EUR'],
   ]));
   ```

| List | Checked against |
| --- | --- |
| `iso4217` | Every currency code in the document |
| `eas` | The endpoint scheme IDs |
| `icd` | The scheme IDs of party identifiers and registration numbers |
| `uncl4461` | The payment means codes |
| `uncl5305` | The VAT category IDs |

A code that is not in its list gives `Invalid EAS code: '9999'.` A list that is missing or empty while the document uses such a code gives `Strict codelist validation requires list ISO4217 to be loaded.` Turning strict mode on without a file or a registry gives `Strict codelist validation is enabled but no codelist registry is configured.` A missing file throws `Codelist file not found: ...`.

## Check a document with an official validator

The quickest way: the [PEPPOL validator](validator.md) on this site runs the official rules in your browser, without uploading the file.

Do this before the first real invoice, and again after you change how you build documents.

1. Save the XML to a file.
2. Upload it to the [Dutch PEPPOL validator](https://test.peppolautoriteit.nl/validate) or the [Ecosio validator](https://ecosio.com/en/peppol-and-xml-document-validator/).
3. Look up every rule code it reports in the [PEPPOL BIS Billing 3.0 rules](https://docs.peppol.eu/poacc/billing/3.0/bis/). `BR-` rules come from EN 16931, `PEPPOL-EN16931-` rules from PEPPOL, and `NL-R-` rules from the Dutch additions.

That specification is the authority. When this documentation and the specification disagree, the specification is right.
