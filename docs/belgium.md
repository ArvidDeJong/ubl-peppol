---
title: "Belgian invoices"
nav_order: 5
description: "Build a Belgian PEPPOL invoice with UblBeBis3Service: a complete example, scheme ID 0208, the order of the calls, calculateTotals() and what validate() checks."
---

# Belgian invoices

`Darvis\UblPeppol\UblBeBis3Service` builds an invoice or a [credit note](credit-notes.md) for a Belgian receiver. It writes a PEPPOL BIS Billing 3.0 document, and its `validate()` checks that the amounts add up.

## The order of the calls matters

The UBL schema fixes the order of the elements inside `<Invoice>`, and a receiver rejects a document that has them in another order. The Belgian builder writes most elements in the order you call them. Only the tax total and the monetary total are moved in front of the lines.

Call the methods in this order:

1. `createDocument()`
2. `addInvoiceHeader()`
3. `addBuyerReference()`, then `addOrderReference()`, then `addAdditionalDocumentReference()`
4. `addAccountingSupplierParty()`, then `addAccountingCustomerParty()`
5. `addDelivery()`, then `addPaymentMeans()`, then `addPaymentTerms()`, then `addAllowanceCharge()`
6. `addInvoiceLine()` per line, `addTaxTotal()` and `addLegalMonetaryTotal()`, these three in any order
7. `generateXml()`

Steps 3 and 5 are optional, but PEPPOL requires a buyer reference or an order reference (rule PEPPOL-EN16931-R003).

## The complete example

```php
<?php
// invoice-be.php

require __DIR__.'/vendor/autoload.php';

use Darvis\UblPeppol\UblBeBis3Service;

$ubl = new UblBeBis3Service();

$ubl->createDocument();
$ubl->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14');
$ubl->addBuyerReference('CLIENT-001');

// You, the supplier.
$ubl->addAccountingSupplierParty(
    '0681845662',               // endpointId: your enterprise number, 10 digits
    '0208',                     // endpointSchemeID: 0208 means "Belgian enterprise number"
    '0681845662',               // partyId
    'My Belgian Company BV',    // name
    'Grote Markt 1',            // street
    '1000',                     // postalCode
    'Brussel',                  // city
    'BE',                       // country
    'BE0681845662'              // vatNumber, with the BE prefix
);

// Your customer.
$ubl->addAccountingCustomerParty(
    '0999000228',               // endpointId
    '0208',                     // endpointSchemeID
    '0999000228',               // partyId
    'Customer Company NV',      // name
    'Kerkstraat 123',           // street
    '2000',                     // postalCode
    'Antwerpen',                // city
    'BE',                       // country
    null,                       // additionalStreet
    '0999000228',               // registrationNumber: the customer's enterprise number
    null,                       // contactName
    null,                       // contactPhone
    null,                       // contactEmail
    'BE0999000228'              // vatNumber, upper case, with the country prefix
);

$ubl->addPaymentMeans(
    '30',                       // 30 is the code for a bank transfer
    'Credit transfer',
    'INV-2026-001',             // paymentId: the reference for the payment
    'BE68539007547034',         // your IBAN
    'My Belgian Company BV',    // account name
    'BBRUBEBB'                  // BIC
);
$ubl->addPaymentTerms('Payment within 30 days');

// The lines. tax_scheme_id is required in the Belgian builder.
$ubl->addInvoiceLine([
    'id' => '1',
    'quantity' => 2,
    'unit_code' => 'C62',       // C62 is the code for "piece"
    'price_amount' => 100.00,
    'currency' => 'EUR',
    'name' => 'Consultancy',
    'description' => 'IT consultancy, 2 days',
    'tax_category_id' => 'S',
    'tax_percent' => 21.0,
    'tax_scheme_id' => 'VAT',
]);

// Let the builder add up the lines, then pass the result back in.
$calculated = $ubl->calculateTotals();

$ubl->addTaxTotal($calculated['tax_totals']);
$ubl->addLegalMonetaryTotal($calculated['totals'], 'EUR');

// Check, then write the XML. This throws when the document does not hold up.
file_put_contents(__DIR__.'/invoice-be.xml', $ubl->generateXml(validateFirst: true));

echo 'Saved invoice-be.xml'.PHP_EOL;
```

`php invoice-be.php` prints `Saved invoice-be.xml`. The file holds an `<Invoice>` with a tax total of 42.00 and a payable amount of 242.00.

## Scheme ID 0208: the enterprise number

`0208` is the scheme for the Belgian enterprise number (KBO in Dutch, BCE in French). It has ten digits. They are the digits of the Belgian VAT number, so for VAT number `BE0681845662` the endpoint is `0681845662`. Pass the endpoint **without** `BE` and the `vatNumber` argument **with** `BE`.

`CompanyRegistrationService` checks the checksum of an enterprise number; see [Company numbers](company-numbers.md).

## The calls

The header, the references and the line keys work as in the [Dutch builder](netherlands.md#the-calls). These are the differences.

| Call | Difference from the Dutch builder |
| --- | --- |
| `addAccountingSupplierParty()`, `addAccountingCustomerParty()` | No check on empty arguments. The supplier's legal name is the `$name` you pass. A customer `$registrationNumber` is written with scheme `0106` when the country is `NL`, otherwise `0208` |
| Customer `$vatNumber` | Must start with two **upper case** letters, otherwise the builder throws `VAT number must start with a 2-letter ISO 3166-1 alpha-2 country code` |
| `addAdditionalDocumentReference($id, $documentType)` | `$documentType` is written as `cbc:DocumentDescription` |
| `addPaymentMeans()` | The first six arguments are required. The IBAN is not checked. The payment means name and the account name are written to the XML |
| `addPaymentTerms()` | Accepts four arguments; only `$note` is written |
| `addAllowanceCharge()` | All six arguments are required. The VAT category is always written |
| `addDelivery()` | The first eight arguments are required |
| `addTaxTotal()` | No check on missing keys. A second call replaces the first |
| `addLegalMonetaryTotal(array $totals, string $currency)` | `$currency` is required. The optional keys `allowance_total_amount` and `prepaid_amount` are written when they are greater than zero |
| `addInvoiceLine()` | `tax_scheme_id` is required. `base_quantity` is always written as `1` |

`cbc:DocumentCurrencyCode` is always `EUR`, and `addInvoiceHeader()` writes `cbc:AccountingCost` with the fixed value `4025:123:4343` into every invoice. You cannot change either through the public methods yet.

## Let the builder add up the lines

`calculateTotals()` adds up the lines you added so far and returns three keys:

| Key | Holds |
| --- | --- |
| `totals` | `line_extension_amount`, `tax_exclusive_amount`, `tax_inclusive_amount`, `charge_total_amount`, `allowance_total_amount`, `payable_amount`: ready for `addLegalMonetaryTotal()` |
| `tax_totals` | One entry per VAT category and rate: ready for `addTaxTotal()` |
| `total_tax_amount` | The VAT of all categories together |

It groups lines by `tax_category_id` and `tax_percent`, and rounds the VAT per group to two decimals. It knows nothing about `addAllowanceCharge()`: with a charge or a discount, calculate the totals yourself.

`getInvoiceLines()`, `getTotals()` and `getTaxTotals()` return what you passed in so far.

## What `validate()` checks

The Belgian `validate()` refuses a document without lines, without a monetary total or without a tax total. Then it checks the arithmetic:

| Rule | Check |
| --- | --- |
| Per line | `line_extension_amount` equals `price_amount` times `quantity` |
| BR-CO-10 | The lines add up to `line_extension_amount` |
| BR-CO-13 | `tax_exclusive_amount` equals lines minus allowances plus charges |
| BR-S-08 | Each taxable amount equals the lines of that VAT category, and each tax amount equals taxable amount times rate |
| BR-CO-15 | `tax_inclusive_amount` equals `tax_exclusive_amount` plus VAT |
| BR-CO-16 | `payable_amount` equals `tax_inclusive_amount` minus `prepaid_amount` |

A difference of at most 0.01 is accepted. It also checks the code formats, like the Dutch builder does. It does not check the Dutch `NL-R` rules.

When something is wrong, `getCorrections()` returns the totals the validator calculated itself. They are **suggestions**: nothing in your document is changed. See [Validation](validation.md#corrections-are-suggestions).

### A charge or a discount fails `validate()`

`validate()` compares `charge_total_amount` and `allowance_total_amount` with a sum of zero, because the builder does not pass your `addAllowanceCharge()` calls to the validator. A correct document with a charge of 10.00 therefore reports:

```text
BR-CO-12: Sum of document charges (0.00) does not match ChargeTotalAmount (10.00)
```

and a BR-S-08 error for the taxable amount. A discount reports `BR-CO-11` in the same way. The suggested corrections are wrong in this case, because they leave the charge out.

Until this is fixed: for a document with `addAllowanceCharge()`, call `generateXml()` without `validateFirst`, and check the XML with an [official validator](validation.md#check-a-document-with-an-official-validator).

## A charge or a discount

```php
// A freight charge of 10.00 with 21% VAT. Call this before the lines and the totals.
$ubl->addAllowanceCharge(true, 10.00, 'Freight', 'S', 21.0, 'EUR');

$ubl->addTaxTotal([
    [
        'taxable_amount' => 210.00,     // lines 200.00 + charge 10.00
        'tax_amount' => 44.10,
        'currency' => 'EUR',
        'tax_category_id' => 'S',
        'tax_percent' => 21.0,
        'tax_scheme_id' => 'VAT',
    ],
]);

$ubl->addLegalMonetaryTotal([
    'line_extension_amount' => 200.00,  // the lines only
    'tax_exclusive_amount' => 210.00,   // lines - allowances + charges
    'tax_inclusive_amount' => 254.10,
    'charge_total_amount' => 10.00,
    'payable_amount' => 254.10,
], 'EUR');
```

Pass `false` as the first argument for a discount, and put its total in `allowance_total_amount`.

## Belgian VAT categories

| Situation | `tax_category_id` | `tax_percent` |
| --- | --- | --- |
| Standard rate | `S` | `21.0` |
| Reduced rates | `S` | `12.0` or `6.0` |
| Zero rate | `Z` | `0.0` |
| Exempt | `E` | `0.0` |
| Reverse charge | `AE` | `0.0` |

The builder writes no name for a VAT category. A `tax_category_name` key in a tax entry is ignored.

## Check the result

Upload the XML to the [Ecosio validator](https://ecosio.com/en/peppol-and-xml-document-validator/) before you send a first invoice.
