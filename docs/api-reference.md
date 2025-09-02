# API Reference

This page contains the complete API documentation for both UBL service classes.

## UblBeBis3Service (Belgium)

For Belgian UBL invoices according to EN 16931 standard.

### Constructor & Basic Methods

#### `__construct()`
```php
$ubl = new UblBeBis3Service();
```

#### `createDocument(): self`
Initializes the UBL document with namespaces.
```php
$ubl->createDocument();
```

#### `generateXml(): string`
Generates the final UBL XML string.
```php
$xml = $ubl->generateXml();
```

### Document Header

#### `addInvoiceHeader(string $invoiceNumber, $issueDate, $dueDate): self`
```php
$ubl->addInvoiceHeader('INV-2024-001', '2024-01-15', '2024-02-14');
```

#### `addBuyerReference(?string $buyerRef = 'BUYER_REF'): self`
```php
$ubl->addBuyerReference('KLANT-001');
```

#### `addOrderReference(string $orderNumber = 'PO-001'): self`
```php
$ubl->addOrderReference('ORDER-2024-001');
```

#### `addAdditionalDocumentReference(string $id, ?string $documentType = null): self`
```php
$ubl->addAdditionalDocumentReference('DOC-001', 'Contract');
```

### Parties

#### `addAccountingSupplierParty(...): self`
```php
$ubl->addAccountingSupplierParty(
    string $endpointId,           // VAT number
    string $endpointSchemeID,     // '0208' for Belgium
    string $partyId,              // Company ID
    string $name,                 // Company name
    string $street,               // Street name + number
    string $postalCode,           // Postal code
    string $city,                 // City name
    string $country,              // 'BE'
    string $vatNumber,            // VAT number
    ?string $additionalStreet = null
);
```

#### `addAccountingCustomerParty(...): self`
```php
$ubl->addAccountingCustomerParty(
    string $endpointId,
    string $endpointSchemeID,
    string $partyId,
    string $name,
    string $street,
    string $postalCode,
    string $city,
    string $country,
    ?string $additionalStreet = null,
    ?string $registrationNumber = null,
    ?string $contactName = null,
    ?string $contactPhone = null,
    ?string $contactEmail = null
);
```

### Invoice Lines

#### `addInvoiceLine(array $lineData): self`
```php
$ubl->addInvoiceLine([
    'id' => '1',
    'quantity' => 2,
    'unit_code' => 'C62',        // Pieces
    'price_amount' => 100.00,
    'currency' => 'EUR',
    'name' => 'Product name',
    'description' => 'Product description',
    'tax_category_id' => 'S',    // Standard rate
    'tax_percent' => 21.0,
    'tax_scheme_id' => 'VAT'
]);
```

### Taxes & Totals

#### `addTaxTotal(array $taxTotals): self`
```php
$ubl->addTaxTotal([
    [
        'taxable_amount' => '100.00',
        'tax_amount' => '21.00',
        'currency' => 'EUR',
        'tax_category_id' => 'S',
        'tax_percent' => 21.0,
        'tax_scheme_id' => 'VAT'
    ]
]);
```

#### `addLegalMonetaryTotal(array $totals, string $currency): self`
```php
$ubl->addLegalMonetaryTotal([
    'line_extension_amount' => 100.00,
    'tax_exclusive_amount' => 100.00,
    'tax_inclusive_amount' => 121.00,
    'charge_total_amount' => 0.00,
    'payable_amount' => 121.00
], 'EUR');
```

### Payment Information

#### `addPaymentMeans(...): self`
```php
$ubl->addPaymentMeans(
    string $paymentMeansCode,     // '30' = Credit transfer
    ?string $paymentMeansName,    // 'Credit transfer'
    string $paymentId,            // Payment reference
    string $account_iban,         // IBAN number
    ?string $account_name,        // Account holder
    ?string $bic,                 // BIC code
    ?string $channel_code,
    ?string $due_date
);
```

#### `addPaymentTerms(?string $note, ?float $discount_percent, ?float $discount_amount, ?string $discount_date): self`
```php
$ubl->addPaymentTerms('Payment within 30 days', null, null, null);
```

### Allowances & Charges

#### `addAllowanceCharge(...): self`
```php
$ubl->addAllowanceCharge(
    bool $isCharge,              // true = charge, false = allowance
    float $amount,               // Amount
    string $reason,              // Reason
    string $taxCategoryId,       // 'S'
    float $taxPercent,           // 21.0
    string $currency             // 'EUR'
);
```

### Delivery

#### `addDelivery(...): self`
```php
$ubl->addDelivery(
    string $deliveryDate,        // 'YYYY-MM-DD'
    string $locationId,          // Location ID
    string $locationSchemeId,    // '0088'
    string $street,              // Delivery address
    ?string $additional_street,
    string $city,
    string $postal_code,
    string $country,
    ?string $party_name = null
);
```

## UblNlBis3Service (Netherlands)

For Dutch UBL invoices. Has largely the same API as UblBeBis3Service, with these differences:

### Dutch Specifications

- Automatic KVK number detection with schemeID '0106'
- Support for OIN numbers with schemeID '0190'
- Dutch validation rules

### Usage

```php
use Darvis\UblPeppol\UblNlBis3Service;

$ubl = new UblNlBis3Service();
// Use the same methods as UblBeBis3Service
```

## Common Parameters

### Unit Codes (UN/ECE Recommendation 20)
- `C62` - Pieces
- `MTR` - Meter
- `KGM` - Kilogram
- `LTR` - Liter
- `HUR` - Hour

### Tax Category IDs
- `S` - Standard rate (21% Belgium/Netherlands)
- `Z` - Zero rate (0%)
- `E` - Exempt
- `AE` - Reverse charge

### Country Codes (ISO 3166-1)
- `BE` - Belgium
- `NL` - Netherlands
- `DE` - Germany
- `FR` - France

### Endpoint Scheme IDs
- `0208` - Belgium VAT number
- `0106` - Netherlands KVK number
- `0190` - Netherlands OIN number
