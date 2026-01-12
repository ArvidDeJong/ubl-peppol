# Validation & Compliance

This guide covers PEPPOL validation, compliance rules and testing of generated UBL documents.

## PEPPOL Validators

### Netherlands
**URL**: https://test.peppolautoriteit.nl/validate
- Official Dutch PEPPOL validator
- Tests PEPPOL BIS Billing 3.0 compliance
- Supports Dutch specifications

### Belgium
**URL**: https://ecosio.com/en/peppol-and-xml-document-validator/
- Tests EN 16931 compliance
- Belgian Schematron rules (ubl-BE-*)
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

// Automatic VAT number validation
if (!UblValidator::isValidVatNumber($vatNumber, $countryCode)) {
    throw new InvalidArgumentException("Invalid VAT number: {$vatNumber}");
}

// Automatic date validation
if (!UblValidator::isValidDate($date)) {
    throw new InvalidArgumentException("Invalid date: {$date}");
}

// Validate VAT number
if (!UblValidator::isValidVatNumber('BE0123456789', 'BE')) {
    throw new InvalidArgumentException('Invalid VAT number');
}

// Validate tax category
if (!UblValidator::isValidTaxCategory('S')) {
    throw new InvalidArgumentException('Invalid tax category');
}
```

### 2. XML Generation
```php
$ubl = new UblBeBis3Service();
$ubl->createDocument();
// ... add elements
$xml = $ubl->generateXml();

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
- [ ] No TaxTotal in InvoiceLine

### General
- [ ] Date format: YYYY-MM-DD
- [ ] Required fields present
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

// Validate before generation
$validator = new UblValidator();
if (!$validator->validateBeforeGeneration($invoiceData)) {
    throw new ValidationException('Invalid invoice data');
}

// Generate XML
$xml = $ubl->generateXml();

// Validate generated XML
if (!$validator->validateXml($xml)) {
    throw new ValidationException('Invalid XML generated');
}
```
