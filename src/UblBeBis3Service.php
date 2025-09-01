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
class UblBeBis3Service
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
            'urn:cen.eu:en16931:2017#conformant#urn:UBL.BE:1.0.0.20180214'
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
     * Add an Additional Document Reference to the invoice.
     *
     * @param string $id The identifier of the referenced document.
     * @param string|null $documentType The type of the referenced document.
     * @return self
     */
    public function addAdditionalDocumentReference(string $id, ?string $documentType = null): self
    {
        $docRef = $this->addChildElement($this->rootElement, 'cac', 'AdditionalDocumentReference');
        $this->addChildElement($docRef, 'cbc', 'ID', $id);

        if ($documentType) {
            $this->addChildElement($docRef, 'cbc', 'DocumentDescription', $documentType);
        }

        return $this;
    }

    /**
     * Add AccountingSupplierParty to the UBL document
     *
     * @param string $endpointId
     * @param string $endpointScheme
     * @param string $partyId
     * @param string $name
     * @param string $street
     * @param string $postalCode
     * @param string $city
     * @param string $country
     * @param string $vatNumber
     * @param string|null $additionalStreet
     * @return self
     */
    /**
     * Add AccountingCustomerParty to the UBL document
     *
     * @param string $endpointId
     * @param string $endpointScheme
     * @param string $partyId
     * @param string $name
     * @param string $street
     * @param string $postalCode
     * @param string $city
     * @param string $country
     * @param string|null $additionalStreet
     * @param string|null $registrationNumber
     * @param string|null $contactName
     * @param string|null $contactPhone
     * @param string|null $contactEmail
     * @return self
     */
    /**
     * Add Delivery to the UBL document
     *
     * @param string $date
     * @param string $location_id
     * @param string $location_scheme
     * @param string $street
     * @param string|null $additional_street
     * @param string $city
     * @param string $postal_code
     * @param string $country
     * @param string|null $party_name
     * @return self
     */
    /**
     * Add PaymentMeans to the UBL document
     *
     * @param string $means_code
     * @param string|null $means_name
     * @param string $payment_id
     * @param string $account_iban
     * @param string|null $account_name
     * @param string|null $bic
     * @param string|null $channel_code
     * @param string|null $due_date
     * @return self
     */
    /**
     * Add PaymentTerms to the UBL document
     *
     * @param string|null $note
     * @param float|null $discount_percent
     * @param float|null $discount_amount
     * @param string|null $discount_date
     * @return self
     */
    /**
     * Add AllowanceCharge to the UBL document
     *
     * @param bool $isCharge
     * @param float $amount
     * @param string $reason
     * @param string $taxCategoryId
     * @param float $taxPercent
     * @param string $currency
     * @return self
     */
    /**
     * Add TaxTotal to the UBL document
     *
     * @param array $taxTotals
     * @return self
     */
    /**
     * Add LegalMonetaryTotal to the UBL document
     *
     * @param array $totals
     * @param string $currency
     * @return self
     */
    /**
     * Add InvoiceLine to the UBL document
     *
     * @param array $lineData
     * @return self
     */
    public function addInvoiceLine(array $lineData): self
    {
        $invoiceLine = $this->addChildElement($this->rootElement, 'cac', 'InvoiceLine');

        $this->addChildElement($invoiceLine, 'cbc', 'ID', $lineData['id']);
        $this->addChildElement($invoiceLine, 'cbc', 'InvoicedQuantity', $this->formatAmount((float)$lineData['quantity']), ['unitCode' => $lineData['unit_code']]);
        $this->addChildElement($invoiceLine, 'cbc', 'LineExtensionAmount', $this->formatAmount((float)$lineData['line_extension_amount']), ['currencyID' => $lineData['currency']]);

        if (!empty($lineData['accounting_cost'])) {
            $this->addChildElement($invoiceLine, 'cbc', 'AccountingCost', $lineData['accounting_cost']);
        }

        if (!empty($lineData['order_line_id'])) {
            $orderLineReference = $this->addChildElement($invoiceLine, 'cac', 'OrderLineReference');
            $this->addChildElement($orderLineReference, 'cbc', 'LineID', $lineData['order_line_id']);
        }

        $item = $this->addChildElement($invoiceLine, 'cac', 'Item');
        $this->addChildElement($item, 'cbc', 'Description', $lineData['description']);
        $this->addChildElement($item, 'cbc', 'Name', $lineData['name']);

        $classifiedTaxCategory = $this->addChildElement($item, 'cac', 'ClassifiedTaxCategory');
        $this->addChildElement($classifiedTaxCategory, 'cbc', 'ID', $lineData['tax_category_id']);
        if (!empty($lineData['tax_category_name'])) {
            $this->addChildElement($classifiedTaxCategory, 'cbc', 'Name', $lineData['tax_category_name']);
        }
        $this->addChildElement($classifiedTaxCategory, 'cbc', 'Percent', $this->formatAmount((float)$lineData['tax_percent']));
        $taxScheme = $this->addChildElement($classifiedTaxCategory, 'cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', $lineData['tax_scheme_id']);

        $taxTotal = $this->addChildElement($invoiceLine, 'cac', 'TaxTotal');
        $taxAmount = ($lineData['line_extension_amount'] * $lineData['tax_percent']) / 100;
        $this->addChildElement($taxTotal, 'cbc', 'TaxAmount', $this->formatAmount($taxAmount), ['currencyID' => $lineData['currency']]);

        $price = $this->addChildElement($invoiceLine, 'cac', 'Price');
        $this->addChildElement($price, 'cbc', 'PriceAmount', $this->formatAmount((float)$lineData['price_amount']), ['currencyID' => $lineData['currency']]);
        $this->addChildElement($price, 'cbc', 'BaseQuantity', '1', ['unitCode' => $lineData['unit_code']]);

        return $this;
    }

    public function addLegalMonetaryTotal(array $totals, string $currency): self
    {
        $monetaryTotal = $this->addChildElement($this->rootElement, 'cac', 'LegalMonetaryTotal');

        $this->addChildElement($monetaryTotal, 'cbc', 'LineExtensionAmount', $this->formatAmount((float)$totals['line_extension_amount']), ['currencyID' => $currency]);
        $this->addChildElement($monetaryTotal, 'cbc', 'TaxExclusiveAmount', $this->formatAmount((float)$totals['tax_exclusive_amount']), ['currencyID' => $currency]);
        $this->addChildElement($monetaryTotal, 'cbc', 'TaxInclusiveAmount', $this->formatAmount((float)$totals['tax_inclusive_amount']), ['currencyID' => $currency]);
        $this->addChildElement($monetaryTotal, 'cbc', 'ChargeTotalAmount', $this->formatAmount((float)$totals['charge_total_amount']), ['currencyID' => $currency]);
        $this->addChildElement($monetaryTotal, 'cbc', 'PayableAmount', $this->formatAmount((float)$totals['payable_amount']), ['currencyID' => $currency]);

        return $this;
    }

    public function addTaxTotal(array $taxTotals): self
    {
        // Find and remove existing TaxTotal to prevent duplicates
        $existingTaxTotals = $this->dom->getElementsByTagName('TaxTotal');
        while ($existingTaxTotals->length > 0) {
            $existingTaxTotals->item(0)->parentNode->removeChild($existingTaxTotals->item(0));
        }

        $totalTaxAmount = 0;
        foreach ($taxTotals as $tax) {
            $totalTaxAmount += (float)$tax['tax_amount'];
        }

        $monetaryTotal = $this->dom->getElementsByTagName('LegalMonetaryTotal')->item(0);

        $taxTotalElement = $this->createElement('cac', 'TaxTotal');
        if ($monetaryTotal) {
            $this->rootElement->insertBefore($taxTotalElement, $monetaryTotal);
        } else {
            $this->rootElement->appendChild($taxTotalElement);
        }
        $this->addChildElement($taxTotalElement, 'cbc', 'TaxAmount', $this->formatAmount($totalTaxAmount), ['currencyID' => $taxTotals[0]['currency'] ?? 'EUR']);

        foreach ($taxTotals as $tax) {
            $taxSubtotal = $this->addChildElement($taxTotalElement, 'cac', 'TaxSubtotal');
            $this->addChildElement($taxSubtotal, 'cbc', 'TaxableAmount', $this->formatAmount((float)$tax['taxable_amount']), ['currencyID' => $tax['currency']]);
            $this->addChildElement($taxSubtotal, 'cbc', 'TaxAmount', $this->formatAmount((float)$tax['tax_amount']), ['currencyID' => $tax['currency']]);

            $taxCategory = $this->addChildElement($taxSubtotal, 'cac', 'TaxCategory');
            $this->addChildElement($taxCategory, 'cbc', 'ID', $tax['tax_category_id']);
            if ($tax['tax_category_id'] === 'S') {
                $this->addChildElement($taxCategory, 'cbc', 'Name', 'Standard rated');
            } elseif (!empty($tax['tax_category_name'])) {
                $this->addChildElement($taxCategory, 'cbc', 'Name', $tax['tax_category_name']);
            }
            $this->addChildElement($taxCategory, 'cbc', 'Percent', $this->formatAmount((float)$tax['tax_percent']));
            $taxScheme = $this->addChildElement($taxCategory, 'cac', 'TaxScheme');
            $this->addChildElement($taxScheme, 'cbc', 'ID', $tax['tax_scheme_id']);
        }

        return $this;
    }

    public function addAllowanceCharge(
        bool $isCharge,
        float $amount,
        string $reason,
        string $taxCategoryId,
        float $taxPercent,
        string $currency
    ): self {
        $allowanceCharge = $this->addChildElement($this->rootElement, 'cac', 'AllowanceCharge');
        $this->addChildElement($allowanceCharge, 'cbc', 'ChargeIndicator', $isCharge ? 'true' : 'false');
        $this->addChildElement($allowanceCharge, 'cbc', 'AllowanceChargeReason', $reason);
        $this->addChildElement($allowanceCharge, 'cbc', 'Amount', $this->formatAmount($amount), ['currencyID' => $currency]);

        $taxCategory = $this->addChildElement($allowanceCharge, 'cac', 'TaxCategory');
        $this->addChildElement($taxCategory, 'cbc', 'ID', $taxCategoryId);
        $this->addChildElement($taxCategory, 'cbc', 'Percent', $this->formatAmount($taxPercent));
        $taxScheme = $this->addChildElement($taxCategory, 'cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', 'VAT');

        return $this;
    }

    public function addPaymentTerms(
        ?string $note,
        ?float $discount_percent,
        ?float $discount_amount,
        ?string $discount_date
    ): self {
        $paymentTerms = $this->addChildElement($this->rootElement, 'cac', 'PaymentTerms');
        if ($note) {
            $this->addChildElement($paymentTerms, 'cbc', 'Note', $note);
        }

        return $this;
    }

    public function addPaymentMeans(
        string $means_code,
        ?string $means_name,
        string $payment_id,
        string $account_iban,
        ?string $account_name,
        ?string $bic,
        ?string $channel_code,
        ?string $due_date
    ): self {
        $paymentMeans = $this->addChildElement($this->rootElement, 'cac', 'PaymentMeans');
        $this->addChildElement($paymentMeans, 'cbc', 'PaymentMeansCode', $means_code, $means_name ? ['name' => $means_name] : []);
        $this->addChildElement($paymentMeans, 'cbc', 'PaymentID', $payment_id);

        $payeeFinancialAccount = $this->addChildElement($paymentMeans, 'cac', 'PayeeFinancialAccount');
        $this->addChildElement($payeeFinancialAccount, 'cbc', 'ID', $account_iban);
        if ($account_name) {
            $this->addChildElement($payeeFinancialAccount, 'cbc', 'Name', $account_name);
        }
        if ($bic) {
            $financialInstitutionBranch = $this->addChildElement($payeeFinancialAccount, 'cac', 'FinancialInstitutionBranch');
            $this->addChildElement($financialInstitutionBranch, 'cbc', 'ID', $bic);
        }

        return $this;
    }

    public function addDelivery(
        string $date,
        string $location_id,
        string $location_scheme,
        string $street,
        ?string $additional_street,
        string $city,
        string $postal_code,
        string $country,
        ?string $party_name = null
    ): self {
        $delivery = $this->addChildElement($this->rootElement, 'cac', 'Delivery');
        $this->addChildElement($delivery, 'cbc', 'ActualDeliveryDate', $date);

        $deliveryLocation = $this->addChildElement($delivery, 'cac', 'DeliveryLocation');
        $this->addChildElement($deliveryLocation, 'cbc', 'ID', $location_id, ['schemeID' => $location_scheme]);

        $address = $this->addChildElement($deliveryLocation, 'cac', 'Address');
        $this->addChildElement($address, 'cbc', 'StreetName', $street);
        if ($additional_street) {
            $this->addChildElement($address, 'cbc', 'AdditionalStreetName', $additional_street);
        }
        $this->addChildElement($address, 'cbc', 'CityName', $city);
        $this->addChildElement($address, 'cbc', 'PostalZone', $postal_code);
        $countryElement = $this->addChildElement($address, 'cac', 'Country');
        $this->addChildElement($countryElement, 'cbc', 'IdentificationCode', $country);

        if ($party_name) {
            $deliveryParty = $this->addChildElement($delivery, 'cac', 'DeliveryParty');
            $partyName = $this->addChildElement($deliveryParty, 'cac', 'PartyName');
            $this->addChildElement($partyName, 'cbc', 'Name', $party_name);
        }

        return $this;
    }

    public function addAccountingCustomerParty(
        string $endpointId,
        string $endpointScheme,
        string $partyId,
        string $name,
        string $street,
        string $postalCode,
        string $city,
        string $country,
        ?string $additionalStreet = null,
        ?string $registrationNumber = null,
        ?string $contactName = null,
        ?string $contactPhone = null,
        ?string $contactEmail = null
    ): self {
        $customerParty = $this->addChildElement($this->rootElement, 'cac', 'AccountingCustomerParty');
        $party = $this->addChildElement($customerParty, 'cac', 'Party');

        $this->addChildElement($party, 'cbc', 'EndpointID', $endpointId, ['schemeID' => $endpointScheme]);

        $partyIdentification = $this->addChildElement($party, 'cac', 'PartyIdentification');
        $this->addChildElement($partyIdentification, 'cbc', 'ID', $partyId);

        $partyName = $this->addChildElement($party, 'cac', 'PartyName');
        $this->addChildElement($partyName, 'cbc', 'Name', $name);

        $postalAddress = $this->addChildElement($party, 'cac', 'PostalAddress');
        $this->addChildElement($postalAddress, 'cbc', 'StreetName', $street);
        if ($additionalStreet) {
            $this->addChildElement($postalAddress, 'cbc', 'AdditionalStreetName', $additionalStreet);
        }
        $this->addChildElement($postalAddress, 'cbc', 'CityName', $city);
        $this->addChildElement($postalAddress, 'cbc', 'PostalZone', $postalCode);
        $countryElement = $this->addChildElement($postalAddress, 'cac', 'Country');
        $this->addChildElement($countryElement, 'cbc', 'IdentificationCode', $country);

        $partyLegalEntity = $this->addChildElement($party, 'cac', 'PartyLegalEntity');
        $this->addChildElement($partyLegalEntity, 'cbc', 'RegistrationName', $name);
        if ($registrationNumber) {
            $this->addChildElement($partyLegalEntity, 'cbc', 'CompanyID', $registrationNumber);
        }

        if ($contactName || $contactPhone || $contactEmail) {
            $contact = $this->addChildElement($party, 'cac', 'Contact');
            if ($contactName) {
                $this->addChildElement($contact, 'cbc', 'Name', $contactName);
            }
            if ($contactPhone) {
                $this->addChildElement($contact, 'cbc', 'Telephone', $contactPhone);
            }
            if ($contactEmail) {
                $this->addChildElement($contact, 'cbc', 'ElectronicMail', $contactEmail);
            }
        }

        return $this;
    }

    public function addAccountingSupplierParty(
        string $endpointId,
        string $endpointScheme,
        string $partyId,
        string $name,
        string $street,
        string $postalCode,
        string $city,
        string $country,
        string $vatNumber,
        ?string $additionalStreet = null
    ): self {
        $supplierParty = $this->addChildElement($this->rootElement, 'cac', 'AccountingSupplierParty');
        $party = $this->addChildElement($supplierParty, 'cac', 'Party');

        // EndpointID
        $this->addChildElement($party, 'cbc', 'EndpointID', $endpointId, ['schemeID' => $endpointScheme]);

        // PartyIdentification
        $partyIdentification = $this->addChildElement($party, 'cac', 'PartyIdentification');
        $this->addChildElement($partyIdentification, 'cbc', 'ID', $partyId);

        // PartyName
        $partyName = $this->addChildElement($party, 'cac', 'PartyName');
        $this->addChildElement($partyName, 'cbc', 'Name', $name);

        // PostalAddress
        $postalAddress = $this->addChildElement($party, 'cac', 'PostalAddress');
        $this->addChildElement($postalAddress, 'cbc', 'StreetName', $street);
        if ($additionalStreet) {
            $this->addChildElement($postalAddress, 'cbc', 'AdditionalStreetName', $additionalStreet);
        }
        $this->addChildElement($postalAddress, 'cbc', 'CityName', $city);
        $this->addChildElement($postalAddress, 'cbc', 'PostalZone', $postalCode);
        $countryElement = $this->addChildElement($postalAddress, 'cac', 'Country');
        $this->addChildElement($countryElement, 'cbc', 'IdentificationCode', $country);

        // PartyTaxScheme
        $partyTaxScheme = $this->addChildElement($party, 'cac', 'PartyTaxScheme');
        $this->addChildElement($partyTaxScheme, 'cbc', 'CompanyID', $vatNumber);
        $taxScheme = $this->addChildElement($partyTaxScheme, 'cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', 'VAT');

        // PartyLegalEntity
        $partyLegalEntity = $this->addChildElement($party, 'cac', 'PartyLegalEntity');
        $this->addChildElement($partyLegalEntity, 'cbc', 'RegistrationName', $name);

        return $this;
    }
}
