# UBL/PEPPOL Service

A PHP library for generating invoices according to the UBL/PEPPOL standard. This package allows you to generate UBL 2.1 documents that comply with the PEPPOL BIS Billing 3.0 standard for e-invoicing, with full support for Belgian implementation (EN 16931) and multi-country validation.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darvis/ubl-peppol.svg?style=flat-square)](https://packagist.org/packages/darvis/ubl-peppol)
[![Total Downloads](https://img.shields.io/packagist/dt/darvis/ubl-peppol.svg?style=flat-square)](https://packagist.org/packages/darvis/ubl-peppol)


# UBL PEPPOL Generator for PHP

An easy-to-use PHP package for generating UBL XML documents that comply with the PEPPOL BIS Billing 3.0 standard, specifically tailored for the Belgian implementation (EN 16931).

## Objective

This package enables developers to easily generate UBL documents that comply with PEPPOL standards, without requiring in-depth knowledge of the UBL specification. The package supports multiple PEPPOL validation standards including Belgium, Netherlands, and Italy.

## Documentation

📖 **[Complete Documentation](docs/README.md)** - Detailed guides, API reference, and implementation examples

**Official PEPPOL Documentation**: https://docs.peppol.eu/poacc/billing/3.0/

## Validation Testing

### Netherlands
https://test.peppolautoriteit.nl/validate

### Belgium
https://ecosio.com/en/peppol-and-xml-document-validator/
Ruleset: OpenPeppol UBL Invoice (2024.11) (aka BIS Billing 3.0.18)

### Italy (General PEPPOL)
https://peppol-docs.agid.gov.it/docs/validator/

## Installation

You can install this package via Composer:

```bash
composer require darvis/ubl-peppol
```

## Laravel Installation

If you're using Laravel, the service provider will be automatically registered via package discovery. You can then use the UblNLBis3Service through dependency injection or via the facade:

```php
use Darvis\UblPeppol\UblNLBis3Service;

class InvoiceController extends Controller
{
    public function generate(UblNLBis3Service $UblNLBis3Service)
    {
        // Use the UblNLBis3Service...
    }
}
```

Or via the app container:

```php
$UblNLBis3Service = app('ubl-peppol');
```

## Usage

### Quick Start

For a quick start, see the complete examples in the `examples/` directory:

- **`examples/be/generate_invoice_be.php`** - Complete Belgian UBL invoice example
- **`examples/nl/generate_invoice_nl.php`** - Complete Dutch UBL invoice example  
- **`examples/test_data.php`** - Sample invoice data structure
- **`examples/index.php`** - Basic usage demonstration

### Basic Example

```php
use Darvis\UblPeppol\UblBeBis3Service;

// Create a new UBL Service instance for Belgian invoices
$ublService = new UblBeBis3Service();

// Load test data (see examples/test_data.php for structure)
$invoiceData = require 'examples/test_data.php';

// Generate the UBL XML document
$ublXml = $ublService->generateInvoice($invoiceData);

// Save the generated invoice
file_put_contents('invoice.xml', $ublXml);
```

### Country-Specific Services

- **`UblBeBis3Service`** - For Belgian UBL invoices (EN 16931 compliant)
- **`UblNlBis3Service`** - For Dutch UBL invoices

### Complete Examples

For detailed implementation examples with full invoice data structures, validation, and country-specific requirements, check the `examples/` directory. Each example includes:

- Complete invoice data setup
- Supplier and customer information
- Invoice lines with tax calculations
- Payment terms and delivery information
- Country-specific validation requirements

📚 **[View Full Documentation](docs/README.md)** for comprehensive guides on:
- [API Reference](docs/api-reference.md)
- [Belgian Implementation](docs/belgium-implementation.md)
- [Dutch Implementation](docs/netherlands-implementation.md)
- [Validation & Compliance](docs/validation.md)
- [Troubleshooting](docs/troubleshooting.md)

## Features

- Generate UBL 2.1 invoices according to the PEPPOL standard
- **Belgian Implementation (EN 16931)**: Full compliance with Belgian UBL Schematron rules
- **Multi-Country Validation**: Tested against Belgian, Dutch, and Italian PEPPOL validators
- **Correct BTCC Values**: Proper Belgian tax category names ("Taux standard", "Taux zéro")
- Support for different VAT rates and tax categories
- Automatic calculation of totals and VAT amounts
- Input data validation with country-specific requirements
- XSD and Schematron validation compliance
- **PeppolService**: Send invoices to the Peppol network via access point providers
- **PeppolLog**: Track sent invoices with success/error logging

## Peppol Network Integration

This package includes a `PeppolService` for sending UBL invoices to the Peppol network via access point providers.

### Configuration

Add the following to your `.env` file:

```env
PEPPOL_URL=https://your-provider.com/api/endpoint
PEPPOL_USERNAME=your_username
PEPPOL_PASSWORD=your_password
```

### Publish Config & Migrations

```bash
php artisan vendor:publish --tag=ubl-peppol-config
php artisan vendor:publish --tag=ubl-peppol-migrations
php artisan migrate
```

### Usage

```php
use Darvis\UblPeppol\PeppolService;
use Darvis\UblPeppol\UblBeBis3Service;

// Generate UBL XML
$ublService = new UblBeBis3Service();
// ... configure invoice ...
$ublXml = $ublService->generateXml();

// Send to Peppol network
$peppolService = new PeppolService();
$result = $peppolService->sendInvoice($invoice, $ublXml);

if ($result['success']) {
    echo "Invoice sent successfully!";
} else {
    echo "Error: " . $result['error'];
}

// Or send XML directly without Invoice model
$result = $peppolService->sendUblXml($ublXml, 'INV-2024-001');

// Test connection
$result = $peppolService->testConnection();
```

### PeppolLog Model

All sent invoices are logged in the `peppol_logs` table:

```php
use Darvis\UblPeppol\Models\PeppolLog;

// Get recent errors
$errors = PeppolLog::error()->recent(7)->get();

// Cleanup old logs (default 60 days)
PeppolLog::cleanupOldLogs(60);
```

## Validation Testing

The package has been validated against multiple PEPPOL validators:

- **Netherlands**: https://test.peppolautoriteit.nl/validate
- **Belgium**: https://ecosio.com/en/peppol-and-xml-document-validator/
- **Italy (General PEPPOL)**: https://peppol-docs.agid.gov.it/docs/validator/

## Recent Updates (v1.2.0)

- Fixed Belgian UBL Schematron validation errors (ubl-BE-01, ubl-BE-10, ubl-BE-14)
- Added proper BTCC values for Belgian tax categories
- Enhanced XSD validation compliance
- Fixed PEPPOL Italy validator warnings
- Added automatic schemeID handling for Dutch KVK numbers
- Improved Italian Codice Fiscale format validation

## Requirements

- PHP 8.2 or higher
- DOM extension

## Author

**Arvid de Jong**  
Email: info@arvid.nl  
Website: [arvid.nl](https://arvid.nl)

## Contributing

Contributions are welcome! Feel free to create issues or submit pull requests.

## License

This package is open-source software licensed under the [MIT License](LICENSE).
