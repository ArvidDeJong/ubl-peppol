# Quick Start Guide

## Standalone Usage

```php
use Darvis\UblPeppol\UblBeBis3Service;

$ubl = new UblBeBis3Service();
$xml = $ubl->generateInvoice($invoiceData);
file_put_contents('invoice.xml', $xml);
```

## Next Steps

- [Belgium Implementation](belgium-implementation.md)
- [Netherlands Implementation](netherlands-implementation.md)
- [Validation & Compliance](validation.md)
