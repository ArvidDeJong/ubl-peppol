# UBL PEPPOL Generator for PHP

An easy-to-use PHP package for generating UBL XML documents that comply with the PEPPOL BIS Billing 3.0 standard, specifically tailored for the Belgian implementation (EN 16931).

## Objective

This package enables developers to easily generate UBL documents that comply with PEPPOL standards, without requiring in-depth knowledge of the UBL specification.

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

2. Basic Example:
   ```php
   use Darvis\UblPeppol\UblService;
   
   $ubl = new UblService();
   $xml = $ubl->createInvoice([
       'supplier' => [
           'name' => 'Supplier BV',
           'vat_number' => 'BE0123456789',
           // ... other required fields
       ],
       // ... other invoice data
   ]);
   
   file_put_contents('invoice.xml', $xml);
   ```

## Documentation

- `src/UblService.php` - Main class for generating UBL documents
- `examples/` - Example files for reference
  - `base-example.xml` - Basic UBL invoice
  - `creditnote-example.xml` - Credit note example
  - `allowance-example.xml` - Example with allowances

## Requirements

- PHP 8.1 or higher
- PHP extensions: DOM, SimpleXML, XMLWriter

## License

This package is available under the [MIT License](LICENSE). See the [LICENSE](LICENSE) file for more information.

## Contributions

Contributions are welcome! When submitting pull requests, ensure that your code adheres to the PSR-12 standards and don't forget to add tests for new functionality.

## Support

For questions or issues, please open an issue in the GitHub repository or contact the maintainers.