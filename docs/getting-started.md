---
title: "Your first invoice"
nav_order: 3
description: "A complete, copy-paste Dutch PEPPOL invoice with darvis/ubl-peppol: header, supplier, customer, payment, VAT, totals and one line, checked and saved as XML."
---

# Your first invoice

This page builds one complete Dutch invoice, checks it and saves it as `invoice.xml`. It assumes you followed [Installation](installation.md).

## Pick the builder by the receiver

| The receiver is in | Use | Builds |
| --- | --- | --- |
| The Netherlands | `Darvis\UblPeppol\UblNlBis3Service` | Invoices |
| Belgium | `Darvis\UblPeppol\UblBeBis3Service` | Invoices and credit notes |

The two are separate classes because their checks differ. This page uses the Dutch builder. [Belgian invoices](belgium.md) has the same example for Belgium.

## The complete example

Create `invoice.php` in the root of your project. In a Laravel application the same code can live in any class, for example `app/Actions/BuildInvoiceXml.php`; leave out the `require` line there.

```php
<?php
// invoice.php

require __DIR__.'/vendor/autoload.php';

use Darvis\UblPeppol\UblNlBis3Service;

$ubl = new UblNlBis3Service();

// 1. Start the document. Call this once per builder.
$ubl->createDocument();

// 2. Header: invoice number, invoice date, due date (YYYY-MM-DD).
$ubl->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14');

// 3. The reference your customer asked you to put on the invoice.
$ubl->addBuyerReference('CLIENT-001');

// 4. You, the supplier.
$ubl->addAccountingSupplierParty(
    '12345678',             // endpointId: where PEPPOL delivers, here your KvK number
    '0106',                 // endpointSchemeID: 0106 means "this is a KvK number"
    '12345678',             // partyId: your own identifier for this party
    'My Dutch Company BV',  // partyName
    'Damrak 1',             // street
    '1012 JS',              // postalCode
    'Amsterdam',            // city
    'NL',                   // countryCode
    'NL123456789B01'        // companyId: your VAT number
);

// Your KvK number as the legal registration (scheme 0106). A Dutch supplier needs this.
$ubl->addSupplierLegalRegistration('12345678');

// 5. Your customer.
$ubl->addAccountingCustomerParty(
    '87654321',                     // endpointId
    '0106',                         // endpointSchemeID
    '87654321',                     // partyId
    'Customer Company BV',          // partyName
    'Nieuwezijds Voorburgwal 123',  // street
    '1012 RJ',                      // postalCode
    'Amsterdam',                    // city
    'NL',                           // countryCode
    null,                           // additionalStreet
    '87654321',                     // companyId: the customer's KvK number
    null,                           // contactName
    null,                           // contactPhone
    null,                           // contactEmail
    'NL987654321B01'                // vatNumber, with the country prefix
);

// 6. How the customer pays: 30 is the code for a bank transfer.
$ubl->addPaymentMeans(
    '30',
    'Credit transfer',
    'INV-2026-001',         // paymentId: the reference the customer puts on the payment
    'NL91ABNA0417164300',   // your IBAN, without spaces
    'My Dutch Company BV',
    'ABNANL2A'              // your bank's BIC
);
$ubl->addPaymentTerms('Payment within 30 days');

// 7. The VAT, one entry per rate.
$ubl->addTaxTotal([
    [
        'taxable_amount' => 425.00,
        'tax_amount' => 89.25,
        'currency' => 'EUR',
        'tax_category_id' => 'S',   // S is the standard rate
        'tax_percent' => 21.0,
        'tax_scheme_id' => 'VAT',
    ],
]);

// 8. The totals of the invoice.
$ubl->addLegalMonetaryTotal([
    'line_extension_amount' => 425.00,  // sum of the lines, without VAT
    'tax_exclusive_amount' => 425.00,
    'tax_inclusive_amount' => 514.25,
    'charge_total_amount' => 0.00,
    'payable_amount' => 514.25,
], 'EUR');

// 9. One call per invoice line.
$ubl->addInvoiceLine([
    'id' => '1',
    'quantity' => 5,
    'unit_code' => 'HUR',           // HUR is the code for hours
    'price_amount' => 85.00,
    'currency' => 'EUR',
    'name' => 'Software development',
    'description' => 'Frontend development, 5 hours',
    'tax_category_id' => 'S',
    'tax_percent' => 21.0,
]);

// 10. Check the document, then write the XML.
$result = $ubl->validate();

if (! $result->isValid()) {
    exit($result->getErrorsAsString().PHP_EOL);
}

file_put_contents(__DIR__.'/invoice.xml', $ubl->generateXml());

echo 'Saved invoice.xml'.PHP_EOL;
```

Run it:

```bash
php invoice.php
```

It prints `Saved invoice.xml`. The file holds an `<Invoice>` document with type code 380, the currency EUR and one `<cac:InvoiceLine>`. Every `add...()` method returns the builder, so you can also chain the calls.

## What can go wrong in this example

- **A date in the future.** `addInvoiceHeader()` throws `Invoice date cannot be in the future` when the invoice date is after today. The due date must be after the invoice date.
- **An IBAN with spaces.** The Dutch builder throws `Invalid IBAN format`. Remove the spaces first.
- **A negative line.** `addInvoiceLine()` throws on a negative price or line amount. A discount is an allowance, not a negative line: use `addAllowanceCharge()`.
- **The same builder twice.** `createDocument()` throws `Document is already initialized` on the second call. Create a new builder for every invoice.

Every message is listed in [Troubleshooting](troubleshooting.md).

## Read this before you send the file to a customer

Upload `invoice.xml` to an [official validator](validation.md#check-a-document-with-an-official-validator). `validate()` checks the rules this package implements, which is less than a receiver checks.

## Check and generate in one call

`generateXml(validateFirst: true)` runs `validate()` first and throws an `InvalidArgumentException` with the message `UBL/Peppol validation failed:` followed by the errors:

```php
$xml = $ubl->generateXml(validateFirst: true);
```

[Validation](validation.md) explains what each builder checks.

## Next steps

- [Dutch invoices](netherlands.md) and [Belgian invoices](belgium.md) list the fields of every call
- [Credit notes](credit-notes.md) corrects an invoice you already sent
- [Sending invoices](peppol-service.md) posts the XML to your access point provider from Laravel
- The repository has runnable examples for both countries in `examples/`
