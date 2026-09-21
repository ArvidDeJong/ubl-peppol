---
title: "Credit notes"
nav_order: 6
description: "Build a PEPPOL credit note with UblBeBis3Service: createCreditNoteDocument(), the billing reference BR-55 requires, positive amounts, what generateXml() throws."
---

# Credit notes

A credit note corrects an invoice you already sent. In PEPPOL it is its own document type, not an invoice with negative amounts.

Credit notes are built with `UblBeBis3Service`. `UblNlBis3Service` builds invoices only and has no credit note methods.

## How a credit note differs from an invoice

| | Invoice | Credit note |
| --- | --- | --- |
| Start with | `createDocument()` | `createCreditNoteDocument()` |
| Header | `addInvoiceHeader($number, $issueDate, $dueDate)` | `addCreditNoteHeader($number, $issueDate)`, no due date |
| Lines | `addInvoiceLine()` | `addCreditNoteLine()` |
| Reference to the invoice | Not needed | `addBillingReference()` is required (rule BR-55) |
| `addBuyerReference()` | Allowed | Throws; use `addOrderReference()` |
| Root element and type code | `<Invoice>`, 380 | `<CreditNote>`, 381 |
| Amounts | Positive | Positive as well. The document type says it is a credit |

## The complete example

```php
<?php
// credit-note.php

require __DIR__.'/vendor/autoload.php';

use Darvis\UblPeppol\UblBeBis3Service;

$creditNote = new UblBeBis3Service();

// 1. Start a credit note, not an invoice.
$creditNote->createCreditNoteDocument();

// 2. Header: credit note number and date. There is no due date.
$creditNote->addCreditNoteHeader('CN-2026-001', '2026-01-21');

// 3. The order reference goes before the billing reference.
$creditNote->addOrderReference('PO-7781');

// 4. Required: the invoice this credit note corrects, and its date.
$creditNote->addBillingReference('INV-2026-001', '2026-01-15');

// 5. The parties, exactly as on an invoice.
$creditNote->addAccountingSupplierParty(
    '0681845662', '0208', '0681845662', 'My Belgian Company BV',
    'Grote Markt 1', '1000', 'Brussel', 'BE', 'BE0681845662'
);
$creditNote->addAccountingCustomerParty(
    '0999000228', '0208', '0999000228', 'Customer Company NV',
    'Kerkstraat 123', '2000', 'Antwerpen', 'BE',
    null, '0999000228', null, null, null, 'BE0999000228'
);

// 6. The lines, with positive numbers.
$creditNote->addCreditNoteLine([
    'id' => '1',
    'quantity' => 2,
    'unit_code' => 'C62',
    'price_amount' => 100.00,
    'currency' => 'EUR',
    'name' => 'Consultancy',
    'description' => 'Refund of 2 days of consultancy',
    'tax_category_id' => 'S',
    'tax_percent' => 21.0,
    'tax_scheme_id' => 'VAT',
]);

// 7. The totals, positive as well.
$calculated = $creditNote->calculateTotals();

$creditNote->addTaxTotal($calculated['tax_totals']);
$creditNote->addLegalMonetaryTotal($calculated['totals'], 'EUR');

// 8. Check, then write the XML.
file_put_contents(__DIR__.'/credit-note.xml', $creditNote->generateXml(validateFirst: true));

echo 'Saved credit-note.xml'.PHP_EOL;
```

`php credit-note.php` prints `Saved credit-note.xml`. The file holds a `<CreditNote>` with type code 381, a `<cac:BillingReference>` to `INV-2026-001` and one `<cac:CreditNoteLine>` with a `<cbc:CreditedQuantity>` of 2.00.

The Belgian builder writes elements in the order you call them, so keep the order of this example. [Belgian invoices](belgium.md#the-order-of-the-calls-matters) explains why.

## Amounts are positive

`addCreditNoteLine()` makes the quantity, the price and the line amount positive for you: a quantity of `-2` is written as `2.00`.

`addTaxTotal()` and `addLegalMonetaryTotal()` change nothing. They write the numbers you pass. Pass positive totals, or use `calculateTotals()` as the example does.

## The keys of a credit note line

Only `id` is required. `quantity` and `price_amount` default to `0`, `unit_code` to `C62`, `currency` to `EUR`, `tax_category_id` to `S`, `tax_percent` to `21`, `tax_scheme_id` to `VAT`, `description` to an empty text and `name` to the description. `line_extension_amount` defaults to price times quantity. `accounting_cost` and `order_line_id` are optional.

## The rules `generateXml()` enforces

On a credit note, `generateXml()` always checks the rules below, with or without `validateFirst`. When one fails it throws an `InvalidArgumentException` that starts with `Credit Note Validation Failed (PEPPOL BIS Billing 3.0 / EN 16931):`.

| Code in the message | Cause |
| --- | --- |
| `[BR-55] PEPPOL Credit Note MUST have a BillingReference.` | You did not call `addBillingReference()` |
| `[BR-CN-03] LineExtensionAmount in totals is negative.` | `line_extension_amount` in `addLegalMonetaryTotal()` is below zero |
| `[BR-CN-04] PayableAmount is negative.` | `payable_amount` in `addLegalMonetaryTotal()` is below zero |

The messages `[BR-CN-01]`, `[BR-27]` and `[BR-CN-02]` exist for a negative line amount, price or quantity. `addCreditNoteLine()` already makes those positive, so you will not see them. The `BR-CN` codes are this package's own labels, not codes from the PEPPOL specification.

**`validate()` does not check these rules.** On a credit note without a billing reference, `validate()` returns valid and `generateXml()` throws. Wrap `generateXml()` in a `try` block when you build credit notes from user input.

## `addBuyerReference()` throws on a credit note

The UBL schema allows a buyer reference in a credit note, but this builder cannot put it in the right position yet. `addBuyerReference()` therefore throws an `InvalidArgumentException` that starts with `BuyerReference is not supported on credit notes by this package.`

PEPPOL requires a buyer reference or an order reference, so use `addOrderReference()` and call it before `addBillingReference()`.

## Which kind of document is this builder

```php
$creditNote->isCreditNote(); // true after createCreditNoteDocument(), false after createDocument()
```

A builder holds one document. Calling `createDocument()` after `createCreditNoteDocument()` throws `Document is already initialized`. Create a new builder per document.

## Further reading

- [PEPPOL BIS Billing 3.0: credit notes](https://docs.peppol.eu/poacc/billing/3.0/bis/#creditnote)
- [Validation](validation.md) for what `validate()` does check
