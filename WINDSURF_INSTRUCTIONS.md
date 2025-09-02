# Windsurf AI Instructions for UBL-PEPPOL Package

## Package Overview
This is the `darvis/ubl-peppol` package - a PHP library for generating UBL/PEPPOL invoices that comply with European e-invoicing standards. The package specifically supports Belgian (EN 16931) and Dutch implementations.

## Installation in Another Project

### Via Composer
```bash
composer require darvis/ubl-peppol
```

### Laravel Integration
The package automatically registers a service provider via package discovery. Use dependency injection:

```php
use Darvis\UblPeppol\UblBeBis3Service;
use Darvis\UblPeppol\UblNlBis3Service;

class InvoiceController extends Controller
{
    public function generateBelgianInvoice(UblBeBis3Service $ublService)
    {
        // Generate Belgian invoice
    }
    
    public function generateDutchInvoice(UblNlBis3Service $ublService)
    {
        // Generate Dutch invoice
    }
}
```

## Available Services

### 1. UblBeBis3Service (Belgium)
- For Belgian UBL invoices
- Fully EN 16931 compliant
- Supports all Belgian Schematron rules (ubl-BE-01, ubl-BE-10, ubl-BE-14)
- Correct BTCC values ("Taux standard", "Taux zéro")

### 2. UblNlBis3Service (Netherlands)
- For Dutch UBL invoices
- Supports KVK numbers with automatic schemeID
- PEPPOL Netherlands validator tested

## Basic Usage

### Step 1: Prepare Data Structure
```php
$invoiceData = [
    'invoice_number' => 'INV-2024-001',
    'invoice_date' => '2024-01-15',
    'due_date' => '2024-02-14',
    'currency' => 'EUR',
    
    // Supplier information
    'supplier' => [
        'name' => 'Company Name',
        'vat_number' => 'BE0123456789',
        'address' => [
            'street' => 'Street Name 123',
            'city' => 'Brussels',
            'postal_code' => '1000',
            'country' => 'BE'
        ]
    ],
    
    // Customer information
    'customer' => [
        'name' => 'Customer Name',
        'vat_number' => 'BE0987654321',
        'address' => [
            'street' => 'Customer Street 456',
            'city' => 'Antwerp',
            'postal_code' => '2000',
            'country' => 'BE'
        ]
    ],
    
    // Invoice lines
    'invoice_lines' => [
        [
            'id' => '1',
            'quantity' => 2,
            'unit_price' => 100.00,
            'vat_rate' => 21,
            'description' => 'Product description',
            'unit_code' => 'C62' // Pieces
        ]
    ]
];
```

### Step 2: Generate UBL XML
```php
use Darvis\UblPeppol\UblBeBis3Service;

// Instantiate service
$ublService = new UblBeBis3Service();

// Initialize document
$ublService->createDocument();

// Add invoice header
$ublService->addInvoiceHeader($invoiceData['invoice_number'], $invoiceData['invoice_date'], $invoiceData['due_date']);

// Add supplier
$ublService->addAccountingSupplierParty(
    $invoiceData['supplier']['vat_number'],
    '0208',  // endpointSchemeID
    $invoiceData['supplier']['vat_number'],
    $invoiceData['supplier']['name'],
    $invoiceData['supplier']['address']['street'],
    $invoiceData['supplier']['address']['postal_code'],
    $invoiceData['supplier']['address']['city'],
    $invoiceData['supplier']['address']['country'],
    $invoiceData['supplier']['vat_number']
);

// Add customer
$ublService->addAccountingCustomerParty(
    $invoiceData['customer']['vat_number'],
    '0208',  // endpointSchemeID
    $invoiceData['customer']['vat_number'],
    $invoiceData['customer']['name'],
    $invoiceData['customer']['address']['street'],
    $invoiceData['customer']['address']['postal_code'],
    $invoiceData['customer']['address']['city'],
    $invoiceData['customer']['address']['country']
);

// Add invoice lines
foreach ($invoiceData['invoice_lines'] as $line) {
    $ublService->addInvoiceLine([
        'id' => $line['id'],
        'quantity' => $line['quantity'],
        'unit_code' => $line['unit_code'],
        'price_amount' => $line['unit_price'],
        'currency' => $invoiceData['currency'],
        'name' => $line['description'],
        'description' => $line['description'],
        'tax_category_id' => 'S',
        'tax_percent' => $line['vat_rate'],
        'tax_scheme_id' => 'VAT'
    ]);
}

// Generate XML
$ublXml = $ublService->generateXml();

// Save as file
file_put_contents('invoice.xml', $ublXml);

// Or direct download
header('Content-Type: application/xml');
header('Content-Disposition: attachment; filename="invoice.xml"');
echo $ublXml;
```

## Using Examples

The package contains complete working examples in the `examples/` directory:

### Belgian Example
Via browser (start a local server):
```bash
php -S localhost:8000 -t vendor/darvis/ubl-peppol/examples/
```
Go to: `http://localhost:8000/be/generate_invoice_be.php`
Download XML: `http://localhost:8000/be/generate_invoice_be.php?download`

### Dutch Example  
Via browser (start a local server):
```bash
php -S localhost:8000 -t vendor/darvis/ubl-peppol/examples/
```
Go to: `http://localhost:8000/nl/generate_invoice_nl.php`
Download XML: `http://localhost:8000/nl/generate_invoice_nl.php?download`

### Test Data Structure
Check `vendor/darvis/ubl-peppol/examples/test_data.php` for a complete data structure with all required fields.

## Validation

The package is validated against:
- **Netherlands**: https://test.peppolautoriteit.nl/validate
- **Belgium**: https://ecosio.com/en/peppol-and-xml-document-validator/
- **Italy (General PEPPOL)**: https://peppol-docs.agid.gov.it/docs/validator/

## Important Notes for AI

### Belgian Specifications (from memory)
- ubl-BE-01: Second AdditionalDocumentReference required (PEPPOL with type PEPPOLInvoice)
- ubl-BE-10: TaxCategory Name must be "Taux standard" (not "Standaardtarief")
- ubl-BE-14: TaxTotal element required in InvoiceLine
- Use correct BTCC values for Belgian tax categories

### Data Validation
- Dates in `YYYY-MM-DD` format
- VAT numbers according to country-specific rules
- Currency codes according to ISO 4217
- Unit codes according to UN/ECE Recommendation 20

### Error Handling
```php
try {
    $ublXml = $ublService->generateInvoice($invoiceData);
} catch (\InvalidArgumentException $e) {
    // Validation error in input data
    echo "Data validation error: " . $e->getMessage();
} catch (\Exception $e) {
    // General error
    echo "Generation error: " . $e->getMessage();
}
```

## Requirements
- PHP 8.2 or higher
- DOM extension
- For Laravel: automatic service provider registration

## Support
- GitHub: https://github.com/ArvidDeJong/ubl-peppol
- Issues: https://github.com/ArvidDeJong/ubl-peppol/issues
- Documentation: https://docs.peppol.eu/poacc/billing/3.0/

## Tips for Windsurf AI
1. Always use the correct service class for the country (UblBeBis3Service for Belgium, UblNlBis3Service for Netherlands)
2. Check the examples in the `examples/` directory for complete implementations
3. Validate generated XML against official PEPPOL validators
4. Pay attention to country-specific requirements like VAT number formats and tax categories
5. Use the test data structure as a basis for new implementations
