# Netherlands Implementation

This guide covers the specific requirements for generating UBL invoices for the Netherlands according to PEPPOL BIS Billing 3.0.

## Dutch Specifications

### PEPPOL BIS Billing 3.0

The Netherlands uses PEPPOL BIS Billing 3.0 with national rules (NL-R-001..NL-R-009).

Key requirements include:

- Credit note must reference the original invoice (NL-R-001).
- Supplier and (when Dutch) buyer address must include street, city, and post code (NL-R-002, NL-R-004).
- Legal entity identifier must be KVK or OIN with schemeID 0106 or 0190 (NL-R-003, NL-R-005).
- Payment means are required when payment is from customer to supplier (NL-R-007).
- Domestic payment means code must be one of 30, 48, 49, 57, 58, 59 (NL-R-008).
- If order line reference is used, a document-level order reference is required (NL-R-009).

### Automatic KVK/OIN Detection

The package automatically detects Dutch business registrations:

```php
// KVK number is automatically recognized
$endpointSchemeID = '0106';  // For KVK numbers

// OIN number for government organizations
$endpointSchemeID = '0190';  // For OIN numbers
```

## Complete Dutch Invoice

```php
use Darvis\UblPeppol\UblNlBis3Service;

$ubl = new UblNlBis3Service();
$ubl->createDocument();

// 1. Invoice header
$ubl->addInvoiceHeader('NL-INV-2024-001', '2024-01-15', '2024-02-14');

// 2. References
$ubl->addBuyerReference('CLIENT-001');
$ubl->addOrderReference('ORDER-2024-001');

// 3. Supplier (Dutch company)
$ubl->addAccountingSupplierParty(
    'NL123456789B01',         // VAT number as endpoint
    '0106',                   // KVK scheme for Dutch companies
    '12345678',               // KVK number
    'My Dutch Company BV',
    'Damrak 1',
    '1012 JS',
    'Amsterdam',
    'NL',
    'NL123456789B01'          // VAT number
);

// 4. Customer
$ubl->addAccountingCustomerParty(
    '87654321',                   // KVK as endpoint
    '0106',                       // KVK scheme
    '87654321',                   // Party ID
    'Customer Company BV',
    'Nieuwezijds Voorburgwal 123',
    '1012 RJ',
    'Amsterdam',
    'NL',
    null,                         // additionalStreet
    '87654321',                   // registrationNumber (KVK)
    null,                         // contactName
    null,                         // contactPhone
    null,                         // contactEmail
    'NL987654321B01'              // vatNumber (with country prefix!)
);

// 5. Invoice lines
$ubl->addInvoiceLine([
    'id' => '1',
    'quantity' => 5,
    'unit_code' => 'HUR',         // Hours
    'price_amount' => 85.00,
    'currency' => 'EUR',
    'name' => 'Software development',
    'description' => 'Frontend development - 5 hours',
    'tax_category_id' => 'S',
    'tax_percent' => 21.0,
    'tax_scheme_id' => 'VAT'
]);

// 6. Taxes
$ubl->addTaxTotal([
    [
        'taxable_amount' => '425.00',
        'tax_amount' => '89.25',
        'currency' => 'EUR',
        'tax_category_id' => 'S',
        'tax_percent' => 21.0,
        'tax_scheme_id' => 'VAT'
    ]
]);

// 7. Totals
$ubl->addLegalMonetaryTotal([
    'line_extension_amount' => 425.00,
    'tax_exclusive_amount' => 425.00,
    'tax_inclusive_amount' => 514.25,
    'charge_total_amount' => 0.00,
    'payable_amount' => 514.25
], 'EUR');

// 8. Payment information
$ubl->addPaymentMeans(
    '30',                     // Credit transfer
    'Credit transfer',
    'NL-PAY-2024-001',
    'NL12 ABNA 0123 4567 89', // Dutch IBAN
    'My Dutch Company BV',
    'ABNANL2A'               // BIC code
);

$ubl->addPaymentTerms('Payment within 14 days');

// 9. Generate XML
$xml = $ubl->generateXml();
$xml = $ubl->generateXml(true); // Optional basic validation
```

## Dutch VAT Rates

| Rate   | Percentage | Tax Category ID | Description   |
| ------ | ---------- | --------------- | ------------- |
| High   | 21%        | S               | Standard rate |
| Low    | 9%         | S               | Reduced rate  |
| Zero   | 0%         | Z               | Zero rate     |
| Exempt | 0%         | E               | Exempt        |

## Dutch Business Registrations

### KVK Numbers

Format: `12345678` (8 digits)

```php
// Automatic detection
$endpointSchemeID = '0106';  // For KVK numbers
```

### OIN Numbers (Government)

Format: `00000001234567890123` (20 digits)

```php
$endpointSchemeID = '0190';  // For OIN numbers
```

### VAT Numbers

Format: `NL123456789B01` (NL + 9 digits + B + 2 digits)

```php
// Validation
if (!preg_match('/^NL[0-9]{9}B[0-9]{2}$/', $vatNumber)) {
    throw new InvalidArgumentException('Invalid Dutch VAT number');
}
```

## Validation

### Dutch PEPPOL Validator

Test your invoices at: https://test.peppolautoriteit.nl/validate

### Common Unit Codes

| Code  | Description |
| ----- | ----------- |
| `C62` | Pieces      |
| `HUR` | Hours       |
| `DAY` | Days        |
| `MTR` | Meter       |
| `KGM` | Kilogram    |
| `LTR` | Liter       |

## Endpoint Scheme IDs for Netherlands

- `0106` - KVK number (most used for companies)
- `0190` - OIN number (government organizations)
- `0088` - EAN/GLN number
- `0096` - DUNS number

## Automatic Conversions

The package performs automatic conversions for Dutch specifications:

```php
// Automatic KVK detection
if (strtoupper($countryCode) === 'NL' && $endpointSchemeID === '0210') {
    $effectiveSchemeID = '0106';  // Convert to KVK
}
```

## Example Validation Response

✅ **Successful**:

```
✓ PEPPOL BIS Billing 3.0 compliant
✓ Dutch VAT number correct
✓ KVK number correctly formatted
✓ XSD validation passed
```

## Differences with Belgium

| Aspect               | Netherlands              | Belgium                  |
| -------------------- | ------------------------ | ------------------------ |
| National rules       | Yes (NL-R-001..NL-R-009) | EN 16931                 |
| Scheme ID            | 0106 (KVK)               | 0208 (VAT)               |
| Tax Category Names   | Standard PEPPOL          | BTCC values              |
| TaxTotal InvoiceLine | Not allowed (UBL-CR-561) | Not allowed (UBL-CR-561) |
