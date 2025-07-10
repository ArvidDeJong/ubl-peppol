# UBL/PEPPOL Service

Een PHP-bibliotheek voor het genereren van facturen volgens de UBL/PEPPOL-standaard. Deze package maakt het mogelijk om UBL 2.1 documenten te genereren die voldoen aan de PEPPOL BIS Billing 3.0 standaard voor e-facturatie.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darvis/ubl-peppol.svg?style=flat-square)](https://packagist.org/packages/darvis/ubl-peppol)
[![Total Downloads](https://img.shields.io/packagist/dt/darvis/ubl-peppol.svg?style=flat-square)](https://packagist.org/packages/darvis/ubl-peppol)

## Installatie

Je kunt deze package installeren via composer:

```bash
composer require darvis/ubl-peppol
```

## Laravel Installatie

Als je Laravel gebruikt, wordt de service provider automatisch geregistreerd via package discovery. Je kunt de UblService vervolgens gebruiken via dependency injection of via de facade:

```php
use Darvis\UblPeppol\UblService;

class InvoiceController extends Controller
{
    public function generate(UblService $ublService)
    {
        // Gebruik de UblService...
    }
}
```

Of via de app container:

```php
$ublService = app('ubl-peppol');
```

## Gebruik

Hieronder een voorbeeld van hoe je een UBL-factuur kunt genereren:

```php
use Darvis\UblPeppol\UblService;

// Maak een nieuwe UBL Service instantie
$ublService = new UblService();

// Stel de factuurgegevens in
$ublService->setId('INVOICE-2023-001');
$ublService->setIssueDate(new \DateTime());
$ublService->setDueDate(new \DateTime('+30 days'));

// Stel de verkoper in
$ublService->setSupplierName('Bedrijfsnaam Verkoper');
$ublService->setSupplierStreet('Voorbeeldstraat 123');
$ublService->setSupplierCity('Amsterdam');
$ublService->setSupplierPostcode('1234 AB');
$ublService->setSupplierCountry('NL');
$ublService->setSupplierVat('NL123456789B01');
$ublService->setSupplierChamber('12345678');

// Stel de koper in
$ublService->setCustomerName('Bedrijfsnaam Koper');
$ublService->setCustomerStreet('Koperstraat 456');
$ublService->setCustomerCity('Rotterdam');
$ublService->setCustomerPostcode('5678 CD');
$ublService->setCustomerCountry('NL');
$ublService->setCustomerVat('NL987654321B01');

// Voeg factuurregels toe
$ublService->addLine(
    '1',                  // Line ID
    'Product omschrijving', // Description
    2,                    // Quantity
    'EA',                 // Unit
    100.00,               // Price per unit (excl. btw)
    21.00,                // VAT percentage
    'S',                  // VAT category
    'Standaard tarief'    // VAT description
);

// Genereer het UBL XML-document
$ublXml = $ublService->getUbl();

// Je kunt het document nu opslaan of versturen
file_put_contents('invoice.xml', $ublXml);
```

Raadpleeg de voorbeelden in de `examples` directory voor meer gebruiksmogelijkheden.

## Features

- Genereren van UBL 2.1 facturen volgens de PEPPOL-standaard
- Ondersteuning voor verschillende BTW-tarieven
- Automatische berekening van totalen en BTW-bedragen
- Validatie van ingevoerde gegevens

## Vereisten

- PHP 8.2 of hoger
- DOM-extensie

## Bijdragen

Bijdragen zijn welkom! Voel je vrij om issues aan te maken of pull requests in te dienen.

## Licentie

Deze package is open-source software gelicenseerd onder de [MIT-licentie](LICENSE).
