# UBL/PEPPOL Service

A PHP library for generating invoices according to the UBL/PEPPOL standard. This package allows you to generate UBL 2.1 documents that comply with the PEPPOL BIS Billing 3.0 standard for e-invoicing.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darvis/ubl-peppol.svg?style=flat-square)](https://packagist.org/packages/darvis/ubl-peppol)
[![Total Downloads](https://img.shields.io/packagist/dt/darvis/ubl-peppol.svg?style=flat-square)](https://packagist.org/packages/darvis/ubl-peppol)

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

Below is an example of how to generate a UBL invoice:

```php
use Darvis\UblPeppol\UblNLBis3Service;

// Create a new UBL Service instance
$UblNLBis3Service = new UblNLBis3Service();

// Set up the invoice details
$UblNLBis3Service->setId('INVOICE-2023-001');
$UblNLBis3Service->setIssueDate(new \DateTime());
$UblNLBis3Service->setDueDate(new \DateTime('+30 days'));

// Set up the supplier
$UblNLBis3Service->setSupplierName('Supplier Company Name');
$UblNLBis3Service->setSupplierStreet('Example Street 123');
$UblNLBis3Service->setSupplierCity('Amsterdam');
$UblNLBis3Service->setSupplierPostcode('1234 AB');
$UblNLBis3Service->setSupplierCountry('NL');
$UblNLBis3Service->setSupplierVat('NL123456789B01');
$UblNLBis3Service->setSupplierChamber('12345678');

// Set up the customer
$UblNLBis3Service->setCustomerName('Customer Company Name');
$UblNLBis3Service->setCustomerStreet('Customer Street 456');
$UblNLBis3Service->setCustomerCity('Rotterdam');
$UblNLBis3Service->setCustomerPostcode('5678 CD');
$UblNLBis3Service->setCustomerCountry('NL');
$UblNLBis3Service->setCustomerVat('NL987654321B01');

// Add invoice lines
$UblNLBis3Service->addLine(
    '1',                  // Line ID
    'Product description',   // Description
    2,                    // Quantity
    'EA',                 // Unit
    100.00,               // Price per unit (excl. btw)
    21.00,                // VAT percentage
    'S',                  // VAT category
    'Standard rate'         // VAT description
);

// Generate the UBL XML document
$ublXml = $UblNLBis3Service->getUbl();

// You can now save or send the document
file_put_contents('invoice.xml', $ublXml);
```

Refer to the examples in the `examples` directory for more usage options.

## Features

- Generate UBL 2.1 invoices according to the PEPPOL standard
- Support for different VAT rates
- Automatic calculation of totals and VAT amounts
- Input data validation

## Requirements

- PHP 8.2 or higher
- DOM extension

## Contributing

Contributions are welcome! Feel free to create issues or submit pull requests.

## License

This package is open-source software licensed under the [MIT License](LICENSE).
