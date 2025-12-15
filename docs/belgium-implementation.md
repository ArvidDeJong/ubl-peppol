# Belgium Implementation (EN 16931)

This guide covers the specific requirements for generating UBL invoices that comply with the Belgian EN 16931 standard.

## Belgian Specifications

### PEPPOL BIS Billing 3.0 + EN 16931
Belgium uses the standard PEPPOL BIS Billing 3.0 with additional national requirements according to EN 16931.

### Important Belgian Rules

#### ubl-BE-01: Second AdditionalDocumentReference
```php
// Required: PEPPOL reference
$ubl->addAdditionalDocumentReference('PEPPOL', 'PEPPOLInvoice');
```

#### ubl-BE-10: Tax Category Names
Use correct BTCC values:
```php
// Standard rate (21%)
'tax_category_name' => 'Taux standard'

// Zero rate (0%)
'tax_category_name' => 'Taux zéro'
```

#### ubl-BE-14: TaxTotal in InvoiceLine
For Belgian compliance, TaxTotal is omitted at InvoiceLine level.

## Complete Belgian Invoice

```php
use Darvis\UblPeppol\UblBeBis3Service;

$ubl = new UblBeBis3Service();
$ubl->createDocument();

// 1. Invoice header
$ubl->addInvoiceHeader('BE-INV-2024-001', '2024-01-15', '2024-02-14');

// 2. Required references
$ubl->addBuyerReference('CLIENT-001');
$ubl->addOrderReference('ORDER-2024-001');

// 3. PEPPOL reference (ubl-BE-01)
$ubl->addAdditionalDocumentReference('PEPPOL', 'PEPPOLInvoice');

// 4. Supplier (Belgian company)
$ubl->addAccountingSupplierParty(
    'BE0123456789',           // VAT number as endpoint
    '0208',                   // Belgian VAT scheme
    'BE0123456789',           // Party ID
    'My Belgian Company BV',
    'Grote Markt 1',
    '1000',
    'Brussel',
    'BE',
    'BE0123456789'            // VAT number
);

// 5. Customer
$ubl->addAccountingCustomerParty(
    'BE0987654321',
    '0208',
    'BE0987654321',
    'Customer Company NV',
    'Kerkstraat 123',
    '2000',
    'Antwerpen',
    'BE'
);

// 6. Invoice lines
$ubl->addInvoiceLine([
    'id' => '1',
    'quantity' => 2,
    'unit_code' => 'C62',
    'price_amount' => 100.00,
    'currency' => 'EUR',
    'name' => 'Consultancy services',
    'description' => 'IT consultancy - 2 days',
    'tax_category_id' => 'S',
    'tax_percent' => 21.0,
    'tax_scheme_id' => 'VAT'
]);

// 7. Taxes (ubl-BE-10 compliance)
$ubl->addTaxTotal([
    [
        'taxable_amount' => '200.00',
        'tax_amount' => '42.00',
        'currency' => 'EUR',
        'tax_category_id' => 'S',
        'tax_category_name' => 'Taux standard', // Belgian BTCC value
        'tax_percent' => 21.0,
        'tax_scheme_id' => 'VAT'
    ]
]);

// 8. Totals
$ubl->addLegalMonetaryTotal([
    'line_extension_amount' => 200.00,
    'tax_exclusive_amount' => 200.00,
    'tax_inclusive_amount' => 242.00,
    'charge_total_amount' => 0.00,
    'payable_amount' => 242.00
], 'EUR');

// 9. Payment information
$ubl->addPaymentMeans(
    '30',                     // Credit transfer
    'Credit transfer',
    'BE-PAY-2024-001',
    'BE12 3456 7890 1234',   // Belgian IBAN
    'My Belgian Company BV',
    'BBRUBEBB',              // BIC code
    null,                    // Channel code not used in Belgium
    null                     // Due date handled at invoice level
);

$ubl->addPaymentTerms('Payment within 30 days', null, null, null);

// 10. Generate XML
$xml = $ubl->generateXml();
```

## Belgian VAT Rates

| Rate | Percentage | Tax Category ID | BTCC Name |
|------|------------|-----------------|-----------|
| Standard | 21% | S | Taux standard |
| Reduced | 6% | S | Taux réduit |
| Zero | 0% | Z | Taux zéro |
| Exempt | 0% | E | Exonéré |

## Belgian VAT Numbers

Format: `BE0123456789` (BE + 10 digits)

```php
// Validation
if (!preg_match('/^BE[0-9]{10}$/', $vatNumber)) {
    throw new InvalidArgumentException('Invalid Belgian VAT number');
}
```

## Validation

### Belgian PEPPOL Validator
Test your invoices at: https://ecosio.com/en/peppol-and-xml-document-validator/

### Common Errors

1. **ubl-BE-01**: Missing PEPPOL AdditionalDocumentReference
2. **ubl-BE-10**: Wrong tax category names (use BTCC values)
3. **ubl-BE-14**: TaxTotal in wrong position in InvoiceLine

## Endpoint Scheme IDs for Belgium

- `0208` - VAT number (most used)
- `0096` - DUNS number
- `0088` - EAN/GLN number

## Example Validation Response

✅ **Successful**:
```
✓ ubl-BE-01: PEPPOL reference present
✓ ubl-BE-10: Correct BTCC values used
✓ ubl-BE-14: TaxTotal correctly positioned
✓ XSD validation passed
```

❌ **Error**:
```
✗ ubl-BE-10: Tax category name "Standard rate" not allowed
  Use: "Taux standard"
```
