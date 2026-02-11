# Validation & Compliance

This guide covers PEPPOL validation, compliance rules and testing of generated UBL documents.

## Specification Version

PEPPOL BIS Billing 3.0 release 3.0.19 is mandatory from 2025-08-25. This release updates
validation artefacts and several code lists (EAS, ICD, VATEX, ISO 4217, UNCL lists). Use
the latest official validators and schematron files to avoid false positives.

## PEPPOL Validators

### Netherlands

**URL**: https://test.peppolautoriteit.nl/validate

- Official Dutch PEPPOL validator
- Tests PEPPOL BIS Billing 3.0 compliance
- Supports Dutch specifications

### Belgium

**URL**: https://ecosio.com/en/peppol-and-xml-document-validator/

- Tests EN 16931 compliance
- Belgian Schematron rules (ubl-BE-\*)
- BTCC validation

### Italy (General PEPPOL)

**URL**: https://peppol-docs.agid.gov.it/docs/validator/

- General PEPPOL BIS Billing 3.0 validator
- International compliance test
- XSD schema validation

## Validation Rules

### XSD Schema Validation

Checks XML structure and data types:

```xml
<!-- Example error -->
<cbc:InvoiceNumber>123</cbc:InvoiceNumber>
<cbc:IssueDate>invalid-date</cbc:IssueDate>
```

### Schematron Validation

Checks business rules:

#### Official Schematron Files

- PEPPOL BIS rules (UBL): https://docs.peppol.eu/poacc/billing/3.0/files/PEPPOL-EN16931-UBL.sch
- EN 16931 rules (UBL): https://docs.peppol.eu/poacc/billing/3.0/files/CEN-EN16931-UBL.sch

### Strict Codelist Validation

Strict codelist validation requires a JSON file with the full lists you want to enforce.
Only enable strict mode when these lists are complete and up-to-date.

Example structure:

```json
{
  "iso4217": ["EUR", "USD"],
  "eas": ["0106", "0190", "0208", "0088"],
  "icd": ["0106", "0190", "0208", "0088"],
  "uncl4461": ["30", "48", "49", "57", "58", "59"],
  "uncl5305": ["S", "Z", "E", "AE", "K", "G", "O"],
  "uncl7143": [],
  "vatex": [],
  "uncl5189": [],
  "uncl7161": []
}
```

#### General PEPPOL Rules

- **UBL-CR-561**: TaxTotal not allowed in InvoiceLine
- **UBL-CR-504**: Required fields present
- **UBL-CR-597**: Correct data formats

#### Belgian Rules (EN 16931)

- **ubl-BE-01**: Second AdditionalDocumentReference required
- **ubl-BE-10**: Correct BTCC tax category names
- **ubl-BE-14**: TaxTotal position in InvoiceLine

#### Important Compliance Rules

- **BR-CO-09**: VAT number must start with ISO 3166-1 alpha-2 country code (e.g., `NL123456789B01`, `BE0123456789`)
- **BR-CO-10**: Sum of invoice lines must equal LineExtensionAmount
- **UBL-CR-654**: PayeeFinancialAccount/ID must NOT have schemeID attribute (IBAN without schemeID)

## Validation Workflow

### 1. Local Validation

```php
use Darvis\UblPeppol\Validation\UblValidator;
use Darvis\UblPeppol\Validation\CodelistRegistry;
use Darvis\UblPeppol\UblBeBis3Service;

// VAT number validation
$error = UblValidator::validateVatNumber($vatNumber);
if ($error) {
    throw new InvalidArgumentException($error);
}

// Validate VAT number
$error = UblValidator::validateVatNumber('BE0123456789');
if ($error) {
    throw new InvalidArgumentException($error);
}

// Validate tax category
if (!UblValidator::isValidTaxCategory('S')) {
    throw new InvalidArgumentException('Invalid tax category');
}

// Validate unit code
if (!UblValidator::isValidUnitCode('C62')) {
    throw new InvalidArgumentException('Invalid unit code');
}

// Strict codelist validation (requires JSON codelists)
$registry = CodelistRegistry::fromJsonFile('/path/to/peppol-codelists.json');
$ubl = new UblBeBis3Service();
$ubl->enableStrictCodelistValidation(registry: $registry);
```

### 2. XML Generation

```php
$ubl = new UblBeBis3Service();
$ubl->createDocument();
// ... add elements
$xml = $ubl->generateXml();
$xml = $ubl->generateXml(true); // Optional basic validation

// Strict codelist validation
$ubl->enableStrictCodelistValidation('/path/to/peppol-codelists.json');
$xml = $ubl->generateXml(true);

$ubl->addInvoiceHeader('INV-001', 'invalid-date', '2024-02-14');
// Throws: InvalidArgumentException('Invalid date format')
```

### 3. Online Validation

Upload the generated XML to a PEPPOL validator.

## Common Validation Errors

### Belgium Specific

#### ubl-BE-01 Error

```
❌ Error: AdditionalDocumentReference missing
```

**Solution**:

```php
$ubl->addAdditionalDocumentReference('PEPPOL', 'PEPPOLInvoice');
```

#### ubl-BE-10 Error

```
❌ Error: Tax category name "Standard rate" not allowed
```

**Solution**:

```php
// Use BTCC values
'tax_category_name' => 'Taux standard'  // Correct
'tax_category_name' => 'Standard rate'  // Error
```

#### ubl-BE-14 Error

```
❌ Error: TaxTotal incorrectly positioned in InvoiceLine
```

**Solution**: UblBeBis3Service handles this automatically.

### General PEPPOL Errors

#### UBL-CR-561

```
❌ Error: TaxTotal not allowed in InvoiceLine
```

**Solution**: Use UblBeBis3Service for Belgium (handles automatically).

#### Date Format Errors

```
❌ Error: Date must have YYYY-MM-DD format
```

**Solution**:

```php
$ubl->addInvoiceHeader('INV-001', '2024-01-15', '2024-02-14'); // Correct
$ubl->addInvoiceHeader('INV-001', '15-01-2024', '14-02-2024'); // Error
```

#### VAT Number Errors

```
❌ Error: Invalid VAT number format
```

**Solution**:

```php
// Belgium: BE + 10 digits
'BE0123456789'  // Correct
'BE123456789'   // Error (9 digits)

// Netherlands: NL + 9 digits + B + 2 digits
'NL123456789B01'  // Correct
'NL123456789'     // Error (missing B01)
```

## Compliance Checklist

### Belgium (EN 16931)

- [ ] ubl-BE-01: PEPPOL AdditionalDocumentReference added
- [ ] ubl-BE-10: BTCC tax category names used
- [ ] ubl-BE-14: TaxTotal correctly positioned
- [ ] VAT number format: BE + 10 digits
- [ ] Endpoint scheme ID: 0208

### Netherlands (PEPPOL BIS)

- [ ] KVK/OIN number correctly detected
- [ ] VAT number format: NL + 9 digits + B + 2 digits
- [ ] Endpoint scheme ID: 0106 (KVK) or 0190 (OIN)
- [ ] Credit note has BillingReference (NL-R-001)
- [ ] NL addresses include street, city, post code (NL-R-002, NL-R-004)
- [ ] Legal entity ID uses schemeID 0106 or 0190 (NL-R-003, NL-R-005)
- [ ] Payment means present when required (NL-R-007)
- [ ] Domestic payment means code allowed (NL-R-008)
- [ ] Order line reference implies OrderReference (NL-R-009)
- [ ] No TaxTotal in InvoiceLine (UBL-CR-561)

### General

- [ ] Date format: YYYY-MM-DD
- [ ] Required fields present
- [ ] Buyer reference or order reference present (PEPPOL-EN16931-R003)
- [ ] Correct unit codes used
- [ ] XML well-formed and valid

## Validation Results

#### Successful

```
✅ XML is valid
✅ PEPPOL BIS Billing 3.0 compliant
✅ All required fields present
```

#### With Errors

```
❌ ubl-BE-01: AdditionalDocumentReference missing
❌ ubl-BE-10: Tax category name must be BTCC value
❌ UBL-CR-561: TaxTotal not allowed in InvoiceLine
```

#### With Warnings

```
⚠️  Warning: Optional field missing
⚠️  Warning: Recommended practice not followed
```

## Automatic Validation

### Pre-validation in Package

The package performs automatic validation:

```php
// Automatische validatie bij invoer
$ubl->addInvoiceHeader('', '2024-01-15', '2024-02-14');
// Throws: InvalidArgumentException('Invoice number cannot be empty')

$ubl->addInvoiceHeader('INV-001', 'invalid-date', '2024-02-14');
// Throws: InvalidArgumentException('Invalid date format')
```

### Validatie Response Interpretatie

#### Succesvol

```
✅ Document is valid
✅ PEPPOL BIS Billing 3.0 compliant
✅ No validation errors found
```

#### Met Fouten

```
❌ Validation failed
❌ ubl-BE-10: Tax category name must be BTCC value
❌ UBL-CR-561: TaxTotal not allowed in InvoiceLine
```

#### Met Waarschuwingen

```
⚠️  Warning: Optional field missing
⚠️  Warning: Recommended practice not followed
```

## CI/CD Integration

```bash
# Add to your test pipeline
php vendor/bin/pest --filter=ValidationTest

# Or use an external validator
curl -X POST https://test.peppolautoriteit.nl/validate \
  -F "file=@generated_invoice.xml" \
  -F "format=xml"
```

## Test Strategies

### Unit Tests

```php
test('Belgian invoice validates correctly', function () {
    $ubl = new UblBeBis3Service();
    $ubl->createDocument();
    // ... add test data

    $xml = $ubl->generateXml();

    expect($xml)->toContain('AdditionalDocumentReference');
    expect($xml)->toContain('PEPPOL');
});
```

### Integration Tests

```php
test('Generated XML passes PEPPOL validation', function () {
    $xml = generateTestInvoice();

    $response = Http::attach(
        'file', $xml, 'test_invoice.xml'
    )->post('https://test.peppolautoriteit.nl/validate');

    expect($response->status())->toBe(200);
    expect($response->json('valid'))->toBeTrue();
});
```

## Manual Tests

1. Generate test XML with different scenarios
2. Upload to online validators
3. Check all error messages
4. Test with real PEPPOL endpoints (staging)

## Automatic Validation

### In Code

```php
use Darvis\UblPeppol\Validation\UblValidator;
use Darvis\UblPeppol\UblBeBis3Service;
use Darvis\UblPeppol\UblNlBis3Service;

// Validate input data before generation (optional)
$errors = UblValidator::validateInvoiceData($invoiceData);
if (!empty($errors)) {
    throw new InvalidArgumentException(implode("\n", $errors));
}

// Validate during generation (optional)
$be = new UblBeBis3Service();
$be->createDocument();
// ... add elements
$xml = $be->generateXml(true);

$nl = new UblNlBis3Service();
$nl->createDocument();
// ... add elements
$xml = $nl->generateXml(true);
```
