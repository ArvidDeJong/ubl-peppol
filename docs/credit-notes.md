---
title: "Credit notes"
nav_order: 6
description: "Build a PEPPOL credit note with the Belgian or the Dutch builder: createCreditNoteDocument(), the billing reference BR-55 requires, positive amounts, what throws."
---

# Credit notes

A credit note corrects an invoice you already sent. In PEPPOL it is its own document type, not an invoice with negative amounts.

Both builders build credit notes, with the same four methods: `createCreditNoteDocument()`, `addCreditNoteHeader()`, `addBillingReference()` and `addCreditNoteLine()`. `UblNlBis3Service` has them since 1.10.0; before that a Dutch credit note ended in `Call to undefined method`. The example below uses the Belgian builder; [the Dutch builder](#a-credit-note-with-the-dutch-builder) has its own example, because a few details differ.

## How a credit note differs from an invoice

| | Invoice | Credit note |
| --- | --- | --- |
| Start with | `createDocument()` | `createCreditNoteDocument()` |
| Header | `addInvoiceHeader($number, $issueDate, $dueDate)` | `addCreditNoteHeader($number, $issueDate)`, no due date |
| Lines | `addInvoiceLine()` | `addCreditNoteLine()` |
| Reference to the invoice | Not needed | `addBillingReference()` is required (rule BR-55) |
| `addBuyerReference()` | Allowed | Belgian builder: throws, use `addOrderReference()`. Dutch builder: allowed |
| Root element and type code | `<Invoice>`, 380 | `<CreditNote>`, 381 |
| Amounts | Positive | Positive as well. The document type says it is a credit |

## The complete example, with the Belgian builder

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

## A credit note with the Dutch builder

```php
<?php
// credit-note-nl.php

require __DIR__.'/vendor/autoload.php';

use Darvis\UblPeppol\UblNlBis3Service;

$creditNote = new UblNlBis3Service();

// 1. Start a credit note, not an invoice.
$creditNote->createCreditNoteDocument();

// 2. Header: credit note number and date. There is no due date.
$creditNote->addCreditNoteHeader('CN-2026-001', '2026-01-21');

// 3. Required: the invoice this credit note corrects, and its date.
$creditNote->addBillingReference('INV-2026-001', '2026-01-15');

// 4. PEPPOL wants a buyer reference or an order reference. The Dutch builder takes both.
$creditNote->addBuyerReference('CLIENT-001');

// 5. The parties and the payment, exactly as on an invoice.
$creditNote->addAccountingSupplierParty(
    '12345678', '0106', '12345678', 'My Dutch Company BV',
    'Damrak 1', '1012 JS', 'Amsterdam', 'NL', 'NL123456789B01'
);
$creditNote->addSupplierLegalRegistration('12345678');
$creditNote->addAccountingCustomerParty(
    '87654321', '0106', '87654321', 'Customer Company BV',
    'Nieuwezijds Voorburgwal 123', '1012 RJ', 'Amsterdam', 'NL',
    null, '87654321', null, null, null, 'NL987654321B01'
);
$creditNote->addPaymentMeans('30', 'Credit transfer', 'CN-2026-001', 'NL91ABNA0417164300');

// 6. The totals: positive numbers, you add them up yourself.
$creditNote->addTaxTotal([[
    'taxable_amount' => 170.00, 'tax_amount' => 35.70, 'currency' => 'EUR',
    'tax_category_id' => 'S', 'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
]]);
$creditNote->addLegalMonetaryTotal([
    'line_extension_amount' => 170.00, 'tax_exclusive_amount' => 170.00,
    'tax_inclusive_amount' => 205.70, 'charge_total_amount' => 0.00, 'payable_amount' => 205.70,
], 'EUR');

// 7. The lines. A quantity of -2 is written as 2.00.
$creditNote->addCreditNoteLine([
    'id' => '1', 'quantity' => 2, 'unit_code' => 'HUR', 'price_amount' => 85.00, 'currency' => 'EUR',
    'name' => 'Software development', 'description' => 'Two hours invoiced too many',
    'tax_category_id' => 'S', 'tax_percent' => 21.0,
]);

file_put_contents(__DIR__.'/credit-note-nl.xml', $creditNote->generateXml(validateFirst: true));

echo 'Saved credit-note-nl.xml'.PHP_EOL;
```

`php credit-note-nl.php` prints `Saved credit-note-nl.xml`.

What differs from the Belgian builder:

- **The order of the calls does not matter.** `<CreditNote>` has its own element order in the UBL schema, which is not the order of `<Invoice>`, and `generateXml()` sorts the elements into it.
- `addBuyerReference()` is allowed.
- There is no `calculateTotals()`: you pass the totals yourself, as on a Dutch invoice.
- `addCreditNoteHeader()` refuses an issue date in the future, as the Dutch `addInvoiceHeader()` does.
- `addBillingReference()` throws an `InvalidArgumentException` on an empty invoice number or a date that is not `YYYY-MM-DD`.
- Mixing the two document types throws a `RuntimeException`: `addInvoiceHeader()` or `addInvoiceLine()` on a credit note, `addCreditNoteHeader()` or `addCreditNoteLine()` on an invoice.
- Without a billing reference `generateXml()` throws an `InvalidArgumentException` that starts with `[BR-55] [NL-R-001]`, and `validate()` reports the same message as an error. NL-R-001 is the Dutch rule that repeats BR-55 for a supplier in the Netherlands.
- A line needs `id`, `quantity` and `price_amount`; without the last two `addCreditNoteLine()` throws `Credit note line requires price_amount and quantity.` The other keys default as described below, and `base_quantity` (default 1) is written as well.

## Amounts are positive

`addCreditNoteLine()` makes the quantity, the price and the line amount positive for you: a quantity of `-2` is written as `2.00`.

`addTaxTotal()` and `addLegalMonetaryTotal()` change nothing. They write the numbers you pass. Pass positive totals, or use `calculateTotals()` as the example does.

## The keys of a credit note line

For the Belgian builder: only `id` is required. `quantity` and `price_amount` default to `0`, `unit_code` to `C62`, `currency` to `EUR`, `tax_category_id` to `S`, `tax_percent` to `21`, `tax_scheme_id` to `VAT`, `description` to an empty text and `name` to the description. `line_extension_amount` defaults to price times quantity. `accounting_cost` and `order_line_id` are optional.

## The rules `generateXml()` enforces

This section describes the Belgian builder; the Dutch builder only checks the billing reference, see above. On a credit note, `generateXml()` always checks the rules below, with or without `validateFirst`. When one fails it throws an `InvalidArgumentException` that starts with `Credit Note Validation Failed (PEPPOL BIS Billing 3.0 / EN 16931):`.

| Code in the message | Cause |
| --- | --- |
| `[BR-55] PEPPOL Credit Note MUST have a BillingReference.` | You did not call `addBillingReference()` |
| `[BR-CN-03] LineExtensionAmount in totals is negative.` | `line_extension_amount` in `addLegalMonetaryTotal()` is below zero |
| `[BR-CN-04] PayableAmount is negative.` | `payable_amount` in `addLegalMonetaryTotal()` is below zero |

The messages `[BR-CN-01]`, `[BR-27]` and `[BR-CN-02]` exist for a negative line amount, price or quantity. `addCreditNoteLine()` already makes those positive, so you will not see them. The `BR-CN` codes are this package's own labels, not codes from the PEPPOL specification.

**The Belgian `validate()` does not check these rules.** On a credit note without a billing reference, it returns valid and `generateXml()` throws. Wrap `generateXml()` in a `try` block when you build credit notes from user input.

## `addBuyerReference()` throws on a Belgian credit note

The UBL schema allows a buyer reference in a credit note, but the Belgian builder cannot put it in the right position yet. `addBuyerReference()` therefore throws an `InvalidArgumentException` that starts with `BuyerReference is not supported on credit notes by this package.`

PEPPOL requires a buyer reference or an order reference, so use `addOrderReference()` and call it before `addBillingReference()`.

`addAccountingCost()` (BT-19) does work on a credit note. Call it after the header.

## Which kind of document is this builder

```php
$creditNote->isCreditNote(); // true after createCreditNoteDocument(), false after createDocument()
```

A builder holds one document. Calling `createDocument()` after `createCreditNoteDocument()` throws `Document is already initialized`. Create a new builder per document.

## Further reading

- [PEPPOL BIS Billing 3.0: credit notes](https://docs.peppol.eu/poacc/billing/3.0/bis/#creditnote)
- [Validation](validation.md) for what `validate()` does check
