# UBL PEPPOL Generator for PHP

An easy-to-use PHP package for generating UBL XML documents that comply with the PEPPOL BIS Billing 3.0 standard, specifically tailored for the Belgian implementation (EN 16931).

## Objective

This package enables developers to easily generate UBL documents that comply with PEPPOL standards, without requiring in-depth knowledge of the UBL specification. The package supports multiple PEPPOL validation standards including Belgium, Netherlands, and Italy.

## Documentation

https://docs.peppol.eu/poacc/billing/3.0/

## Validation Testing

### Netherlands
https://test.peppolautoriteit.nl/validate

### Belgium
https://ecosio.com/en/peppol-and-xml-document-validator/

### Italy (General PEPPOL)
https://peppol-docs.agid.gov.it/docs/validator/

## Recent Updates (v1.2.0)

- **Belgian Compliance**: Full support for Belgian UBL Schematron rules (ubl-BE-01, ubl-BE-10, ubl-BE-14)
- **Multi-Country Support**: Validated against Belgian, Dutch, and Italian PEPPOL validators
- **Correct BTCC Values**: Implemented proper Belgian tax category names ("Taux standard", "Taux zéro")
- **Enhanced Validation**: Fixed XSD and Schematron validation errors across multiple standards

## Key Features

- **Supported Documents**:
  - Invoices
  - Credit Notes
  - Corrective Invoices
  - Invoice Lists

- **Validation**:
  - Required field validation
  - Date format validation (`YYYY-MM-DD`)
  - Numeric and currency format validation
  - VAT number validation according to Belgian rules

- **Features**:
  - Simple, intuitive API
  - Comprehensive error messages
  - Support for multiple currencies
  - Flexible addition of extra UBL elements

## Quick Start

1. Install the package via Composer:
   ```bash
   composer require darvis/ubl-peppol
   ```

2. Run the examples:
   ```bash
   # Belgian UBL invoice example
   php examples/be/generate_invoice_be.php
   
   # Dutch UBL invoice example  
   php examples/nl/generate_invoice_nl.php
   
   # Basic demonstration
   php examples/index.php
   ```

3. Basic Example:
   ```php
   use Darvis\UblPeppol\UblBeBis3Service;
   
   // Load test data structure
   $invoiceData = require 'examples/test_data.php';
   
   $ubl = new UblBeBis3Service();
   $xml = $ubl->generateInvoice($invoiceData);
   
   file_put_contents('invoice.xml', $xml);
   ```

## Examples

The `examples/` directory contains complete working examples:

- **`examples/be/generate_invoice_be.php`** - Belgian UBL invoice with full EN 16931 compliance
- **`examples/nl/generate_invoice_nl.php`** - Dutch UBL invoice example
- **`examples/test_data.php`** - Complete invoice data structure with all required fields
- **`examples/index.php`** - Basic usage demonstration

## Documentation

- `src/UblBeBis3Service.php` - Belgian UBL service class
- `src/UblNlBis3Service.php` - Dutch UBL service class

## Requirements

- PHP 8.1 or higher
- PHP extensions: DOM, SimpleXML, XMLWriter

## License

This package is available under the [MIT License](LICENSE). See the [LICENSE](LICENSE) file for more information.

## Contributions

Contributions are welcome! When submitting pull requests, ensure that your code adheres to the PSR-12 standards and don't forget to add tests for new functionality.

## Support

For questions or issues, please open an issue in the GitHub repository or contact the maintainers.