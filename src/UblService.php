<?php

namespace Darvis\UblPeppol;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;

/**
 * UBL Service voor het genereren van UBL/PEPPOL facturen
 * 
 * Deze versie is volledig herschreven om de exacte XML structuur van de PEPPOL-standaard
 * te volgen volgens het base-example.xml voorbeeld.
 */
class UblService
{
    protected DOMDocument $dom;
    protected DOMElement $rootElement;

    // Namespace URI's
    protected string $ns_cac_uri = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    protected string $ns_cbc_uri = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    protected string $ns_invoice_uri = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    // Namespace prefixes
    protected string $ns_prefix_cac = 'cac';
    protected string $ns_prefix_cbc = 'cbc';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Maak nieuw DOMDocument
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
    }

    /**
     * Creëer het basis XML document
     * 
     * @return self
     * @throws \RuntimeException bij dubbele initialisatie
     */
    public function createDocument(): self
    {
        // Voorkom dubbele initialisatie van het rootElement
        if (isset($this->rootElement)) {
            throw new \RuntimeException('Document is al geïnitialiseerd. Voorkom dubbele initialisatie van het document.');
        }

        // Opnieuw DOM document initialiseren voor het geval dat
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        // Voeg root element toe (Invoice)
        $this->rootElement = $this->dom->createElementNS($this->ns_invoice_uri, 'Invoice');
        $this->rootElement->setAttribute('xmlns:cac', $this->ns_cac_uri);
        $this->rootElement->setAttribute('xmlns:cbc', $this->ns_cbc_uri);
        $this->rootElement = $this->dom->appendChild($this->rootElement);

        return $this;
    }

    /**
     * Genereer de XML string
     * 
     * @return string
     */
    public function generateXml(): string
    {
        return $this->dom->saveXML();
    }

    /**
     * Helper methode voor het aanmaken van XML elementen zonder namespace herhaling
     * 
     * @param string $prefix Namespace prefix (cbc of cac)
     * @param string $name Element naam
     * @param string|null $value Element waarde
     * @param array $attributes Optionele attributen
     * @return \DOMElement
     * @throws \RuntimeException als het document niet is geïnitialiseerd
     */
    protected function createElement(string $prefix, string $name, ?string $value = null, array $attributes = []): \DOMElement
    {
        // Controleer of het DOM document bestaat
        if (!isset($this->dom)) {
            throw new \RuntimeException('DOM document is niet geïnitialiseerd. Roep createDocument() aan voordat je elementen toevoegt.');
        }

        // Controleer of het rootElement bestaat
        if (!isset($this->rootElement)) {
            throw new \RuntimeException('Root element is niet geïnitialiseerd. Roep createDocument() aan voordat je elementen toevoegt.');
        }

        // Maak element zonder namespace declaratie (gebruikt geërfde namespace)
        $element = $this->dom->createElement($prefix . ':' . $name);

        // Voeg waarde toe indien niet null
        if ($value !== null) {
            $textNode = $this->dom->createTextNode($value);
            $element->appendChild($textNode);
        }

        // Voeg attributen toe indien aanwezig
        foreach ($attributes as $attrName => $attrValue) {
            $element->setAttribute($attrName, $attrValue);
        }

        return $element;
    }

    /**
     * Maak een compleet UBL factuur document aan volgens het base-example.xml
     *
     * @return self
     * @throws \RuntimeException als er een fout optreedt bij de initialisatie
     */
    public function createExampleInvoice(): self
    {
        try {
            // Belangrijk: Initialiseer eerst het document en rootElement
            // Dit moet altijd als eerste gebeuren voordat andere methoden worden aangeroepen
            $this->createDocument();

            // Voeg nu alle onderdelen toe in de juiste volgorde volgens PEPPOL standaard
            $this->addInvoiceHeader();
            $this->addBuyerReference('0150abc'); // Conform base-example.xml
            // OrderReference zou hier moeten staan indien nodig
            $this->addAccountingSupplierParty();
            $this->addAccountingCustomerParty();
            $this->addDelivery();
            $this->addPaymentMeans();
            $this->addPaymentTerms();
            $this->addAllowanceCharge();
            $this->addTaxTotal();
            $this->addLegalMonetaryTotal();
            $this->addInvoiceLines();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Fout bij het aanmaken van UBL document: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * Voeg de invoice header toe
     *
     * @param string $invoiceNumber Factuurnummer
     * @param string|\DateTime $issueDate Factuurdatum (format: Y-m-d)
     * @param string|\DateTime $dueDate Vervaldatum (format: Y-m-d)
     * @return self
     */
    public function addInvoiceHeader(string $invoiceNumber = 'Snippet1', $issueDate = '2017-11-13', $dueDate = '2017-12-01'): self
    {
        // Converteer datums naar juiste format indien nodig
        if ($issueDate instanceof \DateTime) {
            $issueDate = $issueDate->format('Y-m-d');
        }

        if ($dueDate instanceof \DateTime) {
            $dueDate = $dueDate->format('Y-m-d');
        }

        // CustomizationID - PEPPOL profile
        $customizationIDElement = $this->createElement(
            'cbc',
            'CustomizationID',
            'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0'
        );
        $this->rootElement->appendChild($customizationIDElement);

        // ProfileID
        $profileIDElement = $this->createElement(
            'cbc',
            'ProfileID',
            'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0'
        );
        $this->rootElement->appendChild($profileIDElement);

        // ID (factuurnummer)
        $idElement = $this->createElement('cbc', 'ID', $invoiceNumber);
        $this->rootElement->appendChild($idElement);

        // IssueDate (factuurdatum)
        $issueDateElement = $this->createElement('cbc', 'IssueDate', $issueDate);
        $this->rootElement->appendChild($issueDateElement);

        // DueDate (vervaldatum) - controleer of deze niet leeg is
        // Als de waarde leeg is, gebruik dan een standaarddatum gebaseerd op issueDate + 30 dagen
        if (empty($dueDate)) {
            // Gebruik issueDate als basis en voeg 30 dagen toe als standaard betaaltermijn
            try {
                $issueDateObj = new \DateTime($issueDate);
                $dueDateObj = clone $issueDateObj;
                $dueDateObj->modify('+30 days');
                $dueDate = $dueDateObj->format('Y-m-d');
            } catch (\Exception $e) {
                // Als er iets fout gaat, gebruik de huidige datum + 30 dagen als fallback
                $dueDate = (new \DateTime())->modify('+30 days')->format('Y-m-d');
            }
        } else {
            // Controleer of het een geldige datum is in het formaat Y-m-d
            try {
                $dueDateObj = new \DateTime($dueDate);
                $dueDate = $dueDateObj->format('Y-m-d'); // Normaliseren naar YYYY-MM-DD
            } catch (\Exception $e) {
                // Als het geen geldige datum is, gebruik de huidige datum + 30 dagen als fallback
                $dueDate = (new \DateTime())->modify('+30 days')->format('Y-m-d');
            }
        }

        // Nu we zeker weten dat $dueDate een geldige datum is in het juiste formaat
        $dueDateElement = $this->createElement('cbc', 'DueDate', $dueDate);
        $this->rootElement->appendChild($dueDateElement);

        // InvoiceTypeCode
        $invoiceTypeCodeElement = $this->createElement('cbc', 'InvoiceTypeCode', '380');
        $this->rootElement->appendChild($invoiceTypeCodeElement);

        // DocumentCurrencyCode
        $documentCurrencyCodeElement = $this->createElement('cbc', 'DocumentCurrencyCode', 'EUR');
        $this->rootElement->appendChild($documentCurrencyCodeElement);

        // AccountingCost
        $accountingCostElement = $this->createElement('cbc', 'AccountingCost', '4025:123:4343');
        $this->rootElement->appendChild($accountingCostElement);

        // BuyerReference wordt nu apart toegevoegd via addBuyerReference() om dubbele elementen te voorkomen

        return $this;
    }

    /**
     * Format een bedrag voor gebruik in UBL
     * 
     * @param float $amount Bedrag
     * @return string Geformatteerd bedrag (2 decimalen)
     */
    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Voeg BuyerReference toe (verplicht voor PEPPOL)
     *
     * @param string|null $buyerRef Referentie van de koper (bijv. debiteurnummer)
     * @return self
     */
    public function addBuyerReference(?string $buyerRef = 'BUYER_REF'): self
    {
        // Fallback naar default waarde als null wordt doorgegeven
        $buyerRefValue = $buyerRef ?? 'BUYER_REF';

        $buyerRefElement = $this->createElement('cbc', 'BuyerReference', $buyerRefValue);
        $this->rootElement->appendChild($buyerRefElement);

        return $this;
    }

    /**
     * Voeg OrderReference toe aan UBL document
     * 
     * @param string $orderNumber Ordernummer referentie
     * @return self
     */
    public function addOrderReference(string $orderNumber = 'PO-001'): self
    {
        // OrderReference container
        $orderRefElement = $this->createElement('cac', 'OrderReference');
        $this->rootElement->appendChild($orderRefElement);

        // OrderReference ID
        $orderIdElement = $this->createElement('cbc', 'ID', $orderNumber);
        $orderRefElement->appendChild($orderIdElement);

        return $this;
    }

    /**
     * Voeg AccountingSupplierParty (verkooppartij) toe
     * 
     * @return self
     */
    public function addAccountingSupplierParty(): self
    {
        // AccountingSupplierParty container
        $accountingSupplierParty = $this->createElement('cac', 'AccountingSupplierParty');
        $accountingSupplierParty = $this->rootElement->appendChild($accountingSupplierParty);

        // Party container
        $party = $this->createElement('cac', 'Party');
        $party = $accountingSupplierParty->appendChild($party);

        // EndpointID
        $endpointIDElement = $this->createElement('cbc', 'EndpointID', '9482348239847239874', ['schemeID' => '0088']);
        $party->appendChild($endpointIDElement);

        // PartyIdentification
        $partyIdentification = $this->createElement('cac', 'PartyIdentification');
        $partyIdentification = $party->appendChild($partyIdentification);

        $idElement = $this->createElement('cbc', 'ID', '99887766');
        $partyIdentification->appendChild($idElement);

        // PartyName
        $partyName = $this->createElement('cac', 'PartyName');
        $partyName = $party->appendChild($partyName);

        $nameElement = $this->createElement('cbc', 'Name', 'SupplierTradingName Ltd.');
        $partyName->appendChild($nameElement);

        // PostalAddress
        $postalAddress = $this->createElement('cac', 'PostalAddress');
        $postalAddress = $party->appendChild($postalAddress);

        $streetNameElement = $this->createElement('cbc', 'StreetName', 'Main street 1');
        $postalAddress->appendChild($streetNameElement);

        $additionalStreetNameElement = $this->createElement('cbc', 'AdditionalStreetName', 'Postbox 123');
        $postalAddress->appendChild($additionalStreetNameElement);

        $cityNameElement = $this->createElement('cbc', 'CityName', 'London');
        $postalAddress->appendChild($cityNameElement);

        $postalZoneElement = $this->createElement('cbc', 'PostalZone', 'GB 123 EW');
        $postalAddress->appendChild($postalZoneElement);

        // Country - moet als laatste element binnen PostalAddress komen
        $country = $this->createElement('cac', 'Country');
        $country = $postalAddress->appendChild($country);

        $identificationCodeElement = $this->createElement('cbc', 'IdentificationCode', 'GB');
        $country->appendChild($identificationCodeElement);

        // PartyTaxScheme
        $partyTaxScheme = $this->createElement('cac', 'PartyTaxScheme');
        $partyTaxScheme = $party->appendChild($partyTaxScheme);

        $companyIDElement = $this->createElement('cbc', 'CompanyID', 'GB1232434');
        $partyTaxScheme->appendChild($companyIDElement);

        $taxScheme = $this->createElement('cac', 'TaxScheme');
        $taxScheme = $partyTaxScheme->appendChild($taxScheme);

        $taxSchemeIDElement = $this->createElement('cbc', 'ID', 'VAT');
        $taxScheme->appendChild($taxSchemeIDElement);

        // PartyLegalEntity
        $partyLegalEntity = $this->createElement('cac', 'PartyLegalEntity');
        $partyLegalEntity = $party->appendChild($partyLegalEntity);

        $registrationNameElement = $this->createElement('cbc', 'RegistrationName', 'SupplierOfficialName Ltd');
        $partyLegalEntity->appendChild($registrationNameElement);

        $companyIDElement = $this->createElement('cbc', 'CompanyID', 'GB983294');
        $partyLegalEntity->appendChild($companyIDElement);

        return $this;
    }

    /**
     * Voeg AccountingCustomerParty (klantpartij) toe
     * 
     * @return self
     */
    public function addAccountingCustomerParty(): self
    {
        // AccountingCustomerParty container
        $accountingCustomerParty = $this->createElement('cac', 'AccountingCustomerParty');
        $accountingCustomerParty = $this->rootElement->appendChild($accountingCustomerParty);

        // Party container
        $party = $this->createElement('cac', 'Party');
        $party = $accountingCustomerParty->appendChild($party);

        // EndpointID
        $endpointIDElement = $this->createElement('cbc', 'EndpointID', 'FR23342', ['schemeID' => '0002']);
        $party->appendChild($endpointIDElement);

        // PartyIdentification
        $partyIdentification = $this->createElement('cac', 'PartyIdentification');
        $partyIdentification = $party->appendChild($partyIdentification);

        $idElement = $this->createElement('cbc', 'ID', 'FR23342', ['schemeID' => '0002']);
        $partyIdentification->appendChild($idElement);

        // PartyName
        $partyName = $this->createElement('cac', 'PartyName');
        $partyName = $party->appendChild($partyName);

        $nameElement = $this->createElement('cbc', 'Name', 'BuyerTradingName AS');
        $partyName->appendChild($nameElement);

        // PostalAddress
        $postalAddress = $this->createElement('cac', 'PostalAddress');
        $postalAddress = $party->appendChild($postalAddress);

        $streetNameElement = $this->createElement('cbc', 'StreetName', 'Hovedgatan 32');
        $postalAddress->appendChild($streetNameElement);

        $additionalStreetNameElement = $this->createElement('cbc', 'AdditionalStreetName', 'Po box 878');
        $postalAddress->appendChild($additionalStreetNameElement);

        $cityNameElement = $this->createElement('cbc', 'CityName', 'Stockholm');
        $postalAddress->appendChild($cityNameElement);

        $postalZoneElement = $this->createElement('cbc', 'PostalZone', '456 34');
        $postalAddress->appendChild($postalZoneElement);

        // Country - moet als laatste element binnen PostalAddress komen
        $country = $this->createElement('cac', 'Country');
        $country = $postalAddress->appendChild($country);

        $identificationCodeElement = $this->createElement('cbc', 'IdentificationCode', 'SE');
        $country->appendChild($identificationCodeElement);

        // PartyTaxScheme
        $partyTaxScheme = $this->createElement('cac', 'PartyTaxScheme');
        $partyTaxScheme = $party->appendChild($partyTaxScheme);

        $companyIDElement = $this->createElement('cbc', 'CompanyID', 'SE1234567801');
        $partyTaxScheme->appendChild($companyIDElement);

        $taxScheme = $this->createElement('cac', 'TaxScheme');
        $taxScheme = $partyTaxScheme->appendChild($taxScheme);

        $taxSchemeIDElement = $this->createElement('cbc', 'ID', 'VAT');
        $taxScheme->appendChild($taxSchemeIDElement);

        // PartyLegalEntity
        $partyLegalEntity = $this->createElement('cac', 'PartyLegalEntity');
        $partyLegalEntity = $party->appendChild($partyLegalEntity);

        $registrationNameElement = $this->createElement('cbc', 'RegistrationName', 'Buyer Official Name');
        $partyLegalEntity->appendChild($registrationNameElement);

        $companyIDElement = $this->createElement('cbc', 'CompanyID', 'SE5567894321');
        $partyLegalEntity->appendChild($companyIDElement);

        // Contact
        $contact = $this->createElement('cac', 'Contact');
        $contact = $party->appendChild($contact);

        $nameElement = $this->createElement('cbc', 'Name', 'Lisa Johnson');
        $contact->appendChild($nameElement);

        $telephoneElement = $this->createElement('cbc', 'Telephone', '+46 12 34 56 78');
        $contact->appendChild($telephoneElement);

        $electronicMailElement = $this->createElement('cbc', 'ElectronicMail', 'lisa@buyer.se');
        $contact->appendChild($electronicMailElement);

        return $this;
    }

    /**
     * Voeg Delivery informatie toe
     * 
     * @return self
     */
    public function addDelivery(): self
    {
        // Delivery container
        $delivery = $this->createElement('cac', 'Delivery');
        $delivery = $this->rootElement->appendChild($delivery);

        // ActualDeliveryDate
        $actualDeliveryDateElement = $this->createElement('cbc', 'ActualDeliveryDate', '2017-11-01');
        $delivery->appendChild($actualDeliveryDateElement);

        // DeliveryLocation
        $deliveryLocation = $this->createElement('cac', 'DeliveryLocation');
        $deliveryLocation = $delivery->appendChild($deliveryLocation);

        $idElement = $this->createElement('cbc', 'ID', '9483759475923478', ['schemeID' => '0088']);
        $deliveryLocation->appendChild($idElement);

        // Address within DeliveryLocation
        $address = $this->createElement('cac', 'Address');
        $address = $deliveryLocation->appendChild($address);

        $streetNameElement = $this->createElement('cbc', 'StreetName', 'Delivery street 2');
        $address->appendChild($streetNameElement);

        $additionalStreetNameElement = $this->createElement('cbc', 'AdditionalStreetName', 'Building 56');
        $address->appendChild($additionalStreetNameElement);

        $cityNameElement = $this->createElement('cbc', 'CityName', 'Stockholm');
        $address->appendChild($cityNameElement);

        $postalZoneElement = $this->createElement('cbc', 'PostalZone', '21234');
        $address->appendChild($postalZoneElement);

        $country = $this->createElement('cac', 'Country');
        $country = $address->appendChild($country);

        $identificationCodeElement = $this->createElement('cbc', 'IdentificationCode', 'SE');
        $country->appendChild($identificationCodeElement);

        // DeliveryParty
        $deliveryParty = $this->createElement('cac', 'DeliveryParty');
        $deliveryParty = $delivery->appendChild($deliveryParty);

        $partyName = $this->createElement('cac', 'PartyName');
        $partyName = $deliveryParty->appendChild($partyName);

        $nameElement = $this->createElement('cbc', 'Name', 'Delivery party Name');
        $partyName->appendChild($nameElement);

        return $this;
    }

    /**
     * Voeg PaymentMeans (betalingsgegevens) toe
     *
     * @param string|null $paymentType
     * @return self
     */
    public function addPaymentMeans(?string $paymentType = '30'): self
    {
        // PaymentMeans container
        $paymentMeans = $this->createElement('cac', 'PaymentMeans');
        $paymentMeans = $this->rootElement->appendChild($paymentMeans);

        // PaymentMeansCode
        $paymentMeansCodeElement = $this->createElement('cbc', 'PaymentMeansCode', $paymentType, ['name' => 'Credit transfer']);
        $paymentMeans->appendChild($paymentMeansCodeElement);

        // PaymentID
        $paymentIDElement = $this->createElement('cbc', 'PaymentID', 'Snippet1');
        $paymentMeans->appendChild($paymentIDElement);

        // PayeeFinancialAccount
        $payeeFinancialAccount = $this->createElement('cac', 'PayeeFinancialAccount');
        $payeeFinancialAccount = $paymentMeans->appendChild($payeeFinancialAccount);

        $idElement = $this->createElement('cbc', 'ID', 'IBAN32423940');
        $payeeFinancialAccount->appendChild($idElement);

        $nameElement = $this->createElement('cbc', 'Name', 'AccountName');
        $payeeFinancialAccount->appendChild($nameElement);

        $financialInstitutionBranch = $this->createElement('cac', 'FinancialInstitutionBranch');
        $financialInstitutionBranch = $payeeFinancialAccount->appendChild($financialInstitutionBranch);

        $idElement = $this->createElement('cbc', 'ID', 'BIC324098');
        $financialInstitutionBranch->appendChild($idElement);

        return $this;
    }

    /**
     * Voeg PaymentTerms (betalingsvoorwaarden) toe
     * 
     * @return self
     */
    public function addPaymentTerms(): self
    {
        // PaymentTerms container
        $paymentTerms = $this->createElement('cac', 'PaymentTerms');
        $paymentTerms = $this->rootElement->appendChild($paymentTerms);

        // Note
        $noteElement = $this->createElement('cbc', 'Note', 'Payment within 10 days, 2% discount');
        $paymentTerms->appendChild($noteElement);

        return $this;
    }

    /**
     * Voeg AllowanceCharge (toeslag/korting) toe
     * 
     * @return self
     */
    public function addAllowanceCharge(): self
    {
        // AllowanceCharge container
        $allowanceCharge = $this->createElement('cac', 'AllowanceCharge');
        $allowanceCharge = $this->rootElement->appendChild($allowanceCharge);

        // ChargeIndicator
        $chargeIndicatorElement = $this->createElement('cbc', 'ChargeIndicator', 'true');
        $allowanceCharge->appendChild($chargeIndicatorElement);

        // AllowanceChargeReason
        $allowanceChargeReasonElement = $this->createElement('cbc', 'AllowanceChargeReason', 'Insurance');
        $allowanceCharge->appendChild($allowanceChargeReasonElement);

        // Amount
        $amountElement = $this->createElement('cbc', 'Amount', '25', ['currencyID' => 'EUR']);
        $allowanceCharge->appendChild($amountElement);

        // TaxCategory
        $taxCategory = $this->createElement('cac', 'TaxCategory');
        $taxCategory = $allowanceCharge->appendChild($taxCategory);

        $idElement = $this->createElement('cbc', 'ID', 'S');
        $taxCategory->appendChild($idElement);

        $percentElement = $this->createElement('cbc', 'Percent', '25.0');
        $taxCategory->appendChild($percentElement);

        $taxScheme = $this->createElement('cac', 'TaxScheme');
        $taxScheme = $taxCategory->appendChild($taxScheme);

        $taxSchemeIDElement = $this->createElement('cbc', 'ID', 'VAT');
        $taxScheme->appendChild($taxSchemeIDElement);

        return $this;
    }

    /**
     * Voeg TaxTotal (BTW totalen) toe
     * 
     * @return self
     */
    public function addTaxTotal(): self
    {
        // TaxTotal container
        $taxTotal = $this->createElement('cac', 'TaxTotal');
        $taxTotal = $this->rootElement->appendChild($taxTotal);

        // TaxAmount (totaal BTW bedrag)
        $taxAmountElement = $this->createElement('cbc', 'TaxAmount', '331.25', ['currencyID' => 'EUR']);
        $taxTotal->appendChild($taxAmountElement);

        // TaxSubtotal
        $taxSubtotal = $this->createElement('cac', 'TaxSubtotal');
        $taxSubtotal = $taxTotal->appendChild($taxSubtotal);

        $taxableAmountElement = $this->createElement('cbc', 'TaxableAmount', '1325', ['currencyID' => 'EUR']);
        $taxSubtotal->appendChild($taxableAmountElement);

        $taxAmountElement = $this->createElement('cbc', 'TaxAmount', '331.25', ['currencyID' => 'EUR']);
        $taxSubtotal->appendChild($taxAmountElement);

        $taxCategory = $this->createElement('cac', 'TaxCategory');
        $taxCategory = $taxSubtotal->appendChild($taxCategory);

        $idElement = $this->createElement('cbc', 'ID', 'S');
        $taxCategory->appendChild($idElement);

        $percentElement = $this->createElement('cbc', 'Percent', '25.0');
        $taxCategory->appendChild($percentElement);

        $taxScheme = $this->createElement('cac', 'TaxScheme');
        $taxScheme = $taxCategory->appendChild($taxScheme);

        $taxSchemeIDElement = $this->createElement('cbc', 'ID', 'VAT');
        $taxScheme->appendChild($taxSchemeIDElement);

        return $this;
    }

    /**
     * Voeg LegalMonetaryTotal (bedragentotalen) toe
     * 
     * @return self
     */
    public function addLegalMonetaryTotal(): self
    {
        // LegalMonetaryTotal container
        $legalMonetaryTotal = $this->createElement('cac', 'LegalMonetaryTotal');
        $legalMonetaryTotal = $this->rootElement->appendChild($legalMonetaryTotal);

        // LineExtensionAmount (som van alle factuurregels ex BTW)
        $lineExtensionAmountElement = $this->createElement('cbc', 'LineExtensionAmount', '1300', ['currencyID' => 'EUR']);
        $legalMonetaryTotal->appendChild($lineExtensionAmountElement);

        // TaxExclusiveAmount (bedrag exclusief BTW)
        $taxExclusiveAmountElement = $this->createElement('cbc', 'TaxExclusiveAmount', '1325', ['currencyID' => 'EUR']);
        $legalMonetaryTotal->appendChild($taxExclusiveAmountElement);

        // TaxInclusiveAmount (bedrag inclusief BTW)
        $taxInclusiveAmountElement = $this->createElement('cbc', 'TaxInclusiveAmount', '1656.25', ['currencyID' => 'EUR']);
        $legalMonetaryTotal->appendChild($taxInclusiveAmountElement);

        // ChargeTotalAmount (totaal toeslagen)
        $chargeTotalAmountElement = $this->createElement('cbc', 'ChargeTotalAmount', '25', ['currencyID' => 'EUR']);
        $legalMonetaryTotal->appendChild($chargeTotalAmountElement);

        // PayableAmount (te betalen bedrag)
        $payableAmountElement = $this->createElement('cbc', 'PayableAmount', '1656.25', ['currencyID' => 'EUR']);
        $legalMonetaryTotal->appendChild($payableAmountElement);

        return $this;
    }

    /**
     * Voeg InvoiceLines (factuurregels) toe
     * 
     * @return self
     */
    public function addInvoiceLines(): self
    {
        // Eerste factuurregel
        $this->addInvoiceLine(
            '1',
            '7',
            'DAY',
            '2800',
            'Description of item',
            'item name',
            '400',
            'Konteringsstreng',
            '123',
            '21382183120983',
            'NO',
            'S',
            '25.0'
        );

        // Tweede factuurregel (negatief bedrag)
        $this->addInvoiceLine(
            '2',
            '-3',
            'DAY',
            '-1500',
            'Description 2',
            'item name 2',
            '500',
            null,
            '123',
            '21382183120983',
            'NO',
            'S',
            '25.0'
        );

        return $this;
    }

    /**
     * Voeg een enkele InvoiceLine (factuurregel) toe
     * 
     * @param string $id Factuurregel ID
     * @param string $quantity Hoeveelheid
     * @param string $unitCode Eenheid (bijv. 'DAY', 'PCS')
     * @param string $lineExtensionAmount Regelbedrag exclusief BTW
     * @param string $description Omschrijving
     * @param string $name Naam
     * @param string $priceAmount Prijs per eenheid
     * @param string|null $accountingCost Kostenplaats (optioneel)
     * @param string|null $orderLineId Order regel ID (optioneel)
     * @param string|null $standardItemId Item ID (optioneel)
     * @param string|null $originCountry Land van herkomst (optioneel)
     * @param string $taxCategoryId BTW categorie ID (S=standaard)
     * @param string $taxPercent BTW percentage
     * @return self
     */
    protected function addInvoiceLine(
        string $id,
        string $quantity,
        string $unitCode,
        string $lineExtensionAmount,
        string $description,
        string $name,
        string $priceAmount,
        ?string $accountingCost = null,
        ?string $orderLineId = null,
        ?string $standardItemId = null,
        ?string $originCountry = null,
        string $taxCategoryId = 'S',
        string $taxPercent = '25.0'
    ): self {
        // InvoiceLine container
        $invoiceLine = $this->createElement('cac', 'InvoiceLine');
        $invoiceLine = $this->rootElement->appendChild($invoiceLine);

        // ID
        $idElement = $this->createElement('cbc', 'ID', $id);
        $invoiceLine->appendChild($idElement);

        // InvoicedQuantity
        $invoicedQuantityElement = $this->createElement('cbc', 'InvoicedQuantity', $quantity, ['unitCode' => $unitCode]);
        $invoiceLine->appendChild($invoicedQuantityElement);

        // LineExtensionAmount
        $lineExtensionAmountElement = $this->createElement('cbc', 'LineExtensionAmount', $lineExtensionAmount, ['currencyID' => 'EUR']);
        $invoiceLine->appendChild($lineExtensionAmountElement);

        // AccountingCost (optioneel)
        if ($accountingCost) {
            $accountingCostElement = $this->createElement('cbc', 'AccountingCost', $accountingCost);
            $invoiceLine->appendChild($accountingCostElement);
        }

        // OrderLineReference (optioneel)
        if ($orderLineId) {
            $orderLineReference = $this->createElement('cac', 'OrderLineReference');
            $orderLineReference = $invoiceLine->appendChild($orderLineReference);

            $lineIdElement = $this->createElement('cbc', 'LineID', $orderLineId);
            $orderLineReference->appendChild($lineIdElement);
        }

        // Item
        $item = $this->createElement('cac', 'Item');
        $item = $invoiceLine->appendChild($item);

        $descriptionElement = $this->createElement('cbc', 'Description', $description);
        $item->appendChild($descriptionElement);

        $nameElement = $this->createElement('cbc', 'Name', $name);
        $item->appendChild($nameElement);

        // StandardItemIdentification (optioneel)
        if ($standardItemId) {
            $standardItemIdentification = $this->createElement('cac', 'StandardItemIdentification');
            $standardItemIdentification = $item->appendChild($standardItemIdentification);

            $idElement = $this->createElement('cbc', 'ID', $standardItemId, ['schemeID' => '0088']);
            $standardItemIdentification->appendChild($idElement);
        }

        // OriginCountry (optioneel)
        if ($originCountry) {
            $originCountryElement = $this->createElement('cac', 'OriginCountry');
            $originCountryElement = $item->appendChild($originCountryElement);

            $identificationCodeElement = $this->createElement('cbc', 'IdentificationCode', $originCountry);
            $originCountryElement->appendChild($identificationCodeElement);
        }

        // CommodityClassification
        $commodityClassification = $this->createElement('cac', 'CommodityClassification');
        $commodityClassification = $item->appendChild($commodityClassification);

        $itemClassificationCodeElement = $this->createElement('cbc', 'ItemClassificationCode', '09348023', ['listID' => 'SRV']);
        $commodityClassification->appendChild($itemClassificationCodeElement);

        // ClassifiedTaxCategory
        $classifiedTaxCategory = $this->createElement('cac', 'ClassifiedTaxCategory');
        $classifiedTaxCategory = $item->appendChild($classifiedTaxCategory);

        $idElement = $this->createElement('cbc', 'ID', $taxCategoryId);
        $classifiedTaxCategory->appendChild($idElement);

        $percentElement = $this->createElement('cbc', 'Percent', $taxPercent);
        $classifiedTaxCategory->appendChild($percentElement);

        $taxScheme = $this->createElement('cac', 'TaxScheme');
        $taxScheme = $classifiedTaxCategory->appendChild($taxScheme);

        $taxSchemeIdElement = $this->createElement('cbc', 'ID', 'VAT');
        $taxScheme->appendChild($taxSchemeIdElement);

        // Price
        $price = $this->createElement('cac', 'Price');
        $price = $invoiceLine->appendChild($price);

        $priceAmountElement = $this->createElement('cbc', 'PriceAmount', $priceAmount, ['currencyID' => 'EUR']);
        $price->appendChild($priceAmountElement);

        return $this;
    }
}
