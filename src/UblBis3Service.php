<?php

namespace Darvis\UblPeppol;

use DOMDocument;
use DOMElement;
use Darvis\UblPeppol\Validation\UblValidator;

/**
 * UBL Service for generating UBL/PEPPOL invoices
 * 
 * This version has been completely rewritten to follow the exact XML structure 
 * of the PEPPOL standard according to the base-example.xml reference.
 */
class UblBis3Service
{
    /**
     * @var DOMDocument The main XML document instance
     */
    protected DOMDocument $dom;

    /**
     * @var DOMElement The root element of the UBL document
     */
    protected DOMElement $rootElement;

    // Namespace URIs
    protected string $ns_cac_uri = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    protected string $ns_cbc_uri = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    protected string $ns_invoice_uri = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    // Namespace prefixes
    protected string $ns_prefix_cac = 'cac';
    protected string $ns_prefix_cbc = 'cbc';

    /**
     * Constructor - Initializes a new UBL document
     */
    public function __construct()
    {
        // Create new DOMDocument with UTF-8 encoding
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
    }

    /**
     * Create the base XML document structure
     * 
     * @return self
     * @throws \RuntimeException When document is already initialized
     */
    public function createDocument(): self
    {
        // Prevent double initialization of the document
        if (isset($this->rootElement)) {
            throw new \RuntimeException('Document is already initialized. Avoid initializing the document multiple times.');
        }

        // Create root element (Invoice)
        $this->rootElement = $this->dom->createElementNS($this->ns_invoice_uri, 'Invoice');
        $this->rootElement->setAttribute('xmlns:cac', $this->ns_cac_uri);
        $this->rootElement->setAttribute('xmlns:cbc', $this->ns_cbc_uri);
        $this->rootElement->setAttribute('xmlns', $this->ns_invoice_uri);

        // Add root element to the document
        $this->dom->appendChild($this->rootElement);

        return $this;
    }

    /**
     * Generate the XML string
     * 
     * @return string The generated XML as a string
     * @throws \RuntimeException If the document is not initialized
     */
    public function generateXml(): string
    {
        return $this->dom->saveXML();
    }

    /**
     * Helper method to create and append a child element
     * 
     * @param \DOMElement $parent The parent element
     * @param string $prefix The namespace prefix (e.g., 'cbc' or 'cac')
     * @param string $name The element name
     * @param string|null $value The element value (optional)
     * @param array $attributes Associative array of attributes (optional)
     * @return \DOMElement The created and appended element
     */
    protected function addChildElement(\DOMElement $parent, string $prefix, string $name, ?string $value = null, array $attributes = []): \DOMElement
    {
        $element = $this->createElement($prefix, $name, $value, $attributes);
        $parent->appendChild($element);
        return $element;
    }

    /**
     * Create an XML element with the given prefix, name, value, and attributes
     *
     * @param string $prefix The namespace prefix (e.g., 'cbc' or 'cac')
     * @param string $name The element name
     * @param string|null $value The element value (optional)
     * @param array $attributes Associative array of attributes (optional)
     * @return \DOMElement The created DOMElement
     * @throws \RuntimeException If the document is not initialized
     */
    protected function createElement(string $prefix, string $name, ?string $value = null, array $attributes = []): \DOMElement
    {
        // Check if the DOM document exists
        if (!isset($this->dom)) {
            throw new \RuntimeException('DOM document is not initialized. Call createDocument() before adding elements.');
        }

        // Check if the rootElement exists
        if (!isset($this->rootElement)) {
            throw new \RuntimeException('Root element is not initialized. Call createDocument() before adding elements.');
        }

        // Create element without namespace declaration (uses inherited namespace)
        $element = $this->dom->createElement($prefix . ':' . $name);

        // Add value if not null
        if ($value !== null) {
            $textNode = $this->dom->createTextNode($value);
            $element->appendChild($textNode);
        }

        // Add attributes if present
        foreach ($attributes as $attrName => $attrValue) {
            $element->setAttribute($attrName, $attrValue);
        }

        return $element;
    }

    /**
     * Add the invoice header
     *
     * @param string $invoiceNumber Invoice number (required, cannot be empty)
     * @param string|\DateTime $issueDate Invoice date (required, format: YYYY-MM-DD)
     * @param string|\DateTime $dueDate Due date (required, must be after invoice date)
     * @return self
     * @throws \InvalidArgumentException On invalid input
     */
    public function addInvoiceHeader(string $invoiceNumber, $issueDate, $dueDate): self
    {
        $errors = [];

        // Validate invoice number
        $invoiceNumber = trim($invoiceNumber);
        if (empty($invoiceNumber)) {
            $errors[] = 'Invoice number is required and cannot be empty';
        } elseif (strlen($invoiceNumber) > 35) {
            $errors[] = 'Invoice number cannot exceed 35 characters';
        }

        // Valideer en converteer factuurdatum
        $issueDateObj = null;
        if ($issueDate instanceof \DateTime) {
            $issueDateObj = $issueDate;
            $issueDate = $issueDate->format('Y-m-d');
        } elseif (is_string($issueDate)) {
            $issueDate = trim($issueDate);
            $issueDateObj = \DateTime::createFromFormat('Y-m-d', $issueDate);

            if (!$issueDateObj || $issueDateObj->format('Y-m-d') !== $issueDate) {
                $errors[] = 'Invalid invoice date. Please use YYYY-MM-DD format';
            } else {
                // Check if the date is in the past or today
                $today = new \DateTime('today');
                if ($issueDateObj > $today) {
                    $errors[] = 'Invoice date cannot be in the future';
                }
            }
        } else {
            $errors[] = 'Invoice date must be a string (YYYY-MM-DD) or DateTime object';
        }

        // Valideer en converteer vervaldatum
        $dueDateObj = null;
        if ($dueDate instanceof \DateTime) {
            $dueDateObj = $dueDate;
            $dueDate = $dueDate->format('Y-m-d');
        } elseif (is_string($dueDate)) {
            $dueDate = trim($dueDate);
            $dueDateObj = \DateTime::createFromFormat('Y-m-d', $dueDate);

            if (!$dueDateObj || $dueDateObj->format('Y-m-d') !== $dueDate) {
                $errors[] = 'Invalid due date. Please use YYYY-MM-DD format';
            } elseif (isset($issueDateObj) && $dueDateObj <= $issueDateObj) {
                $errors[] = 'Due date must be after the invoice date';
            }
        } else {
            $errors[] = 'Due date must be a string (YYYY-MM-DD) or DateTime object';
        }

        // Gooi een uitzondering met alle validatiefouten
        if (!empty($errors)) {
            $errorMessage = "Validation error(s) in invoice header:\n" .
                implode("\n- ", array_merge([''], $errors));
            throw new \InvalidArgumentException($errorMessage);
        }

        // Check if due date is after invoice date
        if ($dueDateObj <= $issueDateObj) {
            throw new \InvalidArgumentException('Due date must be after the invoice date');
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
     * @param string $endpointId Unieke identificatie van de leverancier (bijv. KVK nummer)
     * @param string $endpointSchemeID Het schema van de endpoint ID (bijv. '0106' voor KVK)
     * @param string $partyId Interne identificatie van de partij
     * @param string $partyName Naam van de leverancier
     * @param string $street Straatnaam en huisnummer
     * @param string $postalCode Postcode
     * @param string $city Plaatsnaam
     * @param string $countryCode Landcode (2 letters, bijv. 'NL')
     * @param string $companyId BTW-nummer of ander fiscaal identificatienummer
     * @param string|null $additionalStreet Toevoeging adres (optioneel)
     * @return self
     * @throws \InvalidArgumentException Bij ongeldige invoer
     */
    public function addAccountingSupplierParty(
        string $endpointId,
        string $endpointSchemeID,
        string $partyId,
        string $partyName,
        string $street,
        string $postalCode,
        string $city,
        string $countryCode,
        string $companyId,
        ?string $additionalStreet = null
    ): self {
        // AccountingSupplierParty container
        $accountingSupplierParty = $this->createElement('cac', 'AccountingSupplierParty');
        $accountingSupplierParty = $this->rootElement->appendChild($accountingSupplierParty);

        // Party container
        $party = $this->createElement('cac', 'Party');
        $party = $accountingSupplierParty->appendChild($party);

        // Valideer invoer
        $errors = [];

        // Verzamel alle validatiefouten
        if (empty(trim($endpointId ?? ''))) {
            $errors[] = 'Endpoint ID (bijv. KVK-nummer) is verplicht';
        }
        if (empty(trim($endpointSchemeID ?? ''))) {
            $errors[] = 'Endpoint Scheme ID (bijv. "0106" voor KVK) is verplicht';
        }
        if (empty(trim($partyId ?? ''))) {
            $errors[] = 'Interne partij ID is verplicht';
        }
        if (empty(trim($partyName ?? ''))) {
            $errors[] = 'Bedrijfsnaam is verplicht';
        }
        if (empty(trim($street ?? ''))) {
            $errors[] = 'Straat en huisnummer zijn verplicht';
        }
        if (empty(trim($postalCode ?? ''))) {
            $errors[] = 'Postcode is verplicht';
        }
        if (empty(trim($city ?? ''))) {
            $errors[] = 'Plaatsnaam is verplicht';
        }
        if (empty(trim($countryCode ?? ''))) {
            $errors[] = 'Landcode is verplicht';
        } elseif (strlen(trim($countryCode)) !== 2) {
            $errors[] = 'Landcode moet uit precies 2 tekens bestaan (bijv. "NL")';
        }
        if (empty(trim($companyId ?? ''))) {
            $errors[] = 'BTW-nummer of fiscaal identificatienummer is verplicht';
        }

        // Gooi een uitzondering met alle validatiefouten
        if (!empty($errors)) {
            $errorMessage = "Validatiefout(en) in addAccountingSupplierParty():\n" .
                implode("\n- ", array_merge([''], $errors));
            throw new \InvalidArgumentException($errorMessage);
        }

        // EndpointID
        $endpointIDElement = $this->createElement('cbc', 'EndpointID', $endpointId, ['schemeID' => $endpointSchemeID]);
        $party->appendChild($endpointIDElement);

        // PartyIdentification
        $partyIdentification = $this->createElement('cac', 'PartyIdentification');
        $partyIdentification = $party->appendChild($partyIdentification);

        $idElement = $this->createElement('cbc', 'ID', $partyId);
        $partyIdentification->appendChild($idElement);

        // PartyName
        $partyNameElement = $this->createElement('cac', 'PartyName');
        $partyNameElement = $party->appendChild($partyNameElement);

        $nameElement = $this->createElement('cbc', 'Name', $partyName);
        $partyNameElement->appendChild($nameElement);

        // PostalAddress
        $postalAddress = $this->createElement('cac', 'PostalAddress');
        $postalAddress = $party->appendChild($postalAddress);

        $streetNameElement = $this->createElement('cbc', 'StreetName', $street);
        $postalAddress->appendChild($streetNameElement);

        if ($additionalStreet !== null) {
            $additionalStreetNameElement = $this->createElement('cbc', 'AdditionalStreetName', $additionalStreet);
            $postalAddress->appendChild($additionalStreetNameElement);
        }

        $cityNameElement = $this->createElement('cbc', 'CityName', $city);
        $postalAddress->appendChild($cityNameElement);

        $postalZoneElement = $this->createElement('cbc', 'PostalZone', $postalCode);
        $postalAddress->appendChild($postalZoneElement);

        // Country - moet als laatste element binnen PostalAddress komen
        $country = $this->createElement('cac', 'Country');
        $country = $postalAddress->appendChild($country);

        $identificationCodeElement = $this->createElement('cbc', 'IdentificationCode', strtoupper($countryCode));
        $country->appendChild($identificationCodeElement);

        // PartyTaxScheme
        $partyTaxScheme = $this->createElement('cac', 'PartyTaxScheme');
        $partyTaxScheme = $party->appendChild($partyTaxScheme);

        $companyIDElement = $this->createElement('cbc', 'CompanyID', $companyId);
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

        // Add CompanyID with schemeID for Dutch legal entity identifier (KVK)
        $companyIDElement = $this->createElement('cbc', 'CompanyID', $companyId, ['schemeID' => '0106']);
        $partyLegalEntity->appendChild($companyIDElement);

        return $this;
    }

    /**
     * Add AccountingCustomerParty (customer information)
     * 
     * @param string $endpointId Customer's unique identifier (e.g., VAT number)
     * @param string $endpointSchemeID Scheme of the endpoint ID (e.g., '0002' for GLN)
     * @param string $partyId Internal party ID
     * @param string $partyName Customer's company name
     * @param string $street Street name and number
     * @param string $postalCode Postal code
     * @param string $city City name
     * @param string $countryCode Country code (2 letters, e.g., 'NL')
     * @param string|null $additionalStreet Additional address line (optional)
     * @param string|null $companyId Company registration number (optional)
     * @return self
     * @throws \InvalidArgumentException On invalid input
     */
    public function addAccountingCustomerParty(
        string $endpointId,
        string $endpointSchemeID,
        string $partyId,
        string $partyName,
        string $street,
        string $postalCode,
        string $city,
        string $countryCode,
        ?string $additionalStreet = null,
        ?string $companyId = null,
        ?string $contactName = null,
        ?string $contactPhone = null,
        ?string $contactEmail = null,
        string $taxSchemeId = 'VAT'
    ): self {
        if (empty(trim($endpointId))) {
            throw new \InvalidArgumentException('Endpoint ID is required');
        }

        // Validate VAT number format (if provided) - must start with a valid ISO 3166-1 alpha-2 country code
        if (!empty($companyId)) {
            $iso3166Alpha2Codes = [
                '1A',
                'AD',
                'AE',
                'AF',
                'AG',
                'AI',
                'AL',
                'AM',
                'AO',
                'AQ',
                'AR',
                'AS',
                'AT',
                'AU',
                'AW',
                'AX',
                'AZ',
                'BA',
                'BB',
                'BD',
                'BE',
                'BF',
                'BG',
                'BH',
                'BI',
                'BJ',
                'BL',
                'BM',
                'BN',
                'BO',
                'BQ',
                'BR',
                'BS',
                'BT',
                'BV',
                'BW',
                'BY',
                'BZ',
                'CA',
                'CC',
                'CD',
                'CF',
                'CG',
                'CH',
                'CI',
                'CK',
                'CL',
                'CM',
                'CN',
                'CO',
                'CR',
                'CU',
                'CV',
                'CW',
                'CX',
                'CY',
                'CZ',
                'DE',
                'DJ',
                'DK',
                'DM',
                'DO',
                'DZ',
                'EC',
                'EE',
                'EG',
                'EH',
                'EL',
                'ER',
                'ES',
                'ET',
                'FI',
                'FJ',
                'FK',
                'FM',
                'FO',
                'FR',
                'GA',
                'GB',
                'GD',
                'GE',
                'GF',
                'GG',
                'GH',
                'GI',
                'GL',
                'GM',
                'GN',
                'GP',
                'GQ',
                'GR',
                'GS',
                'GT',
                'GU',
                'GW',
                'GY',
                'HK',
                'HM',
                'HN',
                'HR',
                'HT',
                'HU',
                'ID',
                'IE',
                'IL',
                'IM',
                'IN',
                'IO',
                'IQ',
                'IR',
                'IS',
                'IT',
                'JE',
                'JM',
                'JO',
                'JP',
                'KE',
                'KG',
                'KH',
                'KI',
                'KM',
                'KN',
                'KP',
                'KR',
                'KW',
                'KY',
                'KZ',
                'LA',
                'LB',
                'LC',
                'LI',
                'LK',
                'LR',
                'LS',
                'LT',
                'LU',
                'LV',
                'LY',
                'MA',
                'MC',
                'MD',
                'ME',
                'MF',
                'MG',
                'MH',
                'MK',
                'ML',
                'MM',
                'MN',
                'MO',
                'MP',
                'MQ',
                'MR',
                'MS',
                'MT',
                'MU',
                'MV',
                'MW',
                'MX',
                'MY',
                'MZ',
                'NA',
                'NC',
                'NE',
                'NF',
                'NG',
                'NI',
                'NL',
                'NO',
                'NP',
                'NR',
                'NU',
                'NZ',
                'OM',
                'PA',
                'PE',
                'PF',
                'PG',
                'PH',
                'PK',
                'PL',
                'PM',
                'PN',
                'PR',
                'PS',
                'PT',
                'PW',
                'PY',
                'QA',
                'RE',
                'RO',
                'RS',
                'RU',
                'RW',
                'SA',
                'SB',
                'SC',
                'SD',
                'SE',
                'SG',
                'SH',
                'SI',
                'SJ',
                'SK',
                'SL',
                'SM',
                'SN',
                'SO',
                'SR',
                'SS',
                'ST',
                'SV',
                'SX',
                'SY',
                'SZ',
                'TC',
                'TD',
                'TF',
                'TG',
                'TH',
                'TJ',
                'TK',
                'TL',
                'TM',
                'TN',
                'TO',
                'TR',
                'TT',
                'TV',
                'TW',
                'TZ',
                'UA',
                'UG',
                'UM',
                'US',
                'UY',
                'UZ',
                'VA',
                'VC',
                'VE',
                'VG',
                'VI',
                'VN',
                'VU',
                'WF',
                'WS',
                'XI',
                'YE',
                'YT',
                'ZA',
                'ZM',
                'ZW'
            ];

            $countryCode = strtoupper(substr($companyId, 0, 2));
            if (!in_array($countryCode, $iso3166Alpha2Codes, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid VAT number format. Must start with a valid ISO 3166-1 alpha-2 country code. Got: %s', $countryCode));
            }
        }
        $errors = [];

        // Validate required fields
        $requiredFields = [
            'Endpoint ID' => $endpointId,
            'Endpoint Scheme ID' => $endpointSchemeID,
            'Party ID' => $partyId,
            'Party name' => $partyName,
            'Street' => $street,
            'Postal code' => $postalCode,
            'City' => $city,
            'Country code' => $countryCode
        ];

        foreach ($requiredFields as $field => $value) {
            if (empty(trim($value ?? ''))) {
                $errors[] = "$field is required";
            }
        }

        // Validate country code format
        if (!empty($countryCode) && strlen(trim($countryCode)) !== 2) {
            $errors[] = 'Country code must be exactly 2 characters (e.g., "NL")';
        }

        // Validate email format if provided
        if (!empty($contactEmail) && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format for contact email';
        }

        // Validate phone number if provided (basic validation)
        if (!empty($contactPhone) && !preg_match('/^[+0-9\s\-\(\)]{6,20}$/', $contactPhone)) {
            $errors[] = 'Invalid phone number format. Only numbers, +, -, spaces and parentheses are allowed';
        }

        // Throw exception with all validation errors
        if (!empty($errors)) {
            $errorMessage = "Validation error(s) in customer information:\n" .
                implode("\n- ", array_merge([''], $errors));
            throw new \InvalidArgumentException($errorMessage);
        }

        // AccountingCustomerParty container
        $accountingCustomerParty = $this->createElement('cac', 'AccountingCustomerParty');
        $accountingCustomerParty = $this->rootElement->appendChild($accountingCustomerParty);

        // Party container
        $party = $this->createElement('cac', 'Party');
        $party = $accountingCustomerParty->appendChild($party);

        // For Dutch customers, ensure we use the correct scheme ID (0106 for KVK or 0190 for OIN)
        // instead of the Italian Tax Code (0210)
        $effectiveSchemeID = (strtoupper($countryCode) === 'NL' && $endpointSchemeID === '0210') ? '0106' : $endpointSchemeID;

        // EndpointID
        $endpointIDElement = $this->createElement('cbc', 'EndpointID', $endpointId, ['schemeID' => $effectiveSchemeID]);
        $party->appendChild($endpointIDElement);

        // PartyIdentification
        $partyIdentification = $this->createElement('cac', 'PartyIdentification');
        $partyIdentification = $party->appendChild($partyIdentification);

        $idElement = $this->createElement('cbc', 'ID', $partyId, ['schemeID' => $effectiveSchemeID]);
        $partyIdentification->appendChild($idElement);

        // PartyName
        $partyNameElement = $this->createElement('cac', 'PartyName');
        $partyNameElement = $party->appendChild($partyNameElement);

        $nameElement = $this->createElement('cbc', 'Name', $partyName);
        $partyNameElement->appendChild($nameElement);

        // PostalAddress
        $postalAddress = $this->createElement('cac', 'PostalAddress');
        $postalAddress = $party->appendChild($postalAddress);

        $streetNameElement = $this->createElement('cbc', 'StreetName', $street);
        $postalAddress->appendChild($streetNameElement);

        if ($additionalStreet !== null) {
            $additionalStreetNameElement = $this->createElement('cbc', 'AdditionalStreetName', $additionalStreet);
            $postalAddress->appendChild($additionalStreetNameElement);
        }

        $cityNameElement = $this->createElement('cbc', 'CityName', $city);
        $postalAddress->appendChild($cityNameElement);

        $postalZoneElement = $this->createElement('cbc', 'PostalZone', $postalCode);
        $postalAddress->appendChild($postalZoneElement);

        // Country - must be the last element within PostalAddress
        $country = $this->createElement('cac', 'Country');
        $country = $postalAddress->appendChild($country);

        $countryCodeElement = $this->createElement('cbc', 'IdentificationCode', strtoupper($countryCode));
        $country->appendChild($countryCodeElement);

        // PartyTaxScheme
        $partyTaxScheme = $this->createElement('cac', 'PartyTaxScheme');
        $partyTaxScheme = $party->appendChild($partyTaxScheme);

        if ($companyId) {
            $companyIDElement = $this->createElement('cbc', 'CompanyID', $companyId);
            $partyTaxScheme->appendChild($companyIDElement);

            $taxScheme = $this->createElement('cac', 'TaxScheme');
            $taxScheme = $partyTaxScheme->appendChild($taxScheme);

            $taxSchemeIDElement = $this->createElement('cbc', 'ID', $taxSchemeId);
            $taxScheme->appendChild($taxSchemeIDElement);
        }

        // PartyLegalEntity
        $partyLegalEntity = $this->createElement('cac', 'PartyLegalEntity');
        $partyLegalEntity = $party->appendChild($partyLegalEntity);

        $registrationNameElement = $this->createElement('cbc', 'RegistrationName', $partyName);
        $partyLegalEntity->appendChild($registrationNameElement);

        if ($companyId) {
            // Add CompanyID with schemeID for Dutch legal entity identifier (KVK)
            $companyIDElement = $this->createElement('cbc', 'CompanyID', $companyId, ['schemeID' => '0106']);
            $partyLegalEntity->appendChild($companyIDElement);
        }

        // Alleen een Contact element toevoegen als er minstens één contactgegeven is opgegeven
        if ($contactName || $contactPhone || $contactEmail) {
            $contact = $this->createElement('cac', 'Contact');
            $contact = $party->appendChild($contact);

            if ($contactName) {
                $nameElement = $this->createElement('cbc', 'Name', $contactName);
                $contact->appendChild($nameElement);
            }

            if ($contactPhone) {
                $telephoneElement = $this->createElement('cbc', 'Telephone', $contactPhone);
                $contact->appendChild($telephoneElement);
            }

            if ($contactEmail) {
                $electronicMailElement = $this->createElement('cbc', 'ElectronicMail', $contactEmail);
                $contact->appendChild($electronicMailElement);
            }
        }

        return $this;
    }

    /**
     * Add delivery information
     *
     * @param string $deliveryDate Delivery date (required)
     * @param string|null $locationId Unique ID for the delivery location (optional)
     * @param string $locationSchemeId Scheme ID for the location (optional, default: '0088' for GLN)
     * @param string|null $street Street name (optional)
     * @param string|null $additionalStreet Additional street information (optional)
     * @param string|null $city City (optional)
     * @param string|null $postalCode Postal code (optional)
     * @param string|null $countryCode Country code (2 letters) (optional)
     * @param string|null $partyName Name of the receiving party (optional)
     * @return self
     */
    public function addDelivery(
        string $deliveryDate,
        ?string $locationId = null,
        string $locationSchemeId = '0088',
        ?string $street = null,
        ?string $additionalStreet = null,
        ?string $city = null,
        ?string $postalCode = null,
        ?string $countryCode = null,
        ?string $partyName = null
    ): self {
        // Delivery container
        $delivery = $this->createElement('cac', 'Delivery');
        $delivery = $this->rootElement->appendChild($delivery);

        // ActualDeliveryDate
        $actualDeliveryDateElement = $this->createElement('cbc', 'ActualDeliveryDate', $deliveryDate);
        $delivery->appendChild($actualDeliveryDateElement);

        // Only add DeliveryLocation if there is location data
        if ($locationId !== null || $street !== null || $city !== null) {
            $deliveryLocation = $this->createElement('cac', 'DeliveryLocation');
            $deliveryLocation = $delivery->appendChild($deliveryLocation);

            // Only add ID if it's provided
            if ($locationId !== null) {
                $idElement = $this->createElement('cbc', 'ID', $locationId, ['schemeID' => $locationSchemeId]);
                $deliveryLocation->appendChild($idElement);
            }

            // Add address if there is address data
            if ($street !== null || $city !== null || $postalCode !== null || $countryCode !== null) {
                $address = $this->createElement('cac', 'Address');
                $address = $deliveryLocation->appendChild($address);

                if ($street !== null) {
                    $streetNameElement = $this->createElement('cbc', 'StreetName', $street);
                    $address->appendChild($streetNameElement);
                }

                if ($additionalStreet !== null) {
                    $additionalStreetElement = $this->createElement('cbc', 'AdditionalStreetName', $additionalStreet);
                    $address->appendChild($additionalStreetElement);
                }

                if ($city !== null) {
                    $cityNameElement = $this->createElement('cbc', 'CityName', $city);
                    $address->appendChild($cityNameElement);
                }

                if ($postalCode !== null) {
                    $postalZoneElement = $this->createElement('cbc', 'PostalZone', $postalCode);
                    $address->appendChild($postalZoneElement);
                }

                if ($countryCode !== null) {
                    $country = $this->createElement('cac', 'Country');
                    $country = $address->appendChild($country);

                    $identificationCodeElement = $this->createElement('cbc', 'IdentificationCode', strtoupper($countryCode));
                    $country->appendChild($identificationCodeElement);
                }
            }
        }

        // Only add DeliveryParty if a party name is provided
        if ($partyName !== null) {
            $deliveryParty = $this->createElement('cac', 'DeliveryParty');
            $deliveryParty = $delivery->appendChild($deliveryParty);

            $partyNameElement = $this->createElement('cac', 'PartyName');
            $partyNameElement = $deliveryParty->appendChild($partyNameElement);

            $nameElement = $this->createElement('cbc', 'Name', $partyName);
            $partyNameElement->appendChild($nameElement);
        }

        return $this;
    }

    /**
     * Validate IBAN (International Bank Account Number)
     * 
     * @param string $iban The IBAN to validate
     * @return bool True if the IBAN is valid, false otherwise
     */
    private function isValidIban(string $iban): bool
    {
        // Normalize IBAN (remove spaces and convert to uppercase)
        $iban = strtoupper(str_replace(' ', '', $iban));

        // Check length is at least 2 characters (country code + check digits)
        if (strlen($iban) < 4) {
            return false;
        }

        // Move first 4 characters to the end
        $moved = substr($iban, 4) . substr($iban, 0, 4);

        // Convert letters to numbers (A=10, B=11, ..., Z=35)
        $converted = '';
        foreach (str_split($moved) as $char) {
            if (ctype_alpha($char)) {
                $converted .= (ord($char) - 55);
            } else {
                $converted .= $char;
            }
        }

        // Check if the number is valid using modulo 97
        return (int)bcmod($converted, '97') === 1;
    }

    /**
     * Add payment means (betalingsgegevens) to the invoice
     *
     * @param string $paymentMeansCode Payment means code (e.g., '30' for credit transfer)
     * @param string $paymentMeansName Payment means name (e.g., 'Credit transfer')
     * @param string $paymentId Payment reference or ID
     * @param string $accountId Bank account number (IBAN)
     * @param string $accountName Name on the bank account
     * @param string $financialInstitutionId BIC/SWIFT code of the financial institution
     * @param string|null $paymentChannelCode Payment channel code (optional)
     * @param string|null $paymentDueDate Payment due date in YYYY-MM-DD format (optional)
     * @return self
     */
    public function addPaymentMeans(
        string $paymentMeansCode = '30',
        string $paymentMeansName = 'Credit transfer',
        ?string $paymentId = null,
        ?string $accountId = null,
        ?string $accountName = null,
        ?string $financialInstitutionId = null,
        ?string $paymentChannelCode = null,
        ?string $paymentDueDate = null
    ): self {
        // Validate payment means code (should be a valid UNCL4461 code)
        if (!preg_match('/^[0-9]+$/', $paymentMeansCode)) {
            throw new \InvalidArgumentException('Payment means code must be a numeric value');
        }

        // Validate IBAN if provided
        if ($accountId !== null && !$this->isValidIban($accountId)) {
            throw new \InvalidArgumentException('Invalid IBAN format');
        }

        // Validate BIC/SWIFT if provided
        if ($financialInstitutionId !== null && !preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $financialInstitutionId)) {
            throw new \InvalidArgumentException('Invalid BIC/SWIFT code format');
        }

        // Create payment means element
        $paymentMeans = $this->createElement('cac', 'PaymentMeans');

        // Add payment means code with name
        $this->addChildElement($paymentMeans, 'cbc', 'PaymentMeansCode', $paymentMeansCode, ['name' => $paymentMeansName]);

        // Add payment ID if provided
        if ($paymentId !== null) {
            $this->addChildElement($paymentMeans, 'cbc', 'PaymentID', $paymentId);
        }
        // Add payment due date inside PaymentMeans (allowed by UBL)
        // if ($paymentDueDate !== null) {
        //     $this->addChildElement($paymentMeans, 'cbc', 'PaymentDueDate', $paymentDueDate);
        // }

        // PaymentChannelCode is not included as per UBL-CR-413
        // PaymentDueDate is not included as per UBL-CR-412 (must be at invoice level)

        // Add payee financial account if account ID is provided
        if ($accountId !== null) {
            $payeeFinancialAccount = $this->createElement('cac', 'PayeeFinancialAccount');
            $payeeFinancialAccount = $paymentMeans->appendChild($payeeFinancialAccount);

            // Add account ID (IBAN)
            $idElement = $this->createElement('cbc', 'ID', $accountId);
            $payeeFinancialAccount->appendChild($idElement);

            // Add account name if provided
            if ($accountName !== null) {
                $nameElement = $this->createElement('cbc', 'Name', $accountName);
                $payeeFinancialAccount->appendChild($nameElement);
            }

            // Add financial institution (BIC/SWIFT) if provided
            if ($financialInstitutionId !== null) {
                $financialInstitutionBranch = $this->createElement('cac', 'FinancialInstitutionBranch');
                $financialInstitutionBranch = $payeeFinancialAccount->appendChild($financialInstitutionBranch);

                $bicElement = $this->createElement('cbc', 'ID', $financialInstitutionId);
                $financialInstitutionBranch->appendChild($bicElement);
            }
        }

        $this->rootElement->appendChild($paymentMeans);

        // Add payment due date to the root level if provided
        // if ($paymentDueDate !== null) {
        //     $this->addChildElement($this->rootElement, 'cbc', 'PaymentDueDate', $paymentDueDate);
        // }

        return $this;
    }

    /**
     * Add payment terms to the invoice
     * 
     * @param string|null $note The payment terms (e.g., 'Payment within 30 days, 2% discount if paid within 10 days')
     * @param string|null $settlementDiscountPercent The discount percentage for early payment (e.g., '2.00')
     * @param string|null $settlementDiscountAmount The discount amount for early payment (e.g., '10.00')
     * @param string|null $settlementDiscountDate Due date for the discount (e.g., '2025-10-15')
     * @return self
     * @throws \InvalidArgumentException For missing or invalid values
     */
    public function addPaymentTerms(?string $note = null): self
    {
        if (empty($note)) {
            throw new \InvalidArgumentException('Payment terms note is required and cannot be empty');
        }

        $paymentTerms = $this->createElement('cac', 'PaymentTerms');
        $paymentTerms = $this->rootElement->appendChild($paymentTerms);

        $noteElement = $this->createElement('cbc', 'Note', $note);
        $paymentTerms->appendChild($noteElement);

        return $this;
    }



    /**
     * Add an allowance or charge to the invoice
     * 
     * @param bool $isCharge True for a charge, false for an allowance
     * @param float $amount The amount of the allowance or charge
     * @param string $reason Reason for the allowance/charge (e.g., 'Insurance', 'Freight', 'Discount')
     * @param string $taxCategoryId Tax category ID (e.g., 'S' for standard rate, 'Z' for zero rate)
     * @param float $taxPercent Tax percentage (e.g., 21.0 for 21%)
     * @param string $currency Currency code (default: 'EUR')
     * @return self
     * @throws \InvalidArgumentException For invalid input values
     */
    public function addAllowanceCharge(
        bool $isCharge = true,
        float $amount = 0.0,
        string $reason = '',
        string $taxCategoryId = 'S',
        float $taxPercent = 0.0,
        string $currency = 'EUR'
    ): self {
        // Validate input values
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }

        if (empty($reason)) {
            throw new \InvalidArgumentException('Reason for allowance/charge is required');
        }

        if ($taxPercent < 0 || $taxPercent > 100) {
            throw new \InvalidArgumentException('Tax percentage must be between 0 and 100');
        }

        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency code must be 3 characters long');
        }

        // Create AllowanceCharge container
        $allowanceCharge = $this->createElement('cac', 'AllowanceCharge');
        $allowanceCharge = $this->rootElement->appendChild($allowanceCharge);

        // Add charge/allowance indicator
        $chargeIndicatorElement = $this->createElement('cbc', 'ChargeIndicator', $isCharge ? 'true' : 'false');
        $allowanceCharge->appendChild($chargeIndicatorElement);

        // Add reason for the allowance/charge
        $allowanceChargeReasonElement = $this->createElement('cbc', 'AllowanceChargeReason', $reason);
        $allowanceCharge->appendChild($allowanceChargeReasonElement);

        // Add amount with currency
        $amountElement = $this->createElement('cbc', 'Amount', (string)number_format($amount, 2, '.', ''), ['currencyID' => $currency]);
        $allowanceCharge->appendChild($amountElement);

        // Add tax information if tax percentage is greater than 0
        if ($taxPercent > 0) {
            // TaxCategory
            $taxCategory = $this->createElement('cac', 'TaxCategory');
            $taxCategory = $allowanceCharge->appendChild($taxCategory);

            // Tax category ID (e.g., 'S' for standard rate, 'Z' for zero rate)
            $idElement = $this->createElement('cbc', 'ID', $taxCategoryId);
            $taxCategory->appendChild($idElement);

            // Tax percentage
            $percentElement = $this->createElement('cbc', 'Percent', (string)number_format($taxPercent, 2, '.', ''));
            $taxCategory->appendChild($percentElement);

            // Tax scheme (always VAT for this implementation)
            $taxScheme = $this->createElement('cac', 'TaxScheme');
            $taxScheme = $taxCategory->appendChild($taxScheme);

            $taxSchemeIDElement = $this->createElement('cbc', 'ID', 'VAT');
            $taxScheme->appendChild($taxSchemeIDElement);
        }

        return $this;
    }

    /**
     * Add tax total information to the invoice
     * 
     * @param array $taxes Array of tax entries with the following structure:
     *   [
     *     [
     *       'taxable_amount' => 1000.00, // Required: Amount subject to tax (must be >= 0)
     *       'tax_amount' => 210.00,      // Required: Tax amount (must be >= 0)
     *       'currency' => 'EUR',         // Required: Currency code (3 letters)
     *       'tax_category_id' => 'S',    // Required: Tax category ID (e.g., 'S' for standard rate)
     *       'tax_percent' => 21.0,       // Required: Tax percentage (0-100)
     *       'tax_scheme_id' => 'VAT'     // Required: Tax scheme ID (e.g., 'VAT')
     *     ]
     *   ]
     * @return self
     * @throws \InvalidArgumentException For invalid or missing required fields
     */
    public function addTaxTotal(array $taxes): self
    {
        if (empty($taxes)) {
            throw new \InvalidArgumentException('At least one tax entry is required');
        }

        // Validate each tax entry
        $totalTaxAmount = 0;
        $entryNumber = 0;

        foreach ($taxes as $tax) {
            $entryNumber++;
            $errorPrefix = "Tax entry #{$entryNumber}: ";

            // Check required fields
            $requiredFields = [
                'taxable_amount' => 'Taxable amount is required and must be a non-negative number',
                'tax_amount' => 'Tax amount is required and must be a non-negative number',
                'currency' => 'Currency code is required and must be 3 characters long',
                'tax_category_id' => 'Tax category ID is required',
                'tax_percent' => 'Tax percentage is required and must be between 0 and 100',
                'tax_scheme_id' => 'Tax scheme ID is required'
            ];

            foreach ($requiredFields as $field => $errorMessage) {
                if (!array_key_exists($field, $tax)) {
                    throw new \InvalidArgumentException($errorPrefix . $errorMessage);
                }
            }

            // Validate field types and values
            if (!is_numeric($tax['taxable_amount']) || $tax['taxable_amount'] < 0) {
                throw new \InvalidArgumentException($errorPrefix . 'Taxable amount must be a non-negative number');
            }

            if (!is_numeric($tax['tax_amount']) || $tax['tax_amount'] < 0) {
                throw new \InvalidArgumentException($errorPrefix . 'Tax amount must be a non-negative number');
            }

            if (!is_string($tax['tax_category_id']) || empty(trim($tax['tax_category_id']))) {
                throw new \InvalidArgumentException($errorPrefix . 'Tax category ID must be a non-empty string');
            }

            if (!is_numeric($tax['tax_percent']) || $tax['tax_percent'] < 0 || $tax['tax_percent'] > 100) {
                throw new \InvalidArgumentException($errorPrefix . 'Tax percentage must be a number between 0 and 100');
            }

            if (!is_string($tax['currency']) || strlen($tax['currency']) !== 3) {
                throw new \InvalidArgumentException($errorPrefix . 'Currency code must be exactly 3 characters long');
            }

            if (!is_string($tax['tax_scheme_id']) || empty(trim($tax['tax_scheme_id']))) {
                throw new \InvalidArgumentException($errorPrefix . 'Tax scheme ID must be a non-empty string');
            }

            $totalTaxAmount += (float)$tax['tax_amount'];
        }

        // Create TaxTotal container
        $taxTotal = $this->createElement('cac', 'TaxTotal');
        $taxTotal = $this->rootElement->appendChild($taxTotal);

        // Add total tax amount using the currency from the first tax entry
        $firstTaxCurrency = $taxes[0]['currency'];
        $totalTaxAmountElement = $this->createElement(
            'cbc',
            'TaxAmount',
            number_format($totalTaxAmount, 2, '.', ''),
            ['currencyID' => $firstTaxCurrency]
        );
        $taxTotal->appendChild($totalTaxAmountElement);

        // Add tax subtotals for each tax category
        foreach ($taxes as $tax) {
            $taxCurrency = $tax['currency'];

            // Create TaxSubtotal element
            $taxSubtotal = $this->createElement('cac', 'TaxSubtotal');
            $taxSubtotal = $taxTotal->appendChild($taxSubtotal);

            // Add taxable amount
            $taxableAmountElement = $this->createElement(
                'cbc',
                'TaxableAmount',
                number_format($tax['taxable_amount'], 2, '.', ''),
                ['currencyID' => $taxCurrency]
            );
            $taxSubtotal->appendChild($taxableAmountElement);

            // Add tax amount
            $taxAmountElement = $this->createElement(
                'cbc',
                'TaxAmount',
                number_format($tax['tax_amount'], 2, '.', ''),
                ['currencyID' => $taxCurrency]
            );
            $taxSubtotal->appendChild($taxAmountElement);

            // Add tax category
            $taxCategory = $this->createElement('cac', 'TaxCategory');
            $taxCategory = $taxSubtotal->appendChild($taxCategory);

            // Add tax category ID
            $idElement = $this->createElement('cbc', 'ID', $tax['tax_category_id']);
            $taxCategory->appendChild($idElement);

            // Add tax percentage
            $percentElement = $this->createElement(
                'cbc',
                'Percent',
                number_format($tax['tax_percent'], 2, '.', '')
            );
            $taxCategory->appendChild($percentElement);

            // Add tax scheme
            $taxScheme = $this->createElement('cac', 'TaxScheme');
            $taxScheme = $taxCategory->appendChild($taxScheme);

            $taxSchemeIDElement = $this->createElement('cbc', 'ID', $tax['tax_scheme_id'] ?? 'VAT');
            $taxScheme->appendChild($taxSchemeIDElement);
        }

        return $this;
    }

    /**
     * Add LegalMonetaryTotal (financial totals) to the invoice
     * 
     * @param array $amounts Associative array containing the following required keys:
     *   - line_extension_amount: Total of all invoice lines excluding tax
     *   - tax_exclusive_amount: Amount excluding tax (line_extension_amount + charges - allowances)
     *   - tax_inclusive_amount: Amount including tax
     *   - charge_total_amount: Total of all charges
     *   - payable_amount: Total amount to be paid (should equal tax_inclusive_amount)
     * @param string $currency Currency code (3 letters, e.g., 'EUR')
     * @return self
     * @throws \InvalidArgumentException For missing or invalid parameters
     */
    public function addLegalMonetaryTotal(array $amounts, string $currency = 'EUR'): self
    {
        // Validate required fields
        $requiredFields = [
            'line_extension_amount' => 'Line extension amount is required',
            'tax_exclusive_amount' => 'Tax exclusive amount is required',
            'tax_inclusive_amount' => 'Tax inclusive amount is required',
            'charge_total_amount' => 'Charge total amount is required',
            'payable_amount' => 'Payable amount is required'
        ];

        foreach ($requiredFields as $field => $errorMessage) {
            if (!array_key_exists($field, $amounts) || !is_numeric($amounts[$field])) {
                throw new \InvalidArgumentException($errorMessage);
            }
        }

        // Validate currency
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency code must be exactly 3 characters long');
        }

        // Format amounts to 2 decimal places
        $formattedAmounts = [];
        foreach ($amounts as $key => $value) {
            $formattedAmounts[$key] = number_format((float)$value, 2, '.', '');
        }

        // Create LegalMonetaryTotal container
        $legalMonetaryTotal = $this->createElement('cac', 'LegalMonetaryTotal');
        $legalMonetaryTotal = $this->rootElement->appendChild($legalMonetaryTotal);

        // Add all monetary amounts with currency
        $elements = [
            'LineExtensionAmount' => $formattedAmounts['line_extension_amount'],
            'TaxExclusiveAmount' => $formattedAmounts['tax_exclusive_amount'],
            'TaxInclusiveAmount' => $formattedAmounts['tax_inclusive_amount'],
            'ChargeTotalAmount' => $formattedAmounts['charge_total_amount'],
            'PayableAmount' => $formattedAmounts['payable_amount']
        ];

        foreach ($elements as $elementName => $amount) {
            $element = $this->createElement(
                'cbc',
                $elementName,
                $amount,
                ['currencyID' => $currency]
            );
            $legalMonetaryTotal->appendChild($element);
        }

        return $this;
    }


    /**
     * Add an invoice line to the document
     * 
     * @param array $lineData Array containing the invoice line data with the following structure:
     *   [
     *     'id' => '1',                               // Required: Line item ID
     *     'quantity' => '2',                         // Required: Quantity
     *     'unit_code' => 'PCE',                      // Required: Unit of measure code (e.g., 'PCE' for piece, 'HUR' for hour)
     *     'line_extension_amount' => '100.00',       // Required: Line total amount excluding tax
     *     'description' => 'Product description',     // Required: Product/service description
     *     'name' => 'Product Name',                  // Required: Product/service name
     *     'price_amount' => '50.00',                 // Required: Price per unit
     *     'currency' => 'EUR',                       // Required: Currency code (3 letters)
     *     'accounting_cost' => 'COST001',            // Optional: Accounting cost center
     *     'order_line_id' => 'PO-001-1',             // Optional: Reference to purchase order line
     *     'standard_item_id' => 'GTIN-123456789',    // Optional: Standard item identifier
     *     'origin_country' => 'NL',                  // Optional: Country of origin (2-letter code)
     *     'tax_category_id' => 'S',                  // Optional: Tax category ID (default: 'S' for standard rate)
     *     'tax_percent' => '21.00',                  // Optional: Tax percentage (default: '21.00')
     *     'tax_scheme_id' => 'VAT',                  // Optional: Tax scheme ID (default: 'VAT')
     *     'item_type_code' => '1000',                // Optional: Item classification code
     *     'item_type_scheme' => 'STD',               // Optional: Item classification scheme (default: 'STD' for Standard)
     *     'item_type_name' => 'Product Type'          // Optional: Item type name
     *   ]
     * @return self
     * @throws \InvalidArgumentException For missing or invalid parameters
     */
    public function addInvoiceLine(array $lineData): self
    {
        // Set default values
        $lineData = array_merge([
            'tax_category_id' => 'S',
            'tax_percent' => '21.00',
            'tax_scheme_id' => 'VAT',
            'item_type_scheme' => 'STD',
            'item_type_name' => 'Product'
        ], $lineData);

        // Validate required fields
        $requiredFields = [
            'id' => 'Line ID is required',
            'quantity' => 'Quantity is required',
            'unit_code' => 'Unit code is required',
            'line_extension_amount' => 'Line extension amount is required',
            'description' => 'Description is required',
            'name' => 'Name is required',
            'price_amount' => 'Price amount is required',
            'currency' => 'Currency is required'
        ];

        foreach ($requiredFields as $field => $errorMessage) {
            if (!isset($lineData[$field]) || $lineData[$field] === '') {
                throw new \InvalidArgumentException($errorMessage);
            }
        }

        // Validate numeric fields
        $numericFields = [
            'quantity' => 'Quantity must be a number',
            'line_extension_amount' => 'Line extension amount must be a number',
            'price_amount' => 'Price amount must be a number',
            'tax_percent' => 'Tax percent must be a number'
        ];

        foreach ($numericFields as $field => $errorMessage) {
            if (isset($lineData[$field]) && !is_numeric($lineData[$field])) {
                throw new \InvalidArgumentException($errorMessage);
            }
        }

        // Validate unit code
        if (!UblValidator::isValidUnitCode($lineData['unit_code'])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid unit code: %s. Must be a valid UN/ECE Recommendation 20 with Rec 21 extension unit code.',
                $lineData['unit_code']
            ));
        }

        // Validate classification scheme if provided
        if (!empty($lineData['item_type_scheme']) && !UblValidator::isValidClassificationScheme($lineData['item_type_scheme'])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid classification scheme: %s. Must be a valid UNTDID 7143 scheme.',
                $lineData['item_type_scheme']
            ));
        }

        // Create InvoiceLine container
        $invoiceLine = $this->createElement('cac', 'InvoiceLine');
        $this->rootElement->appendChild($invoiceLine);

        // Add required elements
        $this->addChildElement($invoiceLine, 'cbc', 'ID', $lineData['id']);

        // Add InvoicedQuantity with unit code
        $this->addChildElement(
            $invoiceLine,
            'cbc',
            'InvoicedQuantity',
            number_format((float)$lineData['quantity'], 2, '.', ''),
            ['unitCode' => $lineData['unit_code']]
        );

        // Add LineExtensionAmount
        $this->addChildElement(
            $invoiceLine,
            'cbc',
            'LineExtensionAmount',
            number_format((float)$lineData['line_extension_amount'], 2, '.', ''),
            ['currencyID' => $lineData['currency']]
        );

        // Add optional accounting cost
        if (!empty($lineData['accounting_cost'])) {
            $this->addChildElement($invoiceLine, 'cbc', 'AccountingCost', $lineData['accounting_cost']);
        }

        // Add order line reference if provided
        if (!empty($lineData['order_line_id'])) {
            $orderLineRef = $this->createElement('cac', 'OrderLineReference');
            $this->addChildElement($orderLineRef, 'cbc', 'LineID', $lineData['order_line_id']);
            $invoiceLine->appendChild($orderLineRef);
        }

        // Create Item section
        $item = $this->createElement('cac', 'Item');
        $invoiceLine->appendChild($item);

        // Add item details
        $this->addChildElement($item, 'cbc', 'Description', $lineData['description']);
        $this->addChildElement($item, 'cbc', 'Name', $lineData['name']);

        // Add standard item identification if provided
        if (!empty($lineData['standard_item_id'])) {
            $stdItemId = $this->createElement('cac', 'StandardItemIdentification');
            $idElement = $this->createElement('cbc', 'ID', $lineData['standard_item_id']);
            $idElement->setAttribute('schemeID', 'GTIN');
            $stdItemId->appendChild($idElement);
            $item->appendChild($stdItemId);
        }

        // Add origin country if provided
        if (!empty($lineData['origin_country'])) {
            $originCountry = $this->createElement('cac', 'OriginCountry');
            $this->addChildElement($originCountry, 'cbc', 'IdentificationCode', $lineData['origin_country']);
            $item->appendChild($originCountry);
        }

        // Add commodity classification if type code is provided
        if (!empty($lineData['item_type_code'])) {
            $commodityClassification = $this->createElement('cac', 'CommodityClassification');
            $itemClassificationCode = $this->createElement('cbc', 'ItemClassificationCode', $lineData['item_type_code']);
            $itemClassificationCode->setAttribute('listID', $lineData['item_type_scheme']);
            $commodityClassification->appendChild($itemClassificationCode);
            $item->appendChild($commodityClassification);
        }

        // Add ClassifiedTaxCategory (required for PEPPOL)
        $classifiedTaxCategory = $this->createElement('cac', 'ClassifiedTaxCategory');
        $this->addChildElement($classifiedTaxCategory, 'cbc', 'ID', $lineData['tax_category_id']);
        $this->addChildElement($classifiedTaxCategory, 'cbc', 'Percent', number_format((float)$lineData['tax_percent'], 2, '.', ''));

        $taxScheme = $this->createElement('cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', $lineData['tax_scheme_id']);
        $classifiedTaxCategory->appendChild($taxScheme);

        $item->appendChild($classifiedTaxCategory);

        // Add price information
        $price = $this->createElement('cac', 'Price');
        $priceAmount = $this->createElement('cbc', 'PriceAmount', number_format((float)$lineData['price_amount'], 2, '.', ''));
        $priceAmount->setAttribute('currencyID', $lineData['currency']);
        $price->appendChild($priceAmount);
        $invoiceLine->appendChild($price);

        // TaxTotal is not included in invoice lines as per UBL-CR-561
        // Tax information is only provided at the document level through the TaxTotal element

        return $this;
    }
}
