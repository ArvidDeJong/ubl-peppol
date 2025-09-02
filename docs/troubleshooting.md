# Troubleshooting

This guide helps solve common problems with the UBL-PEPPOL package.

## Installation Problems

### Composer Errors

#### Error: "Package not found"
```bash
Package darvis/ubl-peppol not found
```

**Solution**:
1. Check the package name spelling
2. Run `composer clear-cache`
3. Try installing again

#### Error: "PHP version requirement"
```bash
darvis/ubl-peppol requires php >=8.2
```

**Solution**:
1. Upgrade PHP to version 8.2 or higher
2. Check with `php -v`
3. Update composer.json requirements
### Laravel Integration Problems

#### Service Provider not loaded
```bash
Class 'UblBeBis3Service' not found
```

**Solution**:
1. Run `composer dump-autoload`
2. Check if the service provider is registered:
```php
// config/app.php
'providers' => [
    Darvis\UblPeppol\UblPeppolServiceProvider::class,
],
```

## Runtime Errors

### DOMDocument Errors

#### Error: "DOMDocument extension not loaded"
```bash
Class 'DOMDocument' not found
```

**Solution**:
1. Install PHP DOM extension:
```bash
# Ubuntu/Debian
sudo apt-get install php-xml

# CentOS/RHEL
sudo yum install php-xml

# macOS (Homebrew)
brew install php
```
2. Restart web server

#### Error: "Invalid XML structure"
```bash
DOMDocument::loadXML(): Start tag expected
```

**Solution**:
1. Check if `createDocument()` was called
2. Add elements before XML generation
3. Debug with `var_dump($ubl->generateXml())`

### Memory Errors

#### Error: "Fatal error: Allowed memory size exhausted"
```bash
Fatal error: Allowed memory size of 134217728 bytes exhausted
```

**Solution**:
1. Increase memory limit in php.ini:
```ini
memory_limit = 256M
```
2. Or temporarily in code:
```php
ini_set('memory_limit', '256M');
```

### Document Already Initialized
```php
❌ RuntimeException: Document is already initialized
```
**Cause**: `createDocument()` is called multiple times.

**Solution**:
```php
$ubl = new UblBeBis3Service();
$ubl->createDocument(); // ✅ Eén keer
// $ubl->createDocument(); // ❌ Niet opnieuw aanroepen
```

### Root Element Not Initialized
```php
❌ RuntimeException: Root element is not initialized
```
**Cause**: Elements added before `createDocument()`.

**Solution**:
```php
$ubl = new UblBeBis3Service();
$ubl->createDocument(); // ✅ Eerst document initialiseren
$ubl->addInvoiceHeader('INV-001', '2024-01-15', '2024-02-14');
```

### Invalid Date Format
```php
❌ InvalidArgumentException: Invalid date format
```
**Cause**: Date not in YYYY-MM-DD format.

**Solution**:
```php
// ✅ Correct format
$ubl->addInvoiceHeader('INV-001', '2024-01-15', '2024-02-14');

// ❌ Wrong formats
$ubl->addInvoiceHeader('INV-001', '15-01-2024', '14/02/2024');
$ubl->addInvoiceHeader('INV-001', '2024/01/15', '2024.02.14');
```

## Validation Errors

### PEPPOL Validation

#### ubl-BE-01: AdditionalDocumentReference missing
```xml
❌ Element 'AdditionalDocumentReference' is missing
```

**Solution**:
```php
$ubl->addAdditionalDocumentReference('PEPPOL', 'PEPPOLInvoice');
```

#### ubl-BE-10: Invalid tax category name
```xml
❌ Tax category name 'Standard rate' not in BTCC list
```

**Solution**:
```php
// Use correct BTCC values
'tax_category_name' => 'Taux standard'     // ✅ Correct
'tax_category_name' => 'Standard rate'     // ❌ Error
'tax_category_name' => 'Standaardtarief'   // ❌ Error
```

#### UBL-CR-561: TaxTotal in InvoiceLine
```xml
❌ Element 'TaxTotal' not allowed in 'InvoiceLine'
```

**Solution**:
- For Netherlands: Use `UblNlBis3Service` (no TaxTotal in InvoiceLine)
- For Belgium: Use `UblBeBis3Service` (handles ubl-BE-14 automatically)

### VAT Number Validation

#### Invalid VAT number format
```php
InvalidArgumentException: Invalid VAT number format
```

**Solution**:
```php
// Belgium: BE + 10 digits
'BE0123456789'  // ✅ Correct
'BE123456789'   // ❌ Error (9 digits)
'123456789'     // ❌ Error (no country code)

// Netherlands: NL + 9 digits + B + 2 digits
'NL123456789B01'  // ✅ Correct
'NL123456789'     // ❌ Error (missing B01)
'123456789B01'    // ❌ Error (no country code)
```

#### Wrong Endpoint Scheme for NL
```php
❌ Endpoint scheme '0208' not valid for Netherlands
```

**Solution**:
```php
// ✅ For Netherlands
$endpointSchemeID = '0106'; // KVK
$endpointSchemeID = '0190'; // OIN

// ❌ For Netherlands
$endpointSchemeID = '0208'; // Belgium VAT
```

## XML Generation Problems

### Empty XML Output
```php
$xml = $ubl->generateXml();
echo $xml; // Empty or minimal XML
```
**Cause**: No elements added after `createDocument()`.

**Solution**:
```php
$ubl = new UblBeBis3Service();
$ubl->createDocument();
$ubl->addInvoiceHeader('INV-001', '2024-01-15', '2024-02-14');
// Voeg meer elementen toe...
$xml = $ubl->generateXml();
```

### Malformed XML
```
❌ XML Parse Error: not well-formed
```
**Cause**: Special characters in data.

**Solution**:
```php
// ✅ Escape special characters
$description = htmlspecialchars('Consultancy & Support', ENT_XML1);

// Or use CDATA for complex content
$description = '<![CDATA[Consultancy & Support <special>]]>';
```

### Encoding Problems
```
❌ Invalid UTF-8 sequence
```
**Solution**:
```php
// Ensure UTF-8 encoding
$name = mb_convert_encoding($name, 'UTF-8');

// Or validate input
if (!mb_check_encoding($name, 'UTF-8')) {
    throw new InvalidArgumentException('Invalid UTF-8 encoding');
}
```

## Performance Problems

### Slow XML Generation
**Cause**: Large number of invoice lines.

**Solution**:
```php
// Batch processing for large invoices
$lines = array_chunk($invoiceLines, 100);
foreach ($lines as $batch) {
    foreach ($batch as $line) {
        $ubl->addInvoiceLine($line);
    }
    // Optional: garbage collection
    gc_collect_cycles();
}
```

### Memory Issues
```
❌ Fatal error: Allowed memory size exhausted
```
**Solution**:
```php
// Increase memory limit
ini_set('memory_limit', '256M');

// Or use streaming for large files
$ubl = new UblBeBis3Service();
$ubl->createDocument();
// Add elements in batches
```

## Browser Examples Problems

### 404 Not Found
```
❌ GET /be/generate_invoice_be.php - No such file or directory
```
**Solution**:
```bash
# Ensure server runs in correct directory
cd /path/to/ubl-peppol
php -S localhost:8000 -t examples/
```

### Download Not Working
**Cause**: Headers already sent.

**Solution**:
```php
// Ensure no output before headers
<?php
// No echo/print statements here

if (isset($_GET['download'])) {
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="invoice.xml"');
    echo $xml;
    exit;
}
```

## Debug Tips

### Enable Error Reporting
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### XML Validation Debug
```php
// Validate XML structure
$dom = new DOMDocument();
$dom->loadXML($xml);
if (!$dom->schemaValidate('path/to/ubl-schema.xsd')) {
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        echo "XML Error: " . $error->message;
    }
}
```

### Service Debug
```php
// Check internal state
$reflection = new ReflectionClass($ubl);
$property = $reflection->getProperty('rootElement');
$property->setAccessible(true);
$rootElement = $property->getValue($ubl);

if (!$rootElement) {
    echo "Document not initialized";
}
```

## Frequently Asked Questions

### Q: Can I use both services in one project?
**A**: Yes, use the correct service per invoice:
```php
// For Belgian customers
$belgianInvoice = new UblBeBis3Service();

// For Dutch customers  
$dutchInvoice = new UblNlBis3Service();
```

### Q: How do I test my XML against validators?
**A**: 
1. Generate XML with `generateXml()`
2. Save as file
3. Upload to validator websites
4. Check validation results

### Q: Can I add custom XML elements?
**A**: Use the `addChildElement()` helper methods to add custom elements within the UBL structure.

### Q: Does the package work with Laravel?
**A**: Yes, the service provider is automatically registered via package discovery.

## Support

For further help:
- **GitHub Issues**: https://github.com/ArvidDeJong/ubl-peppol/issues
- **Email**: info@arvid.nl
- **Documentation**: `/docs` directory
