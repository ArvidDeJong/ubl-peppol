# UBL-PEPPOL Documentation

Welcome to the comprehensive documentation for the UBL-PEPPOL package. This documentation helps you implement PEPPOL-compliant UBL invoices for Belgium and the Netherlands.

## 📚 Documentation Overview

### Basic Documentation
- [**Installation & Setup**](installation.md) - Install and configure the package
- [**Quick Start Guide**](quick-start.md) - Get started with examples
- [**API Reference**](api-reference.md) - Complete method documentation

### Country-Specific Implementations
- [**Belgium (EN 16931)**](belgium-implementation.md) - Belgian UBL specifications
- [**Netherlands**](netherlands-implementation.md) - Dutch UBL specifications

### Advanced Topics
- [**Validation & Compliance**](validation.md) - PEPPOL validators and rules
- [**Troubleshooting**](troubleshooting.md) - Common problems and solutions
- [**Best Practices**](best-practices.md) - Recommended approaches

### For Developers
- [**Contributing**](contributing.md) - Contributing to the project
- [**Testing**](testing.md) - Running and writing tests
- [**Changelog**](../CHANGELOG.md) - Version history

## 🚀 Quick Start

```php
use Darvis\UblPeppol\UblBeBis3Service;

// Belgian invoice
$ubl = new UblBeBis3Service();
$ubl->createDocument();
$ubl->addInvoiceHeader('INV-001', '2024-01-15', '2024-02-14');
// ... add more elements
$xml = $ubl->generateXml();
```

## 🔗 External Links

- [PEPPOL BIS Billing 3.0 Documentation](https://docs.peppol.eu/poacc/billing/3.0/)
- [Dutch PEPPOL Validator](https://test.peppolautoriteit.nl/validate)
- [Belgian PEPPOL Validator](https://ecosio.com/en/peppol-and-xml-document-validator/)
- [Italian PEPPOL Validator](https://peppol-docs.agid.gov.it/docs/validator/)

## 📞 Support

For questions or issues:
- GitHub Issues: [ubl-peppol/issues](https://github.com/ArvidDeJong/ubl-peppol/issues)
- Email: info@arvid.nl
