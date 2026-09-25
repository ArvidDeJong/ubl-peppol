<?php

namespace Darvis\UblPeppol;

use Darvis\UblPeppol\Validation\UblValidator;
use DOMDocument;
use DOMElement;

/**
 * UBL Service for generating UBL/PEPPOL invoices
 *
 * This version has been completely rewritten to follow the exact XML structure
 * of the PEPPOL standard according to the base-example.xml reference.
 */
class UblNlBis3Service
{
    use Validation\ValidationTrackingTrait;

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

    protected string $ns_creditnote_uri = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';

    /** @var bool True when the document is a <CreditNote> (type 381) instead of an <Invoice> */
    protected bool $isCreditNote = false;

    /** @var bool True once addBillingReference() wrote the reference to the credited invoice (BG-3) */
    protected bool $hasBillingReference = false;

    // Namespace prefixes
    protected string $ns_prefix_cac = 'cac';

    protected string $ns_prefix_cbc = 'cbc';

    // Tracking for NL-specific rules
    protected ?string $supplierCountryCode = null;

    protected ?string $customerCountryCode = null;

    protected bool $hasPaymentMeans = false;

    protected bool $hasOrderReference = false;

    protected bool $hasOrderLineReference = false;

    // The PartyLegalEntity elements, so a legal registration can be written after the party was added
    protected ?DOMElement $supplierLegalEntity = null;

    protected ?DOMElement $customerLegalEntity = null;

    /**
     * Legal registrations passed through addSupplierLegalRegistration() and
     * addCustomerLegalRegistration(), which win over what the party methods derive themselves.
     *
     * @var array{id: string, scheme: string}|null
     */
    protected ?array $supplierLegalRegistration = null;

    /** @var array{id: string, scheme: string}|null */
    protected ?array $customerLegalRegistration = null;

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
     * Create the base XML document structure for a credit note (<CreditNote>, type 381).
     *
     * Follow it with addCreditNoteHeader(), addBillingReference() and addCreditNoteLine(); every
     * other add...() method is the same as for an invoice.
     *
     * @throws \RuntimeException When document is already initialized
     */
    public function createCreditNoteDocument(): self
    {
        if (isset($this->rootElement)) {
            throw new \RuntimeException('Document is already initialized. Avoid initializing the document multiple times.');
        }

        $this->isCreditNote = true;

        $this->rootElement = $this->dom->createElementNS($this->ns_creditnote_uri, 'CreditNote');
        $this->rootElement->setAttribute('xmlns:cac', $this->ns_cac_uri);
        $this->rootElement->setAttribute('xmlns:cbc', $this->ns_cbc_uri);
        $this->rootElement->setAttribute('xmlns', $this->ns_creditnote_uri);

        $this->dom->appendChild($this->rootElement);

        return $this;
    }

    /**
     * Check if the current document is a credit note
     */
    public function isCreditNote(): bool
    {
        return $this->isCreditNote;
    }

    /**
     * Generate the XML string
     *
     * @return string The generated XML as a string
     *
     * @throws \RuntimeException If the document is not initialized
     */
    public function generateXml(bool $validateFirst = false): string
    {
        if ($validateFirst) {
            $validationResult = $this->validate();
            if (! $validationResult->isValid()) {
                throw new \InvalidArgumentException(
                    "UBL/Peppol validation failed:\n".$validationResult->getErrorsAsString("\n")
                );
            }
        }

        // arrangeInSchemaOrder() reads the root element, which only exists after createDocument()
        if (! isset($this->rootElement)) {
            throw new \RuntimeException('Root element is not initialized. Call createDocument() before adding elements.');
        }

        // Checked on every credit note, as the Belgian builder does: without the reference every
        // receiver rejects the document, and the rejection arrives after it was sent.
        if ($this->isCreditNote && ! $this->hasBillingReference) {
            throw new \InvalidArgumentException(self::MISSING_BILLING_REFERENCE);
        }

        $this->arrangeInSchemaOrder();

        $xml = $this->dom->saveXML();

        if ($xml === false) {
            throw new \RuntimeException('Could not serialise the document to XML.');
        }

        return $xml;
    }

    /**
     * The children of <Invoice> in the order the UBL 2.1 schema fixes.
     *
     * @var array<int, string>
     */
    protected const ROOT_ELEMENT_ORDER = [
        'UBLExtensions', 'UBLVersionID', 'CustomizationID', 'ProfileID', 'ProfileExecutionID', 'ID',
        'CopyIndicator', 'UUID', 'IssueDate', 'IssueTime', 'DueDate', 'InvoiceTypeCode', 'Note',
        'TaxPointDate', 'DocumentCurrencyCode', 'TaxCurrencyCode', 'PricingCurrencyCode',
        'PaymentCurrencyCode', 'PaymentAlternativeCurrencyCode', 'AccountingCostCode', 'AccountingCost',
        'LineCountNumeric', 'BuyerReference', 'InvoicePeriod', 'OrderReference', 'BillingReference',
        'DespatchDocumentReference', 'ReceiptDocumentReference', 'StatementDocumentReference',
        'OriginatorDocumentReference', 'ContractDocumentReference', 'AdditionalDocumentReference',
        'ProjectReference', 'Signature', 'AccountingSupplierParty', 'AccountingCustomerParty',
        'PayeeParty', 'BuyerCustomerParty', 'SellerSupplierParty', 'TaxRepresentativeParty', 'Delivery',
        'DeliveryTerms', 'PaymentMeans', 'PaymentTerms', 'PrepaidPayment', 'AllowanceCharge',
        'TaxExchangeRate', 'PricingExchangeRate', 'PaymentExchangeRate',
        'PaymentAlternativeExchangeRate', 'TaxTotal', 'WithholdingTaxTotal', 'LegalMonetaryTotal',
        'InvoiceLine',
    ];

    /**
     * The children of <CreditNote> in the order the UBL 2.1 schema fixes. It is not the order of
     * <Invoice>: there is no DueDate, the type code comes before Note, ContractDocumentReference and
     * AdditionalDocumentReference come before StatementDocumentReference, and AllowanceCharge comes
     * behind the exchange rates.
     *
     * @var array<int, string>
     */
    protected const CREDIT_NOTE_ROOT_ELEMENT_ORDER = [
        'UBLExtensions', 'UBLVersionID', 'CustomizationID', 'ProfileID', 'ProfileExecutionID', 'ID',
        'CopyIndicator', 'UUID', 'IssueDate', 'IssueTime', 'TaxPointDate', 'CreditNoteTypeCode', 'Note',
        'DocumentCurrencyCode', 'TaxCurrencyCode', 'PricingCurrencyCode', 'PaymentCurrencyCode',
        'PaymentAlternativeCurrencyCode', 'AccountingCostCode', 'AccountingCost', 'LineCountNumeric',
        'BuyerReference', 'InvoicePeriod', 'DiscrepancyResponse', 'OrderReference', 'BillingReference',
        'DespatchDocumentReference', 'ReceiptDocumentReference', 'ContractDocumentReference',
        'AdditionalDocumentReference', 'StatementDocumentReference', 'OriginatorDocumentReference',
        'Signature', 'AccountingSupplierParty', 'AccountingCustomerParty', 'PayeeParty',
        'BuyerCustomerParty', 'SellerSupplierParty', 'TaxRepresentativeParty', 'Delivery',
        'DeliveryTerms', 'PaymentMeans', 'PaymentTerms', 'TaxExchangeRate', 'PricingExchangeRate',
        'PaymentExchangeRate', 'PaymentAlternativeExchangeRate', 'AllowanceCharge', 'TaxTotal',
        'LegalMonetaryTotal', 'CreditNoteLine',
    ];

    protected const MISSING_BILLING_REFERENCE = '[BR-55] [NL-R-001] A credit note must reference the invoice it credits (BG-3). '
        .'Call addBillingReference($originalInvoiceNumber, $originalIssueDate) before generateXml().';

    /**
     * Put the children of <Invoice> in schema order, whatever the order of the add...() calls was.
     *
     * A receiver rejects a document with the right values in the wrong order, and its error names
     * the element, not the order. The sort is stable: elements of the same name (invoice lines,
     * document references, allowances) keep the order they were added in, and a document that was
     * built in schema order comes out byte for byte as before. An element this list does not know
     * stays behind the element it followed.
     */
    protected function arrangeInSchemaOrder(): void
    {
        $ranks = array_flip($this->isCreditNote ? self::CREDIT_NOTE_ROOT_ELEMENT_ORDER : self::ROOT_ELEMENT_ORDER);
        $children = [];
        $rank = -1;

        foreach (iterator_to_array($this->rootElement->childNodes) as $position => $node) {
            // The node name without its prefix. localName is empty for an element that was made
            // with createElement() instead of createElementNS(), so it cannot be used here.
            $name = $node instanceof DOMElement ? (string) preg_replace('/^.*:/', '', $node->nodeName) : null;

            if ($name !== null && isset($ranks[$name])) {
                $rank = $ranks[$name];
            }

            $children[] = ['node' => $node, 'rank' => $rank, 'position' => $position];
        }

        $sorted = $children;
        usort($sorted, fn (array $a, array $b): int => [$a['rank'], $a['position']] <=> [$b['rank'], $b['position']]);

        if ($sorted === $children) {
            return;
        }

        foreach ($sorted as $child) {
            $this->rootElement->appendChild($child['node']);
        }
    }

    /**
     * Validate basic code formats (currency, scheme IDs, payment means, unit codes, tax categories).
     */
    public function validate(): Validation\InvoiceValidationResult
    {
        $basicResult = UblValidator::validateBasicCodes([
            'currency_codes' => $this->usedCurrencyCodes,
            'scheme_ids' => $this->usedSchemeIds,
            'payment_means_codes' => $this->usedPaymentMeansCodes,
            'unit_codes' => $this->usedUnitCodes,
            'tax_category_ids' => $this->usedTaxCategoryIds,
        ]);

        $errors = $basicResult->errors;
        $warnings = $basicResult->warnings;

        // NL-R-002 to NL-R-005: the addresses and the legal registrations of the parties. They test
        // PartyLegalEntity/CompanyID, not the endpoint: an endpoint under 0088 or 9944 is fine.
        $document = $this->namespacedDocument();
        if ($document !== null) {
            $errors = array_merge($errors, UblValidator::validateDutchRules($document)->errors);
        }

        // BR-55, and NL-R-001 for a Dutch supplier: a credit note references the credited invoice
        if ($this->isCreditNote && ! $this->hasBillingReference) {
            $errors[] = self::MISSING_BILLING_REFERENCE;
        }

        // What the VAT categories demand of the document: breakdown, rates, VAT numbers, delivery
        $errors = array_merge($errors, $this->validateVatCategoriesOfDocument()->errors);

        // NL-R-007: Payment means required when payment is from customer to supplier
        if ($this->supplierCountryCode === 'NL' && ! $this->hasPaymentMeans) {
            $warnings[] = 'NL-R-007: PaymentMeans is required when payment is from customer to supplier.';
        }

        // NL-R-008: Allowed payment means codes for domestic NL invoices
        if ($this->supplierCountryCode === 'NL' && $this->customerCountryCode === 'NL') {
            $allowedCodes = ['30', '48', '49', '57', '58', '59'];
            foreach ($this->usedPaymentMeansCodes as $code) {
                if (! in_array($code, $allowedCodes, true)) {
                    $errors[] = "NL-R-008: Payment means code '{$code}' is not allowed for domestic NL invoices.";
                }
            }
        }

        // NL-R-009: Order line reference requires document-level order reference
        if ($this->supplierCountryCode === 'NL' && $this->hasOrderLineReference && ! $this->hasOrderReference) {
            $errors[] = 'NL-R-009: Order line reference requires a document-level OrderReference.';
        }

        if ($this->strictCodelistValidation) {
            if (! $this->codelistRegistry) {
                $errors[] = 'Strict codelist validation is enabled but no codelist registry is configured.';
            } else {
                $strictResult = UblValidator::validateStrictCodelists([
                    'currency_codes' => $this->usedCurrencyCodes,
                    'endpoint_scheme_ids' => $this->usedEndpointSchemeIds,
                    'party_scheme_ids' => $this->usedPartySchemeIds,
                    'registration_scheme_ids' => $this->usedRegistrationSchemeIds,
                    'payment_means_codes' => $this->usedPaymentMeansCodes,
                    'tax_category_ids' => $this->usedTaxCategoryIds,
                    'item_classification_ids' => [],
                    'tax_exemption_reason_codes' => [],
                    'allowance_reason_codes' => [],
                    'charge_reason_codes' => [],
                ], $this->codelistRegistry);

                $errors = array_merge($errors, $strictResult->errors);
            }
        }

        return new Validation\InvoiceValidationResult(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            corrections: []
        );
    }

    /**
     * Helper method to create and append a child element
     *
     * @param  DOMElement  $parent  The parent element
     * @param  string  $prefix  The namespace prefix (e.g., 'cbc' or 'cac')
     * @param  string  $name  The element name
     * @param  string|null  $value  The element value (optional)
     * @param  array  $attributes  Associative array of attributes (optional)
     * @return DOMElement The created and appended element
     */
    protected function addChildElement(DOMElement $parent, string $prefix, string $name, ?string $value = null, array $attributes = []): DOMElement
    {
        $element = $this->createElement($prefix, $name, $value, $attributes);
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Create an XML element with the given prefix, name, value, and attributes
     *
     * @param  string  $prefix  The namespace prefix (e.g., 'cbc' or 'cac')
     * @param  string  $name  The element name
     * @param  string|null  $value  The element value (optional)
     * @param  array  $attributes  Associative array of attributes (optional)
     * @return DOMElement The created DOMElement
     *
     * @throws \RuntimeException If the document is not initialized
     */
    protected function createElement(string $prefix, string $name, ?string $value = null, array $attributes = []): DOMElement
    {
        // Check if the DOM document exists
        if (! isset($this->dom)) {
            throw new \RuntimeException('DOM document is not initialized. Call createDocument() before adding elements.');
        }

        // Check if the rootElement exists
        if (! isset($this->rootElement)) {
            throw new \RuntimeException('Root element is not initialized. Call createDocument() before adding elements.');
        }

        // Create element without namespace declaration (uses inherited namespace)
        $element = $this->dom->createElement($prefix.':'.$name);

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
     * @param  string  $invoiceNumber  Invoice number (required, cannot be empty)
     * @param  string|\DateTimeInterface  $issueDate  Invoice date (required, format: YYYY-MM-DD)
     * @param  string|\DateTimeInterface  $dueDate  Due date (required, must be after invoice date)
     *
     * @throws \InvalidArgumentException On invalid input
     */
    public function addInvoiceHeader(string $invoiceNumber, $issueDate, $dueDate): self
    {
        if ($this->isCreditNote) {
            throw new \RuntimeException('This document is a credit note. Call addCreditNoteHeader() instead of addInvoiceHeader().');
        }

        $errors = [];

        // Validate invoice number
        $invoiceNumber = trim($invoiceNumber);
        if (empty($invoiceNumber)) {
            $errors[] = 'Invoice number is required and cannot be empty';
        } elseif (strlen($invoiceNumber) > 35) {
            $errors[] = 'Invoice number cannot exceed 35 characters';
        }

        // Validate and convert invoice date
        $issueDateObj = null;
        if ($issueDate instanceof \DateTimeInterface) {
            $issueDateObj = $issueDate;
            $issueDate = $issueDate->format('Y-m-d');
        } elseif (is_string($issueDate)) {
            $issueDate = trim($issueDate);
            $issueDateObj = \DateTime::createFromFormat('!Y-m-d', $issueDate);

            if (! $issueDateObj || $issueDateObj->format('Y-m-d') !== $issueDate) {
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

        // Validate and convert due date
        $dueDateObj = null;
        if ($dueDate instanceof \DateTimeInterface) {
            $dueDateObj = $dueDate;
            $dueDate = $dueDate->format('Y-m-d');
        } elseif (is_string($dueDate)) {
            $dueDate = trim($dueDate);
            $dueDateObj = \DateTime::createFromFormat('!Y-m-d', $dueDate);

            if (! $dueDateObj || $dueDateObj->format('Y-m-d') !== $dueDate) {
                $errors[] = 'Invalid due date. Please use YYYY-MM-DD format';
            } elseif (isset($issueDateObj) && $dueDateObj <= $issueDateObj) {
                $errors[] = 'Due date must be after the invoice date';
            }
        } else {
            $errors[] = 'Due date must be a string (YYYY-MM-DD) or DateTime object';
        }

        // Throw exception with all validation errors
        if (! empty($errors)) {
            $errorMessage = "Validation error(s) in invoice header:\n".
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

        // ID (invoice number)
        $idElement = $this->createElement('cbc', 'ID', $invoiceNumber);
        $this->rootElement->appendChild($idElement);

        // IssueDate (invoice date)
        $issueDateElement = $this->createElement('cbc', 'IssueDate', $issueDate);
        $this->rootElement->appendChild($issueDateElement);

        // DueDate (due date) - check if not empty
        // If empty, use a default date based on issueDate + 30 days
        if (empty($dueDate)) {
            // Use issueDate as base and add 30 days as default payment term
            try {
                $issueDateObj = new \DateTime($issueDate);
                $dueDateObj = clone $issueDateObj;
                $dueDateObj->modify('+30 days');
                $dueDate = $dueDateObj->format('Y-m-d');
            } catch (\Exception $e) {
                // If something goes wrong, use current date + 30 days as fallback
                $dueDate = (new \DateTime)->modify('+30 days')->format('Y-m-d');
            }
        } else {
            // Check if it's a valid date in Y-m-d format
            try {
                $dueDateObj = new \DateTime($dueDate);
                $dueDate = $dueDateObj->format('Y-m-d'); // Normalize to YYYY-MM-DD
            } catch (\Exception $e) {
                // If not a valid date, use current date + 30 days as fallback
                $dueDate = (new \DateTime)->modify('+30 days')->format('Y-m-d');
            }
        }

        // Now we're sure $dueDate is a valid date in the correct format
        $dueDateElement = $this->createElement('cbc', 'DueDate', $dueDate);
        $this->rootElement->appendChild($dueDateElement);

        // InvoiceTypeCode
        $invoiceTypeCodeElement = $this->createElement('cbc', 'InvoiceTypeCode', '380');
        $this->rootElement->appendChild($invoiceTypeCodeElement);

        // DocumentCurrencyCode
        $documentCurrencyCodeElement = $this->createElement('cbc', 'DocumentCurrencyCode', 'EUR');
        $this->rootElement->appendChild($documentCurrencyCodeElement);

        $this->usedCurrencyCodes[] = 'EUR';

        // AccountingCost (BT-19) is optional and the buyer's own booking reference, so it is only
        // written when the caller passes one through addAccountingCost().

        // BuyerReference is now added separately via addBuyerReference() to prevent duplicate elements

        return $this;
    }

    /**
     * Add the credit note header. A credit note has no due date: the CreditNote schema has no
     * cbc:DueDate under the root.
     *
     * @param  string  $creditNoteNumber  Credit note number (required, at most 35 characters)
     * @param  string|\DateTimeInterface  $issueDate  Issue date (YYYY-MM-DD or DateTime, not in the future)
     *
     * @throws \InvalidArgumentException On invalid input
     * @throws \RuntimeException When the document was not made with createCreditNoteDocument()
     */
    public function addCreditNoteHeader(string $creditNoteNumber, $issueDate): self
    {
        $this->requireCreditNoteDocument('addCreditNoteHeader()', 'addInvoiceHeader()');

        $errors = [];

        $creditNoteNumber = trim($creditNoteNumber);
        if ($creditNoteNumber === '') {
            $errors[] = 'Credit note number is required and cannot be empty';
        } elseif (strlen($creditNoteNumber) > 35) {
            $errors[] = 'Credit note number cannot exceed 35 characters';
        }

        if ($issueDate instanceof \DateTimeInterface) {
            $issueDate = $issueDate->format('Y-m-d');
        }

        // The "!" sets the time to midnight. Without it the parsed date carries the time of this
        // moment, and a document dated today compares later than "today" and is refused.
        $issueDate = trim($issueDate);
        $issueDateObj = \DateTime::createFromFormat('!Y-m-d', $issueDate);

        if (! $issueDateObj || $issueDateObj->format('Y-m-d') !== $issueDate) {
            $errors[] = 'Invalid issue date. Please use YYYY-MM-DD format or a DateTime object';
        } elseif ($issueDateObj > new \DateTime('today')) {
            $errors[] = 'Issue date cannot be in the future';
        }

        if (! empty($errors)) {
            throw new \InvalidArgumentException("Validation error(s) in credit note header:\n".implode("\n- ", array_merge([''], $errors)));
        }

        $this->addChildElement($this->rootElement, 'cbc', 'CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0');
        $this->addChildElement($this->rootElement, 'cbc', 'ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $this->addChildElement($this->rootElement, 'cbc', 'ID', $creditNoteNumber);
        $this->addChildElement($this->rootElement, 'cbc', 'IssueDate', $issueDate);

        // 381 = credit note. The amounts in the document are positive; this code makes it a credit.
        $this->addChildElement($this->rootElement, 'cbc', 'CreditNoteTypeCode', '381');
        $this->addChildElement($this->rootElement, 'cbc', 'DocumentCurrencyCode', 'EUR');

        $this->usedCurrencyCodes[] = 'EUR';

        return $this;
    }

    /**
     * Add the reference to the invoice a credit note credits (BG-3). BR-55 requires it on every
     * credit note and NL-R-001 repeats that for a Dutch supplier.
     *
     * @param  string  $originalInvoiceNumber  Number of the credited invoice (BT-25)
     * @param  string|null  $originalIssueDate  Issue date of that invoice, YYYY-MM-DD (BT-26, optional)
     *
     * @throws \InvalidArgumentException When the number is empty or the date is not YYYY-MM-DD
     * @throws \RuntimeException When the document is not initialized
     */
    public function addBillingReference(string $originalInvoiceNumber, ?string $originalIssueDate = null): self
    {
        if (! isset($this->rootElement)) {
            throw new \RuntimeException('Root element is not initialized. Call createCreditNoteDocument() before adding elements.');
        }

        $originalInvoiceNumber = trim($originalInvoiceNumber);
        if ($originalInvoiceNumber === '') {
            throw new \InvalidArgumentException('The number of the credited invoice is required and cannot be empty (BT-25).');
        }

        if ($originalIssueDate !== null && $originalIssueDate !== '') {
            $date = \DateTime::createFromFormat('!Y-m-d', $originalIssueDate);

            if (! $date || $date->format('Y-m-d') !== $originalIssueDate) {
                throw new \InvalidArgumentException('Invalid issue date of the credited invoice. Please use YYYY-MM-DD format (BT-26).');
            }
        }

        $this->hasBillingReference = true;

        $billingReference = $this->addChildElement($this->rootElement, 'cac', 'BillingReference');
        $invoiceDocumentReference = $this->addChildElement($billingReference, 'cac', 'InvoiceDocumentReference');
        $this->addChildElement($invoiceDocumentReference, 'cbc', 'ID', $originalInvoiceNumber);

        if ($originalIssueDate !== null && $originalIssueDate !== '') {
            $this->addChildElement($invoiceDocumentReference, 'cbc', 'IssueDate', $originalIssueDate);
        }

        return $this;
    }

    /**
     * Whether a party identifier may carry the scheme of the endpoint: when it is the endpoint
     * identifier itself, or when it has the format of that scheme. The scheme attribute is optional
     * (BT-46-1), so leaving it out is always valid; writing it on another kind of value is not.
     */
    private function partyIdFitsScheme(string $partyId, string $endpointId, string $schemeId): bool
    {
        $partyId = trim($partyId);

        if ($partyId === trim($endpointId)) {
            return true;
        }

        return match ($schemeId) {
            '0106' => preg_match('/^[0-9]{8}$/', $partyId) === 1,
            '0190' => preg_match('/^[0-9]{20}$/', $partyId) === 1,
            default => false,
        };
    }

    /**
     * @throws \RuntimeException When the document is missing or is an invoice
     */
    private function requireCreditNoteDocument(string $method, string $invoiceMethod): void
    {
        if (! isset($this->rootElement)) {
            throw new \RuntimeException('Root element is not initialized. Call createCreditNoteDocument() before adding elements.');
        }

        if (! $this->isCreditNote) {
            throw new \RuntimeException("This document is an invoice. Call {$invoiceMethod} instead of {$method}, or start with createCreditNoteDocument().");
        }
    }

    /**
     * Format an amount for use in UBL
     *
     * @param  float  $amount  Amount
     * @return string Formatted amount (2 decimals)
     */
    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Add the buyer accounting reference (BT-19): where the buyer books this invoice.
     *
     * Optional, at most one per document. A second call replaces the first. generateXml() puts the
     * element between DocumentCurrencyCode and BuyerReference, where the schema wants it.
     *
     * @throws \InvalidArgumentException When the value is empty
     */
    public function addAccountingCost(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Accounting cost cannot be empty');
        }

        foreach (iterator_to_array($this->rootElement->childNodes) as $node) {
            if ($node instanceof DOMElement && $node->nodeName === 'cbc:AccountingCost') {
                $this->rootElement->removeChild($node);
            }
        }

        $this->rootElement->appendChild($this->createElement('cbc', 'AccountingCost', $value));

        return $this;
    }

    /**
     * Set the legal registration identifier of the supplier (BT-30) and its scheme.
     *
     * For a Dutch supplier NL-R-003 wants a KvK number (scheme 0106) or an OIN (scheme 0190) here.
     * Call it before or after addAccountingSupplierParty(); it wins over what that method derives.
     *
     * @param  string  $identifier  The registration number, for example the 8 digit KvK number
     * @param  string  $schemeId  ISO 6523 ICD code of the register (BR-CL-11), 4 digits
     *
     * @throws \InvalidArgumentException When the identifier is empty or the scheme is not 4 digits
     */
    public function addSupplierLegalRegistration(string $identifier, string $schemeId = '0106'): self
    {
        $this->supplierLegalRegistration = $this->legalRegistration($identifier, $schemeId);

        if ($this->supplierLegalEntity !== null) {
            $this->writeLegalRegistration($this->supplierLegalEntity, $this->supplierLegalRegistration['id'], $this->supplierLegalRegistration['scheme']);
        }

        return $this;
    }

    /**
     * Set the legal registration identifier of the customer (BT-47) and its scheme.
     *
     * For a Dutch customer of a Dutch supplier NL-R-005 wants a KvK number (scheme 0106) or an OIN
     * (scheme 0190). Call it before or after addAccountingCustomerParty(); it wins over the
     * $companyId argument of that method.
     *
     * @param  string  $identifier  The registration number
     * @param  string  $schemeId  ISO 6523 ICD code of the register (BR-CL-11), 4 digits
     *
     * @throws \InvalidArgumentException When the identifier is empty or the scheme is not 4 digits
     */
    public function addCustomerLegalRegistration(string $identifier, string $schemeId = '0106'): self
    {
        $this->customerLegalRegistration = $this->legalRegistration($identifier, $schemeId);

        if ($this->customerLegalEntity !== null) {
            $this->writeLegalRegistration($this->customerLegalEntity, $this->customerLegalRegistration['id'], $this->customerLegalRegistration['scheme']);
        }

        return $this;
    }

    /**
     * @return array{id: string, scheme: string}
     */
    private function legalRegistration(string $identifier, string $schemeId): array
    {
        $identifier = trim($identifier);
        $schemeId = trim($schemeId);

        if ($identifier === '') {
            throw new \InvalidArgumentException('Legal registration identifier cannot be empty');
        }

        if (! UblValidator::isValidSchemeIdFormat($schemeId)) {
            throw new \InvalidArgumentException("Legal registration scheme must be a 4 digit ISO 6523 ICD code (e.g., '0106' for KVK, '0190' for OIN). Got: '{$schemeId}'");
        }

        return ['id' => $identifier, 'scheme' => $schemeId];
    }

    /**
     * Write cbc:CompanyID into a PartyLegalEntity, replacing one that is already there.
     * The schema puts it directly after cbc:RegistrationName, the only other child this builder writes.
     */
    private function writeLegalRegistration(DOMElement $legalEntity, string $identifier, ?string $schemeId): void
    {
        foreach (iterator_to_array($legalEntity->childNodes) as $node) {
            if ($node instanceof DOMElement && $node->nodeName === 'cbc:CompanyID') {
                $legalEntity->removeChild($node);
            }
        }

        $legalEntity->appendChild($this->createElement('cbc', 'CompanyID', $identifier, $schemeId !== null ? ['schemeID' => $schemeId] : []));

        if ($schemeId !== null) {
            $this->usedSchemeIds[] = $schemeId;
            $this->usedRegistrationSchemeIds[] = $schemeId;
        }
    }

    /**
     * Add BuyerReference (required for PEPPOL)
     *
     * @param  string|null  $buyerRef  Buyer reference (e.g., debtor number)
     */
    public function addBuyerReference(?string $buyerRef = 'BUYER_REF'): self
    {
        // Fallback to default value if null is passed
        $buyerRefValue = $buyerRef ?? 'BUYER_REF';

        $buyerRefElement = $this->createElement('cbc', 'BuyerReference', $buyerRefValue);
        $this->rootElement->appendChild($buyerRefElement);

        return $this;
    }

    /**
     * Add OrderReference to UBL document
     *
     * @param  string  $orderNumber  Order number reference
     */
    public function addOrderReference(string $orderNumber = 'PO-001'): self
    {
        $this->hasOrderReference = true;

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
     * @param  string  $id  The identifier of the referenced document.
     * @param  string|null  $documentType  The type of the referenced document.
     */
    public function addAdditionalDocumentReference(string $id, ?string $documentType = null): self
    {
        $docRef = $this->addChildElement($this->rootElement, 'cac', 'AdditionalDocumentReference');
        $this->addChildElement($docRef, 'cbc', 'ID', $id);

        return $this;
    }

    /**
     * Add AccountingSupplierParty (seller party)
     *
     * @param  string  $endpointId  Unique identifier of the supplier (e.g., Chamber of Commerce number)
     * @param  string  $endpointSchemeID  The scheme of the endpoint ID (e.g., '0106' for KVK)
     * @param  string  $partyId  Internal party identifier
     * @param  string  $partyName  Name of the supplier
     * @param  string  $street  Street name and number
     * @param  string  $postalCode  Postal code
     * @param  string  $city  City name
     * @param  string  $countryCode  Country code (2 letters, e.g., 'NL')
     * @param  string  $companyId  VAT number or other tax identification number
     * @param  string|null  $additionalStreet  Additional address line (optional)
     *
     * @throws \InvalidArgumentException On invalid input
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
        $this->supplierCountryCode = strtoupper($countryCode);
        $this->usedEndpointSchemeIds[] = $endpointSchemeID;
        $this->usedSchemeIds[] = $endpointSchemeID;

        // AccountingSupplierParty container
        $accountingSupplierParty = $this->createElement('cac', 'AccountingSupplierParty');
        $accountingSupplierParty = $this->rootElement->appendChild($accountingSupplierParty);

        // Party container
        $party = $this->createElement('cac', 'Party');
        $party = $accountingSupplierParty->appendChild($party);

        // Validate input
        $errors = [];

        // Collect all validation errors
        if (empty(trim($endpointId ?? ''))) {
            $errors[] = 'Endpoint ID (e.g., Chamber of Commerce number) is required';
        }
        if (empty(trim($endpointSchemeID ?? ''))) {
            $errors[] = 'Endpoint Scheme ID (e.g., "0106" for KVK) is required';
        }
        if (empty(trim($partyId ?? ''))) {
            $errors[] = 'Internal party ID is required';
        }
        if (empty(trim($partyName ?? ''))) {
            $errors[] = 'Company name is required';
        }
        if (empty(trim($street ?? ''))) {
            $errors[] = 'Street and house number are required';
        }
        if (empty(trim($postalCode ?? ''))) {
            $errors[] = 'Postal code is required';
        }
        if (empty(trim($city ?? ''))) {
            $errors[] = 'City name is required';
        }
        if (empty(trim($countryCode ?? ''))) {
            $errors[] = 'Country code is required';
        } elseif (strlen(trim($countryCode)) !== 2) {
            $errors[] = 'Country code must be exactly 2 characters (e.g., "NL")';
        }
        if (empty(trim($companyId ?? ''))) {
            $errors[] = 'VAT number or tax identification number is required';
        }

        // Throw exception with all validation errors
        if (! empty($errors)) {
            $errorMessage = "Validation error(s) in addAccountingSupplierParty():\n".
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

        // Country must be the last element inside PostalAddress
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

        // RegistrationName is the Seller name (BT-27)
        $registrationNameElement = $this->createElement('cbc', 'RegistrationName', $partyName);
        $partyLegalEntity->appendChild($registrationNameElement);

        $this->supplierLegalEntity = $partyLegalEntity;

        // Seller legal registration identifier (BT-30). NL-R-003 wants a KvK or OIN number here, so
        // the VAT number in $companyId does not belong under scheme 0106: it is already written as
        // BT-31 above. Only a caller who passed an 8 digit KvK number keeps the element they had.
        if ($this->supplierLegalRegistration !== null) {
            $this->writeLegalRegistration($partyLegalEntity, $this->supplierLegalRegistration['id'], $this->supplierLegalRegistration['scheme']);
        } elseif (preg_match('/^[0-9]{8}$/', trim($companyId)) === 1) {
            $this->writeLegalRegistration($partyLegalEntity, trim($companyId), '0106');
        }

        return $this;
    }

    /**
     * Add AccountingCustomerParty (customer information)
     *
     * @param  string  $endpointId  Customer's unique identifier (e.g., VAT number)
     * @param  string  $endpointSchemeID  Scheme of the endpoint ID (e.g., '0002' for GLN)
     * @param  string  $partyId  Internal party ID
     * @param  string  $partyName  Customer's company name
     * @param  string  $street  Street name and number
     * @param  string  $postalCode  Postal code
     * @param  string  $city  City name
     * @param  string  $countryCode  Country code (2 letters, e.g., 'NL')
     * @param  string|null  $additionalStreet  Additional address line (optional)
     * @param  string|null  $companyId  Company registration number (optional)
     *
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
        ?string $vatNumber = null,
        string $taxSchemeId = 'VAT'
    ): self {
        $this->customerCountryCode = strtoupper($countryCode);
        $this->usedEndpointSchemeIds[] = $endpointSchemeID;
        $this->usedSchemeIds[] = $endpointSchemeID;

        if (empty(trim($endpointId))) {
            throw new \InvalidArgumentException('Endpoint ID is required');
        }

        // CompanyId validation removed - this is a Chamber of Commerce number, not a VAT number
        // VAT number validation happens separately via vatNumber parameter

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
            'Country code' => $countryCode,
        ];

        foreach ($requiredFields as $field => $value) {
            if (empty(trim($value ?? ''))) {
                $errors[] = "$field is required";
            }
        }

        // Validate country code format
        if (! empty($countryCode) && strlen(trim($countryCode)) !== 2) {
            $errors[] = 'Country code must be exactly 2 characters (e.g., "NL")';
        }

        // Email and phone validation removed - accept all values or null
        // The calling code is responsible for validation if desired

        // Throw exception with all validation errors
        if (! empty($errors)) {
            $errorMessage = "Validation error(s) in customer information:\n".
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

        $this->usedSchemeIds[] = $effectiveSchemeID;
        $this->usedEndpointSchemeIds[] = $effectiveSchemeID;

        // EndpointID
        $endpointIDElement = $this->createElement('cbc', 'EndpointID', $endpointId, ['schemeID' => $effectiveSchemeID]);
        $party->appendChild($endpointIDElement);

        // PartyIdentification
        $partyIdentification = $this->createElement('cac', 'PartyIdentification');
        $partyIdentification = $party->appendChild($partyIdentification);

        // BT-46 is whatever the seller calls this buyer, often an internal customer number. The scheme
        // of the endpoint only belongs on it when it is that kind of identifier: the Schematron tests
        // the format of a value under a scheme (PEPPOL-COMMON-R054 for 0106, fatal R043 for 0208).
        $partyIdAttributes = $this->partyIdFitsScheme($partyId, $endpointId, $effectiveSchemeID)
            ? ['schemeID' => $effectiveSchemeID]
            : [];

        $idElement = $this->createElement('cbc', 'ID', $partyId, $partyIdAttributes);
        $partyIdentification->appendChild($idElement);

        if ($partyIdAttributes !== []) {
            $this->usedPartySchemeIds[] = $effectiveSchemeID;
        }

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

        // PartyTaxScheme - only add if VAT number is provided (BR-CO-09: must start with country code)
        if (! empty($vatNumber)) {
            // Validate that VAT number starts with a 2-letter country code
            if (! preg_match('/^[A-Z]{2}/', strtoupper($vatNumber))) {
                throw new \InvalidArgumentException(
                    "VAT number must start with a 2-letter ISO 3166-1 alpha-2 country code (e.g., 'NL', 'BE'). Got: '{$vatNumber}'"
                );
            }

            $partyTaxScheme = $this->createElement('cac', 'PartyTaxScheme');
            $partyTaxScheme = $party->appendChild($partyTaxScheme);

            $companyIDElement = $this->createElement('cbc', 'CompanyID', strtoupper($vatNumber));
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

        $this->customerLegalEntity = $partyLegalEntity;

        // Buyer legal registration identifier (BT-47). Scheme 0106 says "KvK number", which only a
        // Dutch customer has (NL-R-005); for another country the optional schemeID is left out. A
        // value with a country prefix is a VAT number (BT-48, the $vatNumber argument), not a
        // registration number, and is not written here.
        if ($this->customerLegalRegistration !== null) {
            $this->writeLegalRegistration($partyLegalEntity, $this->customerLegalRegistration['id'], $this->customerLegalRegistration['scheme']);
        } elseif (! empty($companyId) && preg_match('/^[A-Za-z]{2}/', trim($companyId)) !== 1) {
            $this->writeLegalRegistration($partyLegalEntity, $companyId, strtoupper($countryCode) === 'NL' ? '0106' : null);
        }

        // Only add a Contact element if at least one contact detail is provided
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
     * @param  string  $deliveryDate  Delivery date (required)
     * @param  string|null  $locationId  Unique ID for the delivery location (optional)
     * @param  string  $locationSchemeId  Scheme ID for the location (optional, default: '0088' for GLN)
     * @param  string|null  $street  Street name (optional)
     * @param  string|null  $additionalStreet  Additional street information (optional)
     * @param  string|null  $city  City (optional)
     * @param  string|null  $postalCode  Postal code (optional)
     * @param  string|null  $countryCode  Country code (2 letters) (optional)
     * @param  string|null  $partyName  Name of the receiving party (optional)
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
        $this->usedSchemeIds[] = $locationSchemeId;
        $this->usedPartySchemeIds[] = $locationSchemeId;

        // Delivery container
        $delivery = $this->createElement('cac', 'Delivery');
        $delivery = $this->rootElement->appendChild($delivery);

        // ActualDeliveryDate
        $actualDeliveryDateElement = $this->createElement('cbc', 'ActualDeliveryDate', $deliveryDate);
        $delivery->appendChild($actualDeliveryDateElement);

        // Only add DeliveryLocation if there is location data. A country alone counts: it is the
        // deliver to country (BT-80) that BR-IC-12 asks for an intra-community supply.
        if ($locationId !== null || $street !== null || $city !== null || $postalCode !== null || $countryCode !== null) {
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
     * @param  string  $iban  The IBAN to validate
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
        $moved = substr($iban, 4).substr($iban, 0, 4);

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
        return (int) bcmod($converted, '97') === 1;
    }

    /**
     * Add payment means to the invoice
     *
     * @param  string  $paymentMeansCode  Payment means code (e.g., '30' for credit transfer)
     * @param  string  $paymentMeansName  Payment means name (e.g., 'Credit transfer')
     * @param  string  $paymentId  Payment reference or ID
     * @param  string  $accountId  Bank account number (IBAN)
     * @param  string  $accountName  Name on the bank account
     * @param  string  $financialInstitutionId  BIC/SWIFT code of the financial institution
     * @param  string|null  $paymentChannelCode  Payment channel code (optional)
     * @param  string|null  $paymentDueDate  Payment due date in YYYY-MM-DD format (optional)
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
        $this->hasPaymentMeans = true;
        $this->usedPaymentMeansCodes[] = $paymentMeansCode;

        // Validate payment means code (should be a valid UNCL4461 code)
        if (! preg_match('/^[0-9]+$/', $paymentMeansCode)) {
            throw new \InvalidArgumentException('Payment means code must be a numeric value');
        }

        // Validate IBAN if provided
        if ($accountId !== null && ! $this->isValidIban($accountId)) {
            throw new \InvalidArgumentException('Invalid IBAN format');
        }

        // Validate BIC/SWIFT if provided
        if ($financialInstitutionId !== null && ! preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $financialInstitutionId)) {
            throw new \InvalidArgumentException('Invalid BIC/SWIFT code format');
        }

        // Create payment means element
        $paymentMeans = $this->createElement('cac', 'PaymentMeans');

        // Add payment means code with name
        $this->addChildElement($paymentMeans, 'cbc', 'PaymentMeansCode', $paymentMeansCode);

        // Add payment ID if provided
        if ($paymentId !== null) {
            $this->addChildElement($paymentMeans, 'cbc', 'PaymentID', $paymentId);
        }

        // PaymentChannelCode is not included as per UBL-CR-413
        // PaymentDueDate is not included as per UBL-CR-412 (must be at invoice level)

        // Add payee financial account if account ID is provided
        if ($accountId !== null) {
            $payeeFinancialAccount = $this->createElement('cac', 'PayeeFinancialAccount');

            // Add account ID (IBAN) without a schemeID (UBL-CR-654)
            $this->addChildElement($payeeFinancialAccount, 'cbc', 'ID', $accountId);

            // Add financial institution branch (BIC/SWIFT) if provided
            if ($financialInstitutionId !== null) {
                $financialInstitutionBranch = $this->createElement('cac', 'FinancialInstitutionBranch');
                $this->addChildElement($financialInstitutionBranch, 'cbc', 'ID', $financialInstitutionId);
                $payeeFinancialAccount->appendChild($financialInstitutionBranch);
            }

            $paymentMeans->appendChild($payeeFinancialAccount);
        }

        $this->rootElement->appendChild($paymentMeans);

        return $this;
    }

    /**
     * Add payment terms to the invoice
     *
     * @param  string|null  $note  The payment terms (e.g., 'Payment within 30 days, 2% discount if paid within 10 days')
     * @param  string|null  $settlementDiscountPercent  The discount percentage for early payment (e.g., '2.00')
     * @param  string|null  $settlementDiscountAmount  The discount amount for early payment (e.g., '10.00')
     * @param  string|null  $settlementDiscountDate  Due date for the discount (e.g., '2025-10-15')
     *
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
     * @param  bool  $isCharge  True for a charge, false for an allowance
     * @param  float  $amount  The amount of the allowance or charge
     * @param  string  $reason  Reason for the allowance/charge (e.g., 'Insurance', 'Freight', 'Discount')
     * @param  string  $taxCategoryId  Tax category ID (e.g., 'S' for standard rate, 'Z' for zero rate)
     * @param  float  $taxPercent  Tax percentage (e.g., 21.0 for 21%)
     * @param  string  $currency  Currency code (default: 'EUR')
     *
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
        $this->usedCurrencyCodes[] = $currency;
        $this->usedTaxCategoryIds[] = $taxCategoryId;

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
        $amountElement = $this->createElement('cbc', 'Amount', (string) number_format($amount, 2, '.', ''), ['currencyID' => $currency]);
        $allowanceCharge->appendChild($amountElement);

        // TaxCategory is mandatory on every document level allowance (BT-95, BR-32) and charge
        // (BT-102, BR-37), also at 0%: a zero rated, exempt or reverse charge amount has a category
        // and a rate of 0 (BR-Z-06/07, BR-E-06/07, BR-AE-06/07).
        $taxCategory = $this->createElement('cac', 'TaxCategory');
        $taxCategory = $allowanceCharge->appendChild($taxCategory);

        // Tax category ID (e.g., 'S' for standard rate, 'Z' for zero rate)
        $idElement = $this->createElement('cbc', 'ID', $taxCategoryId);
        $taxCategory->appendChild($idElement);

        // Tax percentage. "Not subject to VAT" (O) shall not carry a rate (BR-O-06, BR-O-07).
        if (strtoupper($taxCategoryId) !== 'O') {
            $percentElement = $this->createElement('cbc', 'Percent', (string) number_format($taxPercent, 2, '.', ''));
            $taxCategory->appendChild($percentElement);
        }

        // Tax scheme (always VAT for this implementation)
        $taxScheme = $this->createElement('cac', 'TaxScheme');
        $taxScheme = $taxCategory->appendChild($taxScheme);

        $taxSchemeIDElement = $this->createElement('cbc', 'ID', 'VAT');
        $taxScheme->appendChild($taxSchemeIDElement);

        return $this;
    }

    /**
     * Add tax total information to the invoice
     *
     * @param  array  $taxes  Array of tax entries with the following structure:
     *                        [
     *                        [
     *                        'taxable_amount' => 1000.00, // Required: Amount subject to tax (must be >= 0)
     *                        'tax_amount' => 210.00,      // Required: Tax amount (must be >= 0)
     *                        'currency' => 'EUR',         // Required: Currency code (3 letters)
     *                        'tax_category_id' => 'S',    // Required: Tax category ID (e.g., 'S' for standard rate)
     *                        'tax_category_name' => 'Standard rated', // Optional: The name of the tax category
     *                        'tax_percent' => 21.0,       // Required: Tax percentage (0-100)
     *                        'tax_scheme_id' => 'VAT',    // Required: Tax scheme ID (e.g., 'VAT')
     *                        'tax_exemption_reason_code' => 'VATEX-EU-IC', // Optional: BT-121, see VatExemptionReason
     *                        'tax_exemption_reason' => 'Intracommunautaire levering', // Optional: BT-120, free text
     *                        ]
     *                        ]
     *
     * The categories and what each demands are explained by Vat\VatCategory. A category that needs an
     * exemption reason (E, AE, K, G, O) and gets none is written with the code that belongs to it
     * (VATEX-EU-AE, VATEX-EU-IC, VATEX-EU-G, VATEX-EU-O); for E validate() reports the missing reason.
     *
     * @throws \InvalidArgumentException For invalid or missing required fields, and for an exemption
     *                                   reason the category does not allow (see UblValidator::resolveTaxExemption())
     */
    public function addTaxTotal(array $taxes): self
    {
        if (empty($taxes)) {
            throw new \InvalidArgumentException('At least one tax entry is required');
        }

        foreach ($taxes as $tax) {
            if (isset($tax['currency'])) {
                $this->usedCurrencyCodes[] = $tax['currency'];
            }
            if (isset($tax['tax_category_id'])) {
                $this->usedTaxCategoryIds[] = $tax['tax_category_id'];
            }
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
                'tax_scheme_id' => 'Tax scheme ID is required',
            ];

            foreach ($requiredFields as $field => $errorMessage) {
                if (! array_key_exists($field, $tax)) {
                    throw new \InvalidArgumentException($errorPrefix.$errorMessage);
                }
            }

            // Validate field types and values
            if (! is_numeric($tax['taxable_amount']) || $tax['taxable_amount'] < 0) {
                throw new \InvalidArgumentException($errorPrefix.'Taxable amount must be a non-negative number');
            }

            if (! is_numeric($tax['tax_amount']) || $tax['tax_amount'] < 0) {
                throw new \InvalidArgumentException($errorPrefix.'Tax amount must be a non-negative number');
            }

            if (! is_string($tax['tax_category_id']) || empty(trim($tax['tax_category_id']))) {
                throw new \InvalidArgumentException($errorPrefix.'Tax category ID must be a non-empty string');
            }

            if (! is_numeric($tax['tax_percent']) || $tax['tax_percent'] < 0 || $tax['tax_percent'] > 100) {
                throw new \InvalidArgumentException($errorPrefix.'Tax percentage must be a number between 0 and 100');
            }

            if (! is_string($tax['currency']) || strlen($tax['currency']) !== 3) {
                throw new \InvalidArgumentException($errorPrefix.'Currency code must be exactly 3 characters long');
            }

            if (! is_string($tax['tax_scheme_id']) || empty(trim($tax['tax_scheme_id']))) {
                throw new \InvalidArgumentException($errorPrefix.'Tax scheme ID must be a non-empty string');
            }

            $totalTaxAmount += (float) $tax['tax_amount'];
        }

        // BT-120 and BT-121, settled before anything is written so a refused reason leaves no half document
        $exemptions = array_map(fn (array $tax) => UblValidator::resolveTaxExemption(
            (string) $tax['tax_category_id'],
            isset($tax['tax_exemption_reason_code']) ? (string) $tax['tax_exemption_reason_code'] : null,
            isset($tax['tax_exemption_reason']) ? (string) $tax['tax_exemption_reason'] : null
        ), $taxes);

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
        foreach ($taxes as $index => $tax) {
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

            // Add tax percentage; category O carries none (BR-48 allows leaving it out, as BR-O-05 demands on the lines)
            if (strtoupper($tax['tax_category_id']) !== 'O') {
                $percentElement = $this->createElement(
                    'cbc',
                    'Percent',
                    number_format($tax['tax_percent'], 2, '.', '')
                );
                $taxCategory->appendChild($percentElement);
            }

            // Exemption reason code and text, between Percent and TaxScheme as the UBL schema orders them
            if ($exemptions[$index]['code'] !== null) {
                $taxCategory->appendChild($this->createElement('cbc', 'TaxExemptionReasonCode', $exemptions[$index]['code']));
            }

            if ($exemptions[$index]['text'] !== null) {
                $taxCategory->appendChild($this->createElement('cbc', 'TaxExemptionReason', $exemptions[$index]['text']));
            }

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
     * @param  array  $amounts  Associative array containing the following required keys:
     *                          - line_extension_amount: Total of all invoice lines excluding tax
     *                          - tax_exclusive_amount: Amount excluding tax (line_extension_amount + charges - allowances)
     *                          - tax_inclusive_amount: Amount including tax
     *                          - charge_total_amount: Total of all charges
     *                          - payable_amount: Total amount to be paid (tax_inclusive_amount - prepaid_amount)
     *                          and these optional keys, written when they are more than zero:
     *                          - allowance_total_amount: Total of all document level allowances (BT-107)
     *                          - prepaid_amount: Amount already paid (BT-113)
     * @param  string  $currency  Currency code (3 letters, e.g., 'EUR')
     *
     * @throws \InvalidArgumentException For missing or invalid parameters
     */
    public function addLegalMonetaryTotal(array $amounts, string $currency = 'EUR'): self
    {
        $this->usedCurrencyCodes[] = $currency;

        // Validate required fields
        $requiredFields = [
            'line_extension_amount' => 'Line extension amount is required',
            'tax_exclusive_amount' => 'Tax exclusive amount is required',
            'tax_inclusive_amount' => 'Tax inclusive amount is required',
            'charge_total_amount' => 'Charge total amount is required',
            'payable_amount' => 'Payable amount is required',
        ];

        foreach ($requiredFields as $field => $errorMessage) {
            if (! array_key_exists($field, $amounts) || ! is_numeric($amounts[$field])) {
                throw new \InvalidArgumentException($errorMessage);
            }
        }

        // Optional amounts: numeric when present
        $optionalAmounts = [];
        foreach (['allowance_total_amount' => 'Allowance total amount', 'prepaid_amount' => 'Prepaid amount'] as $field => $label) {
            if (isset($amounts[$field]) && ! is_numeric($amounts[$field])) {
                throw new \InvalidArgumentException($label.' must be numeric');
            }

            $optionalAmounts[$field] = (float) ($amounts[$field] ?? 0);
        }

        // Validate currency
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency code must be exactly 3 characters long');
        }

        // Format amounts to 2 decimal places
        $formattedAmounts = [];
        foreach ($amounts as $key => $value) {
            $formattedAmounts[$key] = number_format((float) $value, 2, '.', '');
        }

        // Create LegalMonetaryTotal container
        $legalMonetaryTotal = $this->createElement('cac', 'LegalMonetaryTotal');
        $legalMonetaryTotal = $this->rootElement->appendChild($legalMonetaryTotal);

        // Add all monetary amounts with currency, in the order the schema fixes. The allowance
        // total (BT-107) and the paid amount (BT-113) are optional and only written when they are
        // more than zero, like the Belgian builder does; without them BR-CO-13 and BR-CO-16 cannot
        // hold for an invoice with a discount or a prepayment.
        $elements = [
            'LineExtensionAmount' => $formattedAmounts['line_extension_amount'],
            'TaxExclusiveAmount' => $formattedAmounts['tax_exclusive_amount'],
            'TaxInclusiveAmount' => $formattedAmounts['tax_inclusive_amount'],
        ];

        if ($optionalAmounts['allowance_total_amount'] > 0.001) {
            $elements['AllowanceTotalAmount'] = $formattedAmounts['allowance_total_amount'];
        }

        $elements['ChargeTotalAmount'] = $formattedAmounts['charge_total_amount'];

        if ($optionalAmounts['prepaid_amount'] > 0.001) {
            $elements['PrepaidAmount'] = $formattedAmounts['prepaid_amount'];
        }

        $elements['PayableAmount'] = $formattedAmounts['payable_amount'];

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
     * @param  array  $lineData  Array containing the invoice line data
     *
     * @throws \InvalidArgumentException For missing or invalid parameters
     */
    /**
     * Add an invoice line to the document
     *
     * @param  array  $lineData  Array containing the invoice line data
     *
     * @throws \InvalidArgumentException For missing or invalid parameters
     */
    public function addInvoiceLine(array $lineData): self
    {
        if ($this->isCreditNote) {
            throw new \RuntimeException('This document is a credit note. Call addCreditNoteLine() instead of addInvoiceLine().');
        }

        // PEPPOL BR-27: Item net price (BT-146) shall NOT be negative
        // Validate price_amount before processing
        if (isset($lineData['price_amount']) && (float) $lineData['price_amount'] < 0) {
            $description = $lineData['description'] ?? $lineData['name'] ?? 'Unknown item';
            throw new \InvalidArgumentException(
                "PEPPOL BR-27 Validation Error: Item net price (BT-146) shall NOT be negative.\n".
                "Item: \"{$description}\"\n".
                "Price: {$lineData['price_amount']}\n\n".
                'Fix: add a negative amount, a discount, with addAllowanceCharge(false, ...), not as an invoice line.'
            );
        }

        if (isset($lineData['currency'])) {
            $this->usedCurrencyCodes[] = $lineData['currency'];
        }
        if (isset($lineData['unit_code'])) {
            $this->usedUnitCodes[] = $lineData['unit_code'];
        }
        if (isset($lineData['tax_category_id'])) {
            $this->usedTaxCategoryIds[] = $lineData['tax_category_id'];
        }

        if (! empty($lineData['order_line_id'])) {
            $this->hasOrderLineReference = true;
        }

        // Set default values
        $lineData = array_merge([
            'tax_category_id' => 'S',
            'tax_percent' => '21.00',
        ], $lineData);

        // Create InvoiceLine container
        $invoiceLine = $this->createElement('cac', 'InvoiceLine');
        $this->rootElement->appendChild($invoiceLine);

        // Add ID
        $this->addChildElement($invoiceLine, 'cbc', 'ID', $lineData['id']);

        // Add InvoicedQuantity
        $this->addChildElement(
            $invoiceLine,
            'cbc',
            'InvoicedQuantity',
            number_format((float) $lineData['quantity'], 2, '.', ''),
            ['unitCode' => $lineData['unit_code']]
        );

        // Add LineExtensionAmount: use the given value, or calculate it as a fallback
        $lineExtensionAmount = $lineData['line_extension_amount']
            ?? ((isset($lineData['price_amount'], $lineData['quantity']))
                ? (float) $lineData['price_amount'] * (float) $lineData['quantity']
                : null);

        if ($lineExtensionAmount === null) {
            throw new \InvalidArgumentException('Invoice line requires line_extension_amount or both price_amount and quantity to derive it.');
        }

        // PEPPOL BR-27: Also validate the calculated line_extension_amount
        if ((float) $lineExtensionAmount < 0) {
            $description = $lineData['description'] ?? $lineData['name'] ?? 'Unknown item';
            throw new \InvalidArgumentException(
                "PEPPOL BR-27 Validation Error: Line extension amount shall NOT be negative.\n".
                "Item: \"{$description}\"\n".
                "Line Extension Amount: {$lineExtensionAmount}\n\n".
                'Fix: add a negative amount, a discount, with addAllowanceCharge(false, ...), not as an invoice line.'
            );
        }

        $this->addChildElement($invoiceLine, 'cbc', 'LineExtensionAmount', $this->formatAmount((float) $lineExtensionAmount), ['currencyID' => $lineData['currency']]);

        // AccountingCost
        if (isset($lineData['accounting_cost'])) {
            $this->addChildElement($invoiceLine, 'cbc', 'AccountingCost', $lineData['accounting_cost']);
        }

        // OrderLineReference
        if (isset($lineData['order_line_id'])) {
            $orderLineReference = $this->createElement('cac', 'OrderLineReference');
            $invoiceLine->appendChild($orderLineReference);
            $this->addChildElement($orderLineReference, 'cbc', 'LineID', $lineData['order_line_id']);
        }

        // Item
        $item = $this->createElement('cac', 'Item');
        $invoiceLine->appendChild($item);

        $this->addChildElement($item, 'cbc', 'Description', $lineData['description']);
        $this->addChildElement($item, 'cbc', 'Name', $lineData['name']);

        // Item > ClassifiedTaxCategory
        $classifiedTaxCategory = $this->createElement('cac', 'ClassifiedTaxCategory');
        $item->appendChild($classifiedTaxCategory);
        $this->addChildElement($classifiedTaxCategory, 'cbc', 'ID', $lineData['tax_category_id']);
        // BR-O-05: a line in category O carries no VAT rate
        if (strtoupper($lineData['tax_category_id']) !== 'O') {
            $this->addChildElement($classifiedTaxCategory, 'cbc', 'Percent', $this->formatAmount($lineData['tax_percent']));
        }
        $taxScheme = $this->addChildElement($classifiedTaxCategory, 'cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', 'VAT');

        // Price
        $price = $this->createElement('cac', 'Price');
        $invoiceLine->appendChild($price);

        $this->addChildElement($price, 'cbc', 'PriceAmount', $this->formatAmount($lineData['price_amount']), ['currencyID' => $lineData['currency']]);

        $baseQuantityValue = $lineData['base_quantity'] ?? 1;
        $this->addChildElement($price, 'cbc', 'BaseQuantity', number_format((float) $baseQuantityValue, 2, '.', ''), ['unitCode' => $lineData['unit_code']]);

        return $this;
    }

    /**
     * Add a credit note line to the document.
     *
     * Takes the same keys as addInvoiceLine(). The amounts and the quantity are written as positive
     * numbers, as the Belgian builder does: the type code 381 makes the document a credit, BR-27
     * forbids a negative price, and a host app usually holds a credit note with negative amounts.
     *
     * @param  array<string, mixed>  $lineData  id, quantity, unit_code, price_amount, currency, name,
     *                                          description and optionally line_extension_amount, accounting_cost,
     *                                          order_line_id, tax_category_id, tax_percent, base_quantity
     *
     * @throws \InvalidArgumentException For missing parameters
     * @throws \RuntimeException When the document was not made with createCreditNoteDocument()
     */
    public function addCreditNoteLine(array $lineData): self
    {
        $this->requireCreditNoteDocument('addCreditNoteLine()', 'addInvoiceLine()');

        $lineData = array_merge([
            'currency' => 'EUR',
            'tax_category_id' => 'S',
            'tax_percent' => '21.00',
            'unit_code' => 'C62',
        ], $lineData);

        $lineExtensionAmount = $lineData['line_extension_amount']
            ?? ((isset($lineData['price_amount'], $lineData['quantity']))
                ? (float) $lineData['price_amount'] * (float) $lineData['quantity']
                : null);

        if ($lineExtensionAmount === null || ! isset($lineData['price_amount'], $lineData['quantity'])) {
            throw new \InvalidArgumentException('Credit note line requires price_amount and quantity.');
        }

        $this->usedCurrencyCodes[] = $lineData['currency'];
        $this->usedUnitCodes[] = $lineData['unit_code'];
        $this->usedTaxCategoryIds[] = $lineData['tax_category_id'];

        if (! empty($lineData['order_line_id'])) {
            $this->hasOrderLineReference = true;
        }

        $creditNoteLine = $this->addChildElement($this->rootElement, 'cac', 'CreditNoteLine');

        $this->addChildElement($creditNoteLine, 'cbc', 'ID', (string) $lineData['id']);
        $this->addChildElement($creditNoteLine, 'cbc', 'CreditedQuantity', $this->formatAmount(abs((float) $lineData['quantity'])), ['unitCode' => $lineData['unit_code']]);
        $this->addChildElement($creditNoteLine, 'cbc', 'LineExtensionAmount', $this->formatAmount(abs((float) $lineExtensionAmount)), ['currencyID' => $lineData['currency']]);

        if (! empty($lineData['accounting_cost'])) {
            $this->addChildElement($creditNoteLine, 'cbc', 'AccountingCost', $lineData['accounting_cost']);
        }

        if (! empty($lineData['order_line_id'])) {
            $orderLineReference = $this->addChildElement($creditNoteLine, 'cac', 'OrderLineReference');
            $this->addChildElement($orderLineReference, 'cbc', 'LineID', $lineData['order_line_id']);
        }

        $item = $this->addChildElement($creditNoteLine, 'cac', 'Item');
        $this->addChildElement($item, 'cbc', 'Description', $lineData['description'] ?? '');
        $this->addChildElement($item, 'cbc', 'Name', $lineData['name'] ?? $lineData['description'] ?? '');

        $classifiedTaxCategory = $this->addChildElement($item, 'cac', 'ClassifiedTaxCategory');
        $this->addChildElement($classifiedTaxCategory, 'cbc', 'ID', $lineData['tax_category_id']);
        // BR-O-05: a line in category O carries no VAT rate
        if (strtoupper($lineData['tax_category_id']) !== 'O') {
            $this->addChildElement($classifiedTaxCategory, 'cbc', 'Percent', $this->formatAmount((float) $lineData['tax_percent']));
        }
        $taxScheme = $this->addChildElement($classifiedTaxCategory, 'cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', 'VAT');

        $price = $this->addChildElement($creditNoteLine, 'cac', 'Price');
        $this->addChildElement($price, 'cbc', 'PriceAmount', $this->formatAmount(abs((float) $lineData['price_amount'])), ['currencyID' => $lineData['currency']]);
        $this->addChildElement($price, 'cbc', 'BaseQuantity', number_format((float) ($lineData['base_quantity'] ?? 1), 2, '.', ''), ['unitCode' => $lineData['unit_code']]);

        return $this;
    }
}
