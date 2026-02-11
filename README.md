# UBL/PEPPOL Service

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darvis/ubl-peppol.svg?style=flat-square)](https://packagist.org/packages/darvis/ubl-peppol)
[![Total Downloads](https://img.shields.io/packagist/dt/darvis/ubl-peppol.svg?style=flat-square)](https://packagist.org/packages/darvis/ubl-peppol)

PHP library for generating UBL 2.1 invoices compliant with PEPPOL BIS Billing 3.0. Supports Belgian (EN 16931) and Dutch implementations.

## Installation

```bash
composer require darvis/ubl-peppol
```

**Requirements:** PHP 8.2+, DOM extension

## Quick Start (Standalone)

```php
use Darvis\UblPeppol\UblBeBis3Service;

$ublService = new UblBeBis3Service();
$xml = $ublService->generateInvoice($invoiceData);
file_put_contents('invoice.xml', $xml);
```

See `examples/` for complete implementations.

## Laravel Integration

Additional features: Peppol network integration, logging, artisan commands.

```bash
php artisan vendor:publish --tag=ubl-peppol-config
php artisan migrate
```

📖 **[Laravel Documentation](docs/laravel-integration.md)**

## Documentation

- [API Reference](docs/api-reference.md)
- [Belgian Implementation](docs/belgium-implementation.md)
- [Dutch Implementation](docs/netherlands-implementation.md)
- [VIES VAT Validation](docs/vies-validation.md)
- [Company Registration Validation](docs/company-registration-validation.md)
- [Laravel Integration](docs/laravel-integration.md)
- [Validation](docs/validation.md)
- [Troubleshooting](docs/troubleshooting.md)

## Author

**Arvid de Jong**  
Email: info@arvid.nl  
Website: [arvid.nl](https://arvid.nl)

## Contributing

Contributions are welcome! Feel free to create issues or submit pull requests.

## License

This package is open-source software licensed under the [MIT License](LICENSE).

## Credit Notes Support (v1.6.0)

Generate PEPPOL-compliant Credit Notes with automatic validation:

```php
use Darvis\UblPeppol\UblBeBis3Service;

$service = new UblBeBis3Service();
$service->createCreditNoteDocument();  // Not createDocument()!
$service->addCreditNoteHeader('C2026-001', '2026-01-21');
$service->addBillingReference('F2026-050', '2026-01-15'); // REQUIRED (BR-55)
// ... add parties, lines, totals ...
$xml = $service->generateXml();
```

### Key Points
- Use `createCreditNoteDocument()` instead of `createDocument()`
- `addBillingReference()` is **REQUIRED** (PEPPOL BR-55)
- All amounts must be **POSITIVE** (credit nature = document type 381)
- Use `addCreditNoteLine()` instead of `addInvoiceLine()`

See [docs/CREDIT_NOTES.md](docs/CREDIT_NOTES.md) for full documentation.
