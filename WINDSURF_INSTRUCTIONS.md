# Windsurf AI Instructies voor UBL-PEPPOL Pakket

## Pakket Overzicht
Dit is het `darvis/ubl-peppol` pakket - een PHP library voor het genereren van UBL/PEPPOL facturen die voldoen aan de Europese e-facturatie standaarden. Het pakket ondersteunt specifiek de Belgische (EN 16931) en Nederlandse implementaties.

## Installatie in een ander project

### Via Composer
```bash
composer require darvis/ubl-peppol
```

### Laravel Integratie
Het pakket registreert automatisch een service provider via package discovery. Gebruik dependency injection:

```php
use Darvis\UblPeppol\UblBeBis3Service;
use Darvis\UblPeppol\UblNlBis3Service;

class InvoiceController extends Controller
{
    public function generateBelgianInvoice(UblBeBis3Service $ublService)
    {
        // Belgische factuur genereren
    }
    
    public function generateDutchInvoice(UblNlBis3Service $ublService)
    {
        // Nederlandse factuur genereren
    }
}
```

## Beschikbare Services

### 1. UblBeBis3Service (België)
- Voor Belgische UBL facturen
- Volledig EN 16931 compliant
- Ondersteunt alle Belgische Schematron regels (ubl-BE-01, ubl-BE-10, ubl-BE-14)
- Correcte BTCC waarden ("Taux standard", "Taux zéro")

### 2. UblNlBis3Service (Nederland)
- Voor Nederlandse UBL facturen
- Ondersteunt KVK nummers met automatische schemeID
- PEPPOL Nederland validator getest

## Basis Gebruik

### Stap 1: Data Structuur Voorbereiden
```php
$invoiceData = [
    'invoice_number' => 'INV-2024-001',
    'invoice_date' => '2024-01-15',
    'due_date' => '2024-02-14',
    'currency' => 'EUR',
    
    // Leverancier informatie
    'supplier' => [
        'name' => 'Bedrijfsnaam',
        'vat_number' => 'BE0123456789',
        'address' => [
            'street' => 'Straatnaam 123',
            'city' => 'Brussel',
            'postal_code' => '1000',
            'country' => 'BE'
        ]
    ],
    
    // Klant informatie
    'customer' => [
        'name' => 'Klantnaam',
        'vat_number' => 'BE0987654321',
        'address' => [
            'street' => 'Klantstraat 456',
            'city' => 'Antwerpen',
            'postal_code' => '2000',
            'country' => 'BE'
        ]
    ],
    
    // Factuurregels
    'invoice_lines' => [
        [
            'id' => '1',
            'quantity' => 2,
            'unit_price' => 100.00,
            'vat_rate' => 21,
            'description' => 'Product beschrijving',
            'unit_code' => 'C62' // Stuks
        ]
    ]
];
```

### Stap 2: UBL XML Genereren
```php
use Darvis\UblPeppol\UblBeBis3Service;

// Service instantiëren
$ublService = new UblBeBis3Service();

// UBL XML genereren
$ublXml = $ublService->generateInvoice($invoiceData);

// Opslaan als bestand
file_put_contents('factuur.xml', $ublXml);

// Of direct downloaden
header('Content-Type: application/xml');
header('Content-Disposition: attachment; filename="factuur.xml"');
echo $ublXml;
```

## Voorbeelden Gebruiken

Het pakket bevat complete werkende voorbeelden in de `examples/` directory:

### Belgisch Voorbeeld
Via browser (start een lokale server):
```bash
php -S localhost:8000 -t vendor/darvis/ubl-peppol/examples/
```
Ga naar: `http://localhost:8000/be/generate_invoice_be.php`
Download XML: `http://localhost:8000/be/generate_invoice_be.php?download`

### Nederlands Voorbeeld  
Via browser (start een lokale server):
```bash
php -S localhost:8000 -t vendor/darvis/ubl-peppol/examples/
```
Ga naar: `http://localhost:8000/nl/generate_invoice_nl.php`
Download XML: `http://localhost:8000/nl/generate_invoice_nl.php?download`

### Test Data Structuur
Bekijk `vendor/darvis/ubl-peppol/examples/test_data.php` voor een complete data structuur met alle vereiste velden.

## Validatie

Het pakket is gevalideerd tegen:
- **Nederland**: https://test.peppolautoriteit.nl/validate
- **België**: https://ecosio.com/en/peppol-and-xml-document-validator/
- **Italië (Algemeen PEPPOL)**: https://peppol-docs.agid.gov.it/docs/validator/

## Belangrijke Opmerkingen voor AI

### Belgische Specificaties (uit memory)
- ubl-BE-01: Tweede AdditionalDocumentReference vereist (PEPPOL met type PEPPOLInvoice)
- ubl-BE-10: TaxCategory Name moet "Taux standard" zijn (niet "Standaardtarief")
- ubl-BE-14: TaxTotal element vereist in InvoiceLine
- Correcte BTCC waarden gebruiken voor Belgische belastingcategorieën

### Data Validatie
- Datums in `YYYY-MM-DD` formaat
- BTW nummers volgens landspecifieke regels
- Valuta codes volgens ISO 4217
- Unit codes volgens UN/ECE Recommendation 20

### Foutafhandeling
```php
try {
    $ublXml = $ublService->generateInvoice($invoiceData);
} catch (\InvalidArgumentException $e) {
    // Validatie fout in input data
    echo "Data validatie fout: " . $e->getMessage();
} catch (\Exception $e) {
    // Algemene fout
    echo "Fout bij genereren: " . $e->getMessage();
}
```

## Vereisten
- PHP 8.2 of hoger
- DOM extensie
- Voor Laravel: automatische service provider registratie

## Ondersteuning
- GitHub: https://github.com/ArvidDeJong/ubl-peppol
- Issues: https://github.com/ArvidDeJong/ubl-peppol/issues
- Documentatie: https://docs.peppol.eu/poacc/billing/3.0/

## Tips voor Windsurf AI
1. Gebruik altijd de juiste service class voor het land (UblBeBis3Service voor België, UblNlBis3Service voor Nederland)
2. Controleer de voorbeelden in de `examples/` directory voor complete implementaties
3. Valideer gegenereerde XML tegen de officiële PEPPOL validators
4. Let op landspecifieke vereisten zoals BTW nummer formaten en belastingcategorieën
5. Gebruik de test data structuur als basis voor nieuwe implementaties
