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

#### `generateXml(bool $validateFirst = false): string`

Generates the final UBL XML string. When `validateFirst` is true, it runs basic validation before output.

```php
$xml = $ubl->generateXml();
$xml = $ubl->generateXml(true);
```

#### `enableStrictCodelistValidation(?string $jsonPath = null, ?CodelistRegistry $registry = null): self`

Enables strict codelist validation using a JSON file or a registry instance.

```php
use Darvis\UblPeppol\Validation\CodelistRegistry;

$ubl->enableStrictCodelistValidation('/path/to/peppol-codelists.json');
$registry = CodelistRegistry::fromJsonFile('/path/to/peppol-codelists.json');
$ubl->enableStrictCodelistValidation(registry: $registry);
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
    string $endpointId,           // VAT number WITHOUT country prefix (e.g., '0123456789' not 'BE0123456789')
    string $endpointSchemeID,     // '0208' for Belgium
    string $partyId,              // Company ID
    string $name,                 // Company name
    string $street,               // Street name + number
    string $postalCode,           // Postal code
    string $city,                 // City name
    string $country,              // 'BE'
    string $vatNumber,            // VAT number WITH prefix (e.g., 'BE0123456789')
    ?string $additionalStreet = null
);
```

**Important for Belgium**: The `endpointId` parameter should be the VAT number **WITHOUT** the "BE" prefix (e.g., `0123456789`), while the `vatNumber` parameter should include the prefix (e.g., `BE0123456789`).

#### `addAccountingCustomerParty(...): self`

```php
$ubl->addAccountingCustomerParty(
    string $endpointId,                  // VAT number WITHOUT country prefix for BE (e.g., '0987654321')
    string $endpointSchemeID,            // '0208' for Belgium, '0106' for Netherlands
    string $partyId,
    string $name,
    string $street,
    string $postalCode,
    string $city,
    string $country,
    ?string $additionalStreet = null,
    ?string $registrationNumber = null,  // KVK/KBO number
    ?string $contactName = null,
    ?string $contactPhone = null,
    ?string $contactEmail = null,
    ?string $vatNumber = null            // VAT number WITH country prefix (e.g., BE0987654321, NL123456789B01)
);
```

**Important**:

- For **Belgium**: The `endpointId` should be the VAT number **WITHOUT** the "BE" prefix (e.g., `0987654321`), while `vatNumber` includes the prefix (e.g., `BE0987654321`)
- For **Netherlands**: The `endpointId` is typically the KVK number (8 digits)
- The `vatNumber` parameter must include the country prefix per BR-CO-09 validation rule
- If no VAT number is provided, the `PartyTaxScheme` element will be omitted

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
    string $account_iban,         // IBAN number (without schemeID per UBL-CR-654)
    ?string $account_name,        // Account holder
    ?string $bic,                 // BIC code
    ?string $channel_code,
    ?string $due_date
);
```

**Note**: The IBAN is added without `schemeID` attribute per UBL-CR-654 compliance rule.

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

#### `generateXml(bool $validateFirst = false): string`

Same as Belgium. Optional validation is available before XML generation.

#### `enableStrictCodelistValidation(?string $jsonPath = null, ?CodelistRegistry $registry = null): self`

Same as Belgium. Strict validation requires a JSON codelist file.

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

- `0208` - Belgium VAT number (WITHOUT "BE" prefix - use only the 10 digits)
- `0106` - Netherlands KVK number (8 digits)
- `0190` - Netherlands OIN number

## PeppolService

For sending UBL invoices to the Peppol network.

### Constructor

```php
$peppolService = new PeppolService();
```

Reads configuration from `config/ubl-peppol.php` or environment variables.

### `sendInvoice(object $invoice, string $ublXml): array`

Send an invoice to the Peppol network.

```php
$result = $peppolService->sendInvoice($invoice, $ublXml);
// Returns: ['success' => bool, 'status_code' => int, 'message' => string, 'log_id' => int]
```

### `sendUblXml(string $ublXml, ?string $invoiceNumber = null): array`

Send UBL XML directly without an Invoice model.

```php
$result = $peppolService->sendUblXml($ublXml, 'INV-2024-001');
```

### `testConnection(): array`

Test the connection to the Peppol provider.

```php
$result = $peppolService->testConnection();
// Returns: ['success' => bool, 'status_code' => int, 'message' => string]
```

### `getConfig(): array`

Get current configuration (password hidden).

```php
$config = $peppolService->getConfig();
// Returns: ['url' => string, 'username' => string, 'password_configured' => bool]
```

## PeppolLog Model

For tracking sent invoices.

### Scopes

```php
PeppolLog::success();      // Status = success
PeppolLog::error();        // Status = error
PeppolLog::pending();      // Status = pending
PeppolLog::recent(7);      // Last 7 days
PeppolLog::olderThan(60);  // Older than 60 days
```

### Static Methods

```php
PeppolLog::cleanupOldLogs(60);  // Delete logs older than 60 days
```
