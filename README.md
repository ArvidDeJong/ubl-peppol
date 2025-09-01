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

If you're using Laravel, the service provider will be automatically registered via package discovery. You can then use the UblBis3Service through dependency injection or via the facade:

```php
use Darvis\UblPeppol\UblBis3Service;

class InvoiceController extends Controller
{
    public function generate(UblBis3Service $UblBis3Service)
    {
        // Use the UblBis3Service...
    }
}
```

Or via the app container:

```php
$UblBis3Service = app('ubl-peppol');
```

## Usage

Below is an example of how to generate a UBL invoice:

```php
use Darvis\UblPeppol\UblBis3Service;

// Create a new UBL Service instance
$UblBis3Service = new UblBis3Service();

// Set up the invoice details
$UblBis3Service->setId('INVOICE-2023-001');
$UblBis3Service->setIssueDate(new \DateTime());
$UblBis3Service->setDueDate(new \DateTime('+30 days'));

// Set up the supplier
$UblBis3Service->setSupplierName('Supplier Company Name');
$UblBis3Service->setSupplierStreet('Example Street 123');
$UblBis3Service->setSupplierCity('Amsterdam');
$UblBis3Service->setSupplierPostcode('1234 AB');
$UblBis3Service->setSupplierCountry('NL');
$UblBis3Service->setSupplierVat('NL123456789B01');
$UblBis3Service->setSupplierChamber('12345678');

// Set up the customer
$UblBis3Service->setCustomerName('Customer Company Name');
$UblBis3Service->setCustomerStreet('Customer Street 456');
$UblBis3Service->setCustomerCity('Rotterdam');
$UblBis3Service->setCustomerPostcode('5678 CD');
$UblBis3Service->setCustomerCountry('NL');
$UblBis3Service->setCustomerVat('NL987654321B01');

// Add invoice lines
$UblBis3Service->addLine(
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
$ublXml = $UblBis3Service->getUbl();

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
