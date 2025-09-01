# UBL PEPPOL Generator voor PHP

Een eenvoudig te gebruiken PHP-pakket voor het genereren van UBL XML-documenten die voldoen aan de PEPPOL BIS Billing 3.0 standaard, specifiek toegespitst op de Belgische implementatie (EN 16931).

## Doelstelling

Dit pakket stelt ontwikkelaars in staat om eenvoudig UBL-documenten te genereren die voldoen aan de PEPPOL-standaarden, zonder dat er uitgebreide kennis van de UBL-specificatie nodig is.

## Belangrijkste functionaliteiten

- **Ondersteunde documenten**:
  - Facturen
  - Creditnota's
  - Correctiefacturen
  - Factuurlijsten

- **Validatie**:
  - Controle op verplichte velden
  - Validatie van datums (formaat `YYYY-MM-DD`)
  - Controle op numerieke waarden en valuta-formaten
  - Validatie van BTW-nummers volgens Belgische regels

- **Kenmerken**:
  - Eenvoudige, intuïtieve API
  - Uitgebreide foutmeldingen
  - Ondersteuning voor meerdere valuta's
  - Flexibele toevoeging van extra UBL-elementen

## Snel aan de slag

1. Installeer het pakket via Composer:
   ```bash
   composer require darvis/ubl-peppol
   ```

2. Basisvoorbeeld:
   ```php
   use Darvis\UblPeppol\UblService;
   
   $ubl = new UblService();
   $xml = $ubl->createInvoice([
       'supplier' => [
           'name' => 'Leverancier BV',
           'vat_number' => 'BE0123456789',
           // ... andere vereiste velden
       ],
       // ... andere factuurgegevens
   ]);
   
   file_put_contents('factuur.xml', $xml);
   ```

## Documentatie

- `src/UblService.php` - Hoofdklasse voor het genereren van UBL-documenten
- `examples/` - Voorbeeldbestanden ter referentie
  - `base-example.xml` - Basis UBL-factuur
  - `creditnote-example.xml` - Voorbeeld creditnota
  - `allowance-example.xml` - Voorbeeld met kortingen

## Vereisten

- PHP 8.1 of hoger
- PHP-extensies: DOM, SimpleXML, XMLWriter

## Licentie

Dit project is gelicentieerd onder de MIT-licentie. Zie het [LICENSE](LICENSE) bestand voor meer informatie.

## Bijdragen

Bijdragen zijn welkom! Bij het indienen van pull requests, zorg ervoor dat je code voldoet aan de PSR-12 standaarden en vergeet niet om tests toe te voegen voor nieuwe functionaliteit.

## Ondersteuning

Voor vragen of problemen, open een issue in de GitHub repository of neem contact op met de onderhouders.