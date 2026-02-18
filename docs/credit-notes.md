# Credit Notes - PEPPOL BIS Billing 3.0 / EN 16931

This document explains how to generate PEPPOL-compliant Credit Notes using the `darvis/ubl-peppol` package.

## Key Differences: Invoice vs Credit Note

| Aspect           | Invoice            | Credit Note                  |
| ---------------- | ------------------ | ---------------------------- |
| Root element     | `<Invoice>`        | `<CreditNote>`               |
| Type code        | `380`              | `381`                        |
| Quantity element | `InvoicedQuantity` | `CreditedQuantity`           |
| BillingReference | Optional           | **REQUIRED (BR-55)**         |
| Amounts          | Positive           | **Positive** (not negative!) |

## Quick Start

```php
use Darvis\UblPeppol\UblBeBis3Service;

$service = new UblBeBis3Service();

// 1. Create Credit Note document (not createDocument!)
$service->createCreditNoteDocument();

// 2. Add Credit Note header (no due date needed)
$service->addCreditNoteHeader('C2026-001', '2026-01-21');

// 3. REQUIRED: Add reference to original invoice (BR-55)
$service->addBillingReference('F2026-050', '2026-01-15');

// 4. Add other elements (same as invoice)
$service->addAccountingSupplierParty(...);
$service->addAccountingCustomerParty(...);

// 5. Add credit note lines (amounts will be auto-converted to positive)
$service->addCreditNoteLine([
    'id' => '1',
    'quantity' => '5',           // Use positive numbers
    'unit_code' => 'C62',
    'description' => 'Refund for Product X',
    'name' => 'Product X',
    'price_amount' => '100.00',  // Positive price
    'currency' => 'EUR',
    'tax_category_id' => 'S',
    'tax_percent' => 21,
    'tax_scheme_id' => 'VAT',
]);

// 6. Add totals (positive amounts)
$service->addTaxTotal([...]);
$service->addLegalMonetaryTotal([...], 'EUR');

// 7. Generate XML (validates automatically)
$xml = $service->generateXml();
```

## Business Rules

### BR-55: BillingReference is REQUIRED

Every Credit Note MUST reference the original invoice being credited.

```php
// This will throw an exception:
$service->createCreditNoteDocument();
$service->addCreditNoteHeader('C2026-001', '2026-01-21');
// Missing: $service->addBillingReference(...)
$xml = $service->generateXml(); // ❌ InvalidArgumentException
```

**Error message:**

```
[BR-55] PEPPOL Credit Note MUST have a BillingReference.
A Credit Note SHALL have a preceding invoice reference (BG-3).
Solution: Call addBillingReference($originalInvoiceNumber, $originalIssueDate) before generateXml().
```

### BR-27: Positive Amounts Only

In PEPPOL, the credit nature is indicated by the document type code (381), NOT by negative amounts.

```php
// ✅ Correct: Use positive amounts
$service->addCreditNoteLine([
    'quantity' => '5',
    'price_amount' => '100.00',
    // ...
]);

// ⚠️ Auto-corrected: Negative amounts are converted to positive
$service->addCreditNoteLine([
    'quantity' => '-5',      // Will become 5
    'price_amount' => '-100', // Will become 100
    // ...
]);
```

## Validation

## BuyerReference in Credit Notes

The CreditNote schema does not allow `BuyerReference` in the same position as Invoice documents.
Avoid calling `addBuyerReference()` for credit notes.

The package automatically validates Credit Note specific rules when calling `generateXml()`:

| Rule     | Description                             |
| -------- | --------------------------------------- |
| BR-55    | BillingReference must be present        |
| BR-27    | Price amounts must not be negative      |
| BR-CN-01 | Line extension amounts must be positive |
| BR-CN-02 | Credited quantities must be positive    |
| BR-CN-03 | Total line extension must be positive   |
| BR-CN-04 | Payable amount must be positive         |

## Checking Document Type

```php
$service->createCreditNoteDocument();
echo $service->isCreditNote(); // true

$service->createDocument();
echo $service->isCreditNote(); // false
```

## XML Structure Example

```xml
<?xml version="1.0" encoding="UTF-8"?>
<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"
            xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
            xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">

    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
    <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
    <cbc:ID>C2026-001</cbc:ID>
    <cbc:IssueDate>2026-01-21</cbc:IssueDate>
    <cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>

    <!-- REQUIRED for Credit Notes (BR-55) -->
    <cac:BillingReference>
        <cac:InvoiceDocumentReference>
            <cbc:ID>F2026-050</cbc:ID>
            <cbc:IssueDate>2026-01-15</cbc:IssueDate>
        </cac:InvoiceDocumentReference>
    </cac:BillingReference>

    <cbc:BuyerReference>CUST-123</cbc:BuyerReference>

    <!-- ... supplier, customer, totals ... -->

    <cac:CreditNoteLine>
        <cbc:ID>1</cbc:ID>
        <cbc:CreditedQuantity unitCode="C62">5.00</cbc:CreditedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">500.00</cbc:LineExtensionAmount>
        <!-- ... item details ... -->
    </cac:CreditNoteLine>
</CreditNote>
```

## References

- [PEPPOL BIS Billing 3.0 - Credit Note](https://docs.peppol.eu/poacc/billing/3.0/bis/#creditnote)
- [EN 16931 Business Rules](https://docs.peppol.eu/poacc/billing/3.0/rules/)
- [Belgian PEPPOL Validator](https://ecosio.com/en/peppol-and-xml-document-validator/)
