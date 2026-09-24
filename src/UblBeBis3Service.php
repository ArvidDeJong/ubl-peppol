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
class UblBeBis3Service
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

    // Document type tracking
    protected bool $isCreditNote = false;

    // Track if billing reference was added (required for credit notes - BR-55)
    protected bool $hasBillingReference = false;

    // Namespace prefixes
    protected string $ns_prefix_cac = 'cac';

    protected string $ns_prefix_cbc = 'cbc';

    // Tracking arrays for validation
    protected array $invoiceLines = [];

    protected array $totals = [];

    protected array $taxTotals = [];

    protected float $allowanceTotalAmount = 0.0;

    protected float $chargeTotalAmount = 0.0;

    protected float $prepaidAmount = 0.0;

    /**
     * Document level allowances (BG-20) and charges (BG-21) added through addAllowanceCharge(), so
     * validate() can check BR-CO-11, BR-CO-12 and the taxable amounts per VAT category against them.
     *
     * @var array<int, array{amount: float, tax_category_id: string, tax_percent: float}>
     */
    protected array $documentAllowances = [];

    /** @var array<int, array{amount: float, tax_category_id: string, tax_percent: float}> */
    protected array $documentCharges = [];

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
     * Create the base XML document structure for a Credit Note
     *
     * @throws \RuntimeException When document is already initialized
     */
    public function createCreditNoteDocument(): self
    {
        // Prevent double initialization of the document
        if (isset($this->rootElement)) {
            throw new \RuntimeException('Document is already initialized. Avoid initializing the document multiple times.');
        }

        // Mark this as a credit note
        $this->isCreditNote = true;

        // Create root element (CreditNote)
        $this->rootElement = $this->dom->createElementNS($this->ns_creditnote_uri, 'CreditNote');
        $this->rootElement->setAttribute('xmlns:cac', $this->ns_cac_uri);
        $this->rootElement->setAttribute('xmlns:cbc', $this->ns_cbc_uri);
        $this->rootElement->setAttribute('xmlns', $this->ns_creditnote_uri);

        // Add root element to the document
        $this->dom->appendChild($this->rootElement);

        return $this;
    }

    /**
     * Add Credit Note header information
     *
     * @param  string  $creditNoteNumber  The credit note number
     * @param  string|\DateTime  $issueDate  Issue date (YYYY-MM-DD or DateTime)
     */
    public function addCreditNoteHeader(string $creditNoteNumber, $issueDate): self
    {
        $errors = [];

        // Validate credit note number
        $creditNoteNumber = trim($creditNoteNumber);
        if (empty($creditNoteNumber)) {
            $errors[] = 'Credit note number is required and cannot be empty';
        } elseif (strlen($creditNoteNumber) > 35) {
            $errors[] = 'Credit note number cannot exceed 35 characters';
        }

        // Validate and convert issue date
        if ($issueDate instanceof \DateTime) {
            $issueDate = $issueDate->format('Y-m-d');
        } elseif (is_string($issueDate)) {
            $issueDate = trim($issueDate);
            $issueDateObj = \DateTime::createFromFormat('!Y-m-d', $issueDate);
            if (! $issueDateObj || $issueDateObj->format('Y-m-d') !== $issueDate) {
                $errors[] = 'Invalid issue date. Please use YYYY-MM-DD format';
            }
        } else {
            $errors[] = 'Issue date must be a string (YYYY-MM-DD) or DateTime object';
        }

        if (! empty($errors)) {
            throw new \InvalidArgumentException("Validation error(s) in credit note header:\n".implode("\n- ", array_merge([''], $errors)));
        }

        // CustomizationID - PEPPOL profile (same as invoice)
        $this->addChildElement($this->rootElement, 'cbc', 'CustomizationID',
            'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0');

        // ProfileID
        $this->addChildElement($this->rootElement, 'cbc', 'ProfileID',
            'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');

        // ID (credit note number)
        $this->addChildElement($this->rootElement, 'cbc', 'ID', $creditNoteNumber);

        // IssueDate
        $this->addChildElement($this->rootElement, 'cbc', 'IssueDate', $issueDate);

        // CreditNoteTypeCode (381 = Credit note)
        $this->addChildElement($this->rootElement, 'cbc', 'CreditNoteTypeCode', '381');

        // DocumentCurrencyCode
        $this->addChildElement($this->rootElement, 'cbc', 'DocumentCurrencyCode', 'EUR');

        $this->usedCurrencyCodes[] = 'EUR';

        return $this;
    }

    /**
     * Add Billing Reference - REQUIRED for credit notes (BR-55)
     * References the original invoice being credited
     *
     * @param  string  $originalInvoiceNumber  The original invoice number
     * @param  string|null  $originalIssueDate  Original invoice issue date (YYYY-MM-DD)
     */
    public function addBillingReference(string $originalInvoiceNumber, ?string $originalIssueDate = null): self
    {
        // Track that billing reference was added
        $this->hasBillingReference = true;

        $billingReference = $this->addChildElement($this->rootElement, 'cac', 'BillingReference');
        $invoiceDocRef = $this->addChildElement($billingReference, 'cac', 'InvoiceDocumentReference');
        $this->addChildElement($invoiceDocRef, 'cbc', 'ID', $originalInvoiceNumber);

        if ($originalIssueDate) {
            $this->addChildElement($invoiceDocRef, 'cbc', 'IssueDate', $originalIssueDate);
        }

        return $this;
    }

    /**
     * Check if current document is a credit note
     */
    public function isCreditNote(): bool
    {
        return $this->isCreditNote;
    }

    /**
     * Generate the XML string
     *
     * @param  bool  $validateFirst  If true, validates the invoice before generating XML
     * @return string The generated XML as a string
     *
     * @throws \RuntimeException If the document is not initialized
     * @throws \InvalidArgumentException If validation fails and $validateFirst is true
     */
    public function generateXml(bool $validateFirst = false): string
    {
        // Always validate credit note specific rules
        if ($this->isCreditNote) {
            $this->validateCreditNote();
        }

        if ($validateFirst) {
            $validationResult = $this->validate();
            if (! $validationResult->isValid()) {
                throw new \InvalidArgumentException(
                    "UBL/Peppol validation failed:\n".$validationResult->getErrorsAsString("\n").
                    "\n\nSuggested corrections:\n".json_encode($validationResult->getCorrections(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }
        }

        $xml = $this->dom->saveXML();

        if ($xml === false) {
            throw new \RuntimeException('Could not serialise the document to XML.');
        }

        return $xml;
    }

    /**
     * Validate Credit Note specific PEPPOL/EN16931 rules
     *
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateCreditNote(): void
    {
        $errors = [];

        // BR-55: A Credit Note SHALL have a preceding invoice reference
        if (! $this->hasBillingReference) {
            $errors[] = "[BR-55] PEPPOL Credit Note MUST have a BillingReference.\n".
                "A Credit Note SHALL have a preceding invoice reference (BG-3).\n".
                'Solution: Call addBillingReference($originalInvoiceNumber, $originalIssueDate) before generateXml().';
        }

        // Check for negative amounts in lines (they should be positive in credit notes)
        foreach ($this->invoiceLines as $index => $line) {
            $lineAmount = (float) ($line['line_extension_amount'] ?? 0);
            $priceAmount = (float) ($line['price_amount'] ?? 0);
            $quantity = (float) ($line['quantity'] ?? 0);

            if ($lineAmount < 0) {
                $errors[] = '[BR-CN-01] Credit Note line '.($index + 1)." has negative LineExtensionAmount ({$lineAmount}).\n".
                    "In PEPPOL Credit Notes, ALL amounts must be POSITIVE.\n".
                    "The credit nature is indicated by document type 381, not by negative amounts.\n".
                    'Solution: Use abs() on the amount before adding the line.';
            }

            if ($priceAmount < 0) {
                $errors[] = '[BR-27] Credit Note line '.($index + 1)." has negative PriceAmount ({$priceAmount}).\n".
                    "Item net price (BT-146) shall NOT be negative.\n".
                    'Solution: Use abs() on the price before adding the line.';
            }

            if ($quantity < 0) {
                $errors[] = '[BR-CN-02] Credit Note line '.($index + 1)." has negative CreditedQuantity ({$quantity}).\n".
                    "Credited quantity must be positive.\n".
                    'Solution: Use abs() on the quantity before adding the line.';
            }
        }

        // Check totals are positive
        if (! empty($this->totals)) {
            if (($this->totals['line_extension_amount'] ?? 0) < 0) {
                $errors[] = "[BR-CN-03] LineExtensionAmount in totals is negative.\n".
                    'All monetary totals in a Credit Note must be positive.';
            }
            if (($this->totals['payable_amount'] ?? 0) < 0) {
                $errors[] = "[BR-CN-04] PayableAmount is negative.\n".
                    'The amount to be credited must be positive.';
            }
        }

        if (! empty($errors)) {
            throw new \InvalidArgumentException(
                "Credit Note Validation Failed (PEPPOL BIS Billing 3.0 / EN 16931):\n\n".
                implode("\n\n", $errors).
                "\n\n".
                'Documentation: https://docs.peppol.eu/poacc/billing/3.0/bis/#creditnote'
            );
        }
    }

    /**
     * Validate the invoice according to EN16931/Peppol BIS Billing 3.0 rules
     *
     * This validates:
     * - BR-CO-10: Sum of Invoice line net amounts = Line extension amount
     * - BR-CO-13: Invoice total amount without VAT = Line extension amount - allowances + charges
     * - BR-CO-15: Invoice total amount with VAT = Invoice total without VAT + Invoice total VAT amount
     * - BR-CO-16: Amount due for payment = Invoice total with VAT - Paid amount
     */
    public function validate(): Validation\InvoiceValidationResult
    {
        // Check if we have the required data
        if (empty($this->invoiceLines)) {
            return new Validation\InvoiceValidationResult(
                isValid: false,
                errors: ['No invoice lines found. Add lines first using addInvoiceLine().'],
                warnings: [],
                corrections: []
            );
        }

        if (empty($this->totals)) {
            return new Validation\InvoiceValidationResult(
                isValid: false,
                errors: ['No totals found. Add totals first using addLegalMonetaryTotal().'],
                warnings: [],
                corrections: []
            );
        }

        if (empty($this->taxTotals)) {
            return new Validation\InvoiceValidationResult(
                isValid: false,
                errors: ['No VAT totals found. Add VAT first using addTaxTotal().'],
                warnings: [],
                corrections: []
            );
        }

        $totalsResult = UblValidator::validateInvoiceTotals(
            $this->invoiceLines,
            $this->totals,
            $this->taxTotals,
            $this->allowanceTotalAmount,
            $this->chargeTotalAmount,
            $this->prepaidAmount,
            $this->documentAllowances,
            $this->documentCharges
        );

        $codeResult = UblValidator::validateBasicCodes([
            'currency_codes' => $this->usedCurrencyCodes,
            'scheme_ids' => $this->usedSchemeIds,
            'payment_means_codes' => $this->usedPaymentMeansCodes,
            'unit_codes' => $this->usedUnitCodes,
            'tax_category_ids' => $this->usedTaxCategoryIds,
        ]);

        // What the VAT categories demand of the document: breakdown, rates, VAT numbers, delivery
        $vatResult = $this->validateVatCategoriesOfDocument();

        $errors = array_merge($totalsResult->errors, $codeResult->errors, $vatResult->errors);
        $warnings = array_merge($totalsResult->warnings, $codeResult->warnings);

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
            corrections: $totalsResult->corrections
        );
    }

    /**
     * Get the tracked invoice lines
     */
    public function getInvoiceLines(): array
    {
        return $this->invoiceLines;
    }

    /**
     * Get the tracked totals
     */
    public function getTotals(): array
    {
        return $this->totals;
    }

    /**
     * Get the tracked tax totals
     */
    public function getTaxTotals(): array
    {
        return $this->taxTotals;
    }

    /**
     * Calculate correct totals based on invoice lines
     *
     * @return array Array with calculated totals
     */
    public function calculateTotals(): array
    {
        $lineExtensionAmount = 0.0;
        $taxByCategory = [];

        foreach ($this->invoiceLines as $line) {
            $lineAmount = (float) ($line['line_extension_amount'] ?? 0);
            $lineExtensionAmount += $lineAmount;

            $taxCategoryId = $line['tax_category_id'] ?? 'S';
            $taxPercent = (float) ($line['tax_percent'] ?? 21);
            $key = $taxCategoryId.'_'.$taxPercent;

            if (! isset($taxByCategory[$key])) {
                $taxByCategory[$key] = [
                    'taxable_amount' => 0.0,
                    'tax_percent' => $taxPercent,
                    'tax_category_id' => $taxCategoryId,
                    'tax_scheme_id' => $line['tax_scheme_id'] ?? 'VAT',
                    'currency' => $line['currency'] ?? 'EUR',
                ];
            }
            $taxByCategory[$key]['taxable_amount'] += $lineAmount;
        }

        $totalTaxAmount = 0.0;
        $taxSubtotals = [];
        foreach ($taxByCategory as $category) {
            $taxAmount = round($category['taxable_amount'] * ($category['tax_percent'] / 100), 2);
            $totalTaxAmount += $taxAmount;
            $taxSubtotals[] = [
                'taxable_amount' => round($category['taxable_amount'], 2),
                'tax_amount' => $taxAmount,
                'tax_percent' => $category['tax_percent'],
                'tax_category_id' => $category['tax_category_id'],
                'tax_scheme_id' => $category['tax_scheme_id'],
                'currency' => $category['currency'],
            ];
        }

        $taxExclusiveAmount = $lineExtensionAmount - $this->allowanceTotalAmount + $this->chargeTotalAmount;
        $taxInclusiveAmount = $taxExclusiveAmount + $totalTaxAmount;
        $payableAmount = $taxInclusiveAmount - $this->prepaidAmount;

        return [
            'totals' => [
                'line_extension_amount' => round($lineExtensionAmount, 2),
                'tax_exclusive_amount' => round($taxExclusiveAmount, 2),
                'tax_inclusive_amount' => round($taxInclusiveAmount, 2),
                'charge_total_amount' => round($this->chargeTotalAmount, 2),
                'allowance_total_amount' => round($this->allowanceTotalAmount, 2),
                'payable_amount' => round($payableAmount, 2),
            ],
            'tax_totals' => $taxSubtotals,
            'total_tax_amount' => round($totalTaxAmount, 2),
        ];
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
     * @param  string|\DateTime  $issueDate  Invoice date (required, format: YYYY-MM-DD)
     * @param  string|\DateTime  $dueDate  Due date (required, must be after invoice date)
     *
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

        // Valideer en converteer vervaldatum
        $dueDateObj = null;
        if ($dueDate instanceof \DateTime) {
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

        // Throw an exception listing every validation error
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

        // ID (factuurnummer)
        $idElement = $this->createElement('cbc', 'ID', $invoiceNumber);
        $this->rootElement->appendChild($idElement);

        // IssueDate (factuurdatum)
        $issueDateElement = $this->createElement('cbc', 'IssueDate', $issueDate);
        $this->rootElement->appendChild($issueDateElement);

        // DueDate: check that it is not empty
        // When it is empty, fall back to issueDate plus 30 days
        if (empty($dueDate)) {
            // Take issueDate as the base and add 30 days as the default payment term
            try {
                $issueDateObj = new \DateTime($issueDate);
                $dueDateObj = clone $issueDateObj;
                $dueDateObj->modify('+30 days');
                $dueDate = $dueDateObj->format('Y-m-d');
            } catch (\Exception $e) {
                // If that fails, fall back to today plus 30 days
                $dueDate = (new \DateTime)->modify('+30 days')->format('Y-m-d');
            }
        } else {
            // Check that it is a valid date in Y-m-d format
            try {
                $dueDateObj = new \DateTime($dueDate);
                $dueDate = $dueDateObj->format('Y-m-d'); // Normalise to YYYY-MM-DD
            } catch (\Exception $e) {
                // Not a valid date, so fall back to today plus 30 days
                $dueDate = (new \DateTime)->modify('+30 days')->format('Y-m-d');
            }
        }

        // $dueDate is now guaranteed to be a valid date in the right format
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

        // BuyerReference is added separately through addBuyerReference() to avoid a duplicate element

        return $this;
    }

    /**
     * Format an amount for use in UBL
     *
     * @param  float  $amount  Bedrag
     * @return string Geformatteerd bedrag (2 decimalen)
     */
    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Add the buyer accounting reference (BT-19): where the buyer books this document.
     *
     * Optional, at most one per document; a second call replaces the first. This builder writes
     * elements in call order, so the element is inserted where the schema wants it: directly
     * behind DocumentCurrencyCode, in front of BuyerReference and the other references. That
     * works for an invoice and for a credit note, whenever it is called after the header.
     *
     * @throws \InvalidArgumentException When the value is empty
     * @throws \RuntimeException When the header was not added yet
     */
    public function addAccountingCost(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Accounting cost cannot be empty');
        }

        $element = $this->createElement('cbc', 'AccountingCost', $value);
        $currencyCode = null;

        foreach (iterator_to_array($this->rootElement->childNodes) as $node) {
            if ($node instanceof DOMElement && $node->nodeName === 'cbc:AccountingCost') {
                $this->rootElement->removeChild($node);
            }

            if ($node instanceof DOMElement && $node->nodeName === 'cbc:DocumentCurrencyCode') {
                $currencyCode = $node;
            }
        }

        if ($currencyCode === null) {
            throw new \RuntimeException('Add the header before the accounting cost. Call addInvoiceHeader() or addCreditNoteHeader() first.');
        }

        // insertBefore(null) appends, which is right when the header is the last thing added so far
        $this->rootElement->insertBefore($element, $currencyCode->nextSibling);

        return $this;
    }

    /**
     * Voeg BuyerReference toe (verplicht voor PEPPOL)
     *
     * @param  string|null  $buyerRef  The buyer's own reference (for example their debtor number)
     */
    public function addBuyerReference(?string $buyerRef = 'BUYER_REF'): self
    {
        if ($this->isCreditNote) {
            throw new \InvalidArgumentException('BuyerReference is not supported on credit notes by this package. The UBL schema does allow cbc:BuyerReference in a CreditNote, but this builder does not place it in the right position yet, and a wrongly ordered element is rejected by the receiver. Leave it out, or open an issue if you need it.');
        }

        // Fall back to the default when null is passed in
        $buyerRefValue = $buyerRef ?? 'BUYER_REF';

        $buyerRefElement = $this->createElement('cbc', 'BuyerReference', $buyerRefValue);
        $this->rootElement->appendChild($buyerRefElement);

        return $this;
    }

    /**
     * Voeg OrderReference toe aan UBL document
     *
     * @param  string  $orderNumber  Ordernummer referentie
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
     * @param  string  $id  The identifier of the referenced document.
     * @param  string|null  $documentType  The type of the referenced document.
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
     * @param  string  $endpointId
     * @param  string  $endpointScheme
     * @param  string  $partyId
     * @param  string  $name
     * @param  string  $street
     * @param  string  $postalCode
     * @param  string  $city
     * @param  string  $country
     * @param  string  $vatNumber
     * @param  string|null  $additionalStreet
     */
    /**
     * Add AccountingCustomerParty to the UBL document
     *
     * @param  string  $endpointId
     * @param  string  $endpointScheme
     * @param  string  $partyId
     * @param  string  $name
     * @param  string  $street
     * @param  string  $postalCode
     * @param  string  $city
     * @param  string  $country
     * @param  string|null  $additionalStreet
     * @param  string|null  $registrationNumber
     * @param  string|null  $contactName
     * @param  string|null  $contactPhone
     * @param  string|null  $contactEmail
     */
    /**
     * Add Delivery to the UBL document
     *
     * @param  string  $date
     * @param  string  $location_id
     * @param  string  $location_scheme
     * @param  string  $street
     * @param  string|null  $additional_street
     * @param  string  $city
     * @param  string  $postal_code
     * @param  string  $country
     * @param  string|null  $party_name
     */
    /**
     * Add PaymentMeans to the UBL document
     *
     * @param  string  $means_code
     * @param  string|null  $means_name
     * @param  string  $payment_id
     * @param  string  $account_iban
     * @param  string|null  $account_name
     * @param  string|null  $bic
     * @param  string|null  $channel_code
     * @param  string|null  $due_date
     */
    /**
     * Add PaymentTerms to the UBL document
     *
     * @param  string|null  $note
     * @param  float|null  $discount_percent
     * @param  float|null  $discount_amount
     * @param  string|null  $discount_date
     */
    /**
     * Add AllowanceCharge to the UBL document
     *
     * @param  bool  $isCharge
     * @param  float  $amount
     * @param  string  $reason
     * @param  string  $taxCategoryId
     * @param  float  $taxPercent
     * @param  string  $currency
     */
    /**
     * Add TaxTotal to the UBL document
     *
     * @param  array  $taxTotals
     */
    /**
     * Add LegalMonetaryTotal to the UBL document
     *
     * @param  array  $totals
     * @param  string  $currency
     */
    /**
     * Add InvoiceLine to the UBL document
     */
    public function addInvoiceLine(array $lineData): self
    {
        // PEPPOL BR-27: Item net price (BT-146) shall NOT be negative
        // Validate price_amount before processing
        if (isset($lineData['price_amount']) && (float) $lineData['price_amount'] < 0) {
            $description = $lineData['description'] ?? $lineData['name'] ?? 'Unknown item';
            throw new \InvalidArgumentException(
                "PEPPOL BR-27 Validation Error: Item net price (BT-146) shall NOT be negative.\n".
                "Item: \"{$description}\"\n".
                "Price: {$lineData['price_amount']}\n\n".
                'Oplossing: Negatieve bedragen (kortingen) moeten als AllowanceCharge worden toegevoegd, '.
                'niet als factuurregels. Gebruik addAllowanceCharge() met isCharge=false voor kortingen.'
            );
        }

        // Set default values
        $lineData = array_merge([
            'tax_category_id' => 'S',
            'tax_percent' => '21.00',
            'tax_scheme_id' => 'VAT',
        ], $lineData);

        if (isset($lineData['currency'])) {
            $this->usedCurrencyCodes[] = $lineData['currency'];
        }
        if (isset($lineData['unit_code'])) {
            $this->usedUnitCodes[] = $lineData['unit_code'];
        }
        if (isset($lineData['tax_category_id'])) {
            $this->usedTaxCategoryIds[] = $lineData['tax_category_id'];
        }

        $invoiceLine = $this->addChildElement($this->rootElement, 'cac', 'InvoiceLine');

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
                'Oplossing: Negatieve bedragen (kortingen) moeten als AllowanceCharge worden toegevoegd, '.
                'niet als factuurregels. Gebruik addAllowanceCharge() met isCharge=false voor kortingen.'
            );
        }

        // Track line data for validation
        $this->invoiceLines[] = array_merge($lineData, [
            'line_extension_amount' => $lineExtensionAmount,
        ]);

        $this->addChildElement($invoiceLine, 'cbc', 'ID', $lineData['id']);
        $this->addChildElement($invoiceLine, 'cbc', 'InvoicedQuantity', $this->formatAmount((float) $lineData['quantity']), ['unitCode' => $lineData['unit_code']]);
        $this->addChildElement($invoiceLine, 'cbc', 'LineExtensionAmount', $this->formatAmount((float) $lineExtensionAmount), ['currencyID' => $lineData['currency']]);

        if (! empty($lineData['accounting_cost'])) {
            $this->addChildElement($invoiceLine, 'cbc', 'AccountingCost', $lineData['accounting_cost']);
        }

        if (! empty($lineData['order_line_id'])) {
            $orderLineReference = $this->addChildElement($invoiceLine, 'cac', 'OrderLineReference');
            $this->addChildElement($orderLineReference, 'cbc', 'LineID', $lineData['order_line_id']);
        }

        // TaxTotal weggelaten voor algemene PEPPOL compliance (UBL-CR-561)

        $item = $this->addChildElement($invoiceLine, 'cac', 'Item');
        $this->addChildElement($item, 'cbc', 'Description', $lineData['description']);
        $this->addChildElement($item, 'cbc', 'Name', $lineData['name']);

        $classifiedTaxCategory = $this->addChildElement($item, 'cac', 'ClassifiedTaxCategory');
        $this->addChildElement($classifiedTaxCategory, 'cbc', 'ID', $lineData['tax_category_id']);
        // Name weggelaten voor PEPPOL compliance (UBL-CR-597)
        // BR-O-05: a line in category O carries no VAT rate
        if (strtoupper($lineData['tax_category_id']) !== 'O') {
            $this->addChildElement($classifiedTaxCategory, 'cbc', 'Percent', $this->formatAmount((float) $lineData['tax_percent']));
        }
        $taxScheme = $this->addChildElement($classifiedTaxCategory, 'cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', $lineData['tax_scheme_id']);

        $price = $this->addChildElement($invoiceLine, 'cac', 'Price');
        $this->addChildElement($price, 'cbc', 'PriceAmount', $this->formatAmount((float) $lineData['price_amount']), ['currencyID' => $lineData['currency']]);
        $this->addChildElement($price, 'cbc', 'BaseQuantity', '1', ['unitCode' => $lineData['unit_code']]);

        return $this;
    }

    /**
     * Add a credit note line
     *
     * For credit notes, all amounts should be POSITIVE.
     * The credit nature is indicated by the document type (381), not negative amounts.
     *
     * @param  array  $lineData  Line data array with keys:
     *                           - id: Line identifier
     *                           - quantity: Quantity (positive number)
     *                           - unit_code: Unit code (e.g., 'C62', 'EA')
     *                           - line_extension_amount: Line total (positive, optional if price_amount and quantity provided)
     *                           - description: Item description
     *                           - name: Item name
     *                           - price_amount: Unit price (positive)
     *                           - currency: Currency code (default 'EUR')
     *                           - tax_category_id: Tax category (e.g., 'S' for standard rate)
     *                           - tax_percent: Tax percentage
     *                           - tax_scheme_id: Tax scheme (default 'VAT')
     */
    public function addCreditNoteLine(array $lineData): self
    {
        // Ensure amounts are positive for credit notes
        $priceAmount = abs((float) ($lineData['price_amount'] ?? 0));
        $quantity = abs((float) ($lineData['quantity'] ?? 0));

        $lineExtensionAmount = $lineData['line_extension_amount']
            ?? ($priceAmount * $quantity);
        $lineExtensionAmount = abs((float) $lineExtensionAmount);

        $currencyCode = $lineData['currency'] ?? 'EUR';
        $unitCode = $lineData['unit_code'] ?? 'C62';
        $taxCategoryId = $lineData['tax_category_id'] ?? 'S';

        $this->usedCurrencyCodes[] = $currencyCode;
        $this->usedUnitCodes[] = $unitCode;
        $this->usedTaxCategoryIds[] = $taxCategoryId;

        // Track line data for validation (same as invoice)
        $this->invoiceLines[] = array_merge($lineData, [
            'line_extension_amount' => $lineExtensionAmount,
            'price_amount' => $priceAmount,
            'quantity' => $quantity,
        ]);

        $creditNoteLine = $this->addChildElement($this->rootElement, 'cac', 'CreditNoteLine');

        $this->addChildElement($creditNoteLine, 'cbc', 'ID', $lineData['id']);
        $this->addChildElement($creditNoteLine, 'cbc', 'CreditedQuantity', $this->formatAmount($quantity),
            ['unitCode' => $unitCode]);
        $this->addChildElement($creditNoteLine, 'cbc', 'LineExtensionAmount', $this->formatAmount($lineExtensionAmount),
            ['currencyID' => $currencyCode]);

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
        $this->addChildElement($classifiedTaxCategory, 'cbc', 'ID', $taxCategoryId);
        // BR-O-05: a line in category O carries no VAT rate
        if (strtoupper($taxCategoryId) !== 'O') {
            $this->addChildElement($classifiedTaxCategory, 'cbc', 'Percent',
                $this->formatAmount((float) ($lineData['tax_percent'] ?? 21)));
        }
        $taxScheme = $this->addChildElement($classifiedTaxCategory, 'cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', $lineData['tax_scheme_id'] ?? 'VAT');

        $price = $this->addChildElement($creditNoteLine, 'cac', 'Price');
        $this->addChildElement($price, 'cbc', 'PriceAmount', $this->formatAmount($priceAmount),
            ['currencyID' => $currencyCode]);
        $this->addChildElement($price, 'cbc', 'BaseQuantity', '1', ['unitCode' => $unitCode]);

        return $this;
    }

    public function addLegalMonetaryTotal(array $totals, string $currency): self
    {
        $this->usedCurrencyCodes[] = $currency;

        // Track totals for validation
        $this->totals = $totals;
        $this->chargeTotalAmount = (float) ($totals['charge_total_amount'] ?? 0);
        $this->allowanceTotalAmount = (float) ($totals['allowance_total_amount'] ?? 0);
        $this->prepaidAmount = (float) ($totals['prepaid_amount'] ?? 0);

        $monetaryTotal = $this->createElement('cac', 'LegalMonetaryTotal');
        $lineElement = $this->getFirstLineElement();
        if ($lineElement) {
            $this->rootElement->insertBefore($monetaryTotal, $lineElement);
        } else {
            $this->rootElement->appendChild($monetaryTotal);
        }

        $this->addChildElement($monetaryTotal, 'cbc', 'LineExtensionAmount', $this->formatAmount((float) $totals['line_extension_amount']), ['currencyID' => $currency]);
        $this->addChildElement($monetaryTotal, 'cbc', 'TaxExclusiveAmount', $this->formatAmount((float) $totals['tax_exclusive_amount']), ['currencyID' => $currency]);
        $this->addChildElement($monetaryTotal, 'cbc', 'TaxInclusiveAmount', $this->formatAmount((float) $totals['tax_inclusive_amount']), ['currencyID' => $currency]);

        // AllowanceTotalAmount: required when the document has document-level allowances (BR-CO-11)
        if ($this->allowanceTotalAmount > 0.001) {
            $this->addChildElement($monetaryTotal, 'cbc', 'AllowanceTotalAmount', $this->formatAmount($this->allowanceTotalAmount), ['currencyID' => $currency]);
        }

        // ChargeTotalAmount - altijd outputten (kan 0.00 zijn)
        $this->addChildElement($monetaryTotal, 'cbc', 'ChargeTotalAmount', $this->formatAmount($this->chargeTotalAmount), ['currencyID' => $currency]);

        // PrepaidAmount: optional, only when something was paid up front
        if ($this->prepaidAmount > 0.001) {
            $this->addChildElement($monetaryTotal, 'cbc', 'PrepaidAmount', $this->formatAmount($this->prepaidAmount), ['currencyID' => $currency]);
        }

        $this->addChildElement($monetaryTotal, 'cbc', 'PayableAmount', $this->formatAmount((float) $totals['payable_amount']), ['currencyID' => $currency]);

        return $this;
    }

    /**
     * Add the VAT breakdown (BG-23). Calling it again replaces the breakdown.
     *
     * Each entry takes taxable_amount, tax_amount, currency, tax_category_id, tax_percent and
     * tax_scheme_id, and optionally tax_exemption_reason_code (BT-121, see Vat\VatExemptionReason) and
     * tax_exemption_reason (BT-120, free text). The categories and what each demands are explained by
     * Vat\VatCategory. A category that needs an exemption reason (E, AE, K, G, O) and gets none is
     * written with the code that belongs to it (VATEX-EU-AE, VATEX-EU-IC, VATEX-EU-G, VATEX-EU-O); for E
     * validate() reports the missing reason.
     *
     * @param  array<int, array<string, mixed>>  $taxTotals
     *
     * @throws \InvalidArgumentException For an exemption reason the category does not allow (see UblValidator::resolveTaxExemption())
     */
    public function addTaxTotal(array $taxTotals): self
    {
        // BT-120 and BT-121, settled before anything is written so a refused reason leaves no half document
        $exemptions = array_map(fn (array $tax) => UblValidator::resolveTaxExemption(
            (string) ($tax['tax_category_id'] ?? ''),
            isset($tax['tax_exemption_reason_code']) ? (string) $tax['tax_exemption_reason_code'] : null,
            isset($tax['tax_exemption_reason']) ? (string) $tax['tax_exemption_reason'] : null
        ), $taxTotals);

        // Track tax totals for validation
        $this->taxTotals = $taxTotals;

        foreach ($taxTotals as $tax) {
            if (isset($tax['currency'])) {
                $this->usedCurrencyCodes[] = $tax['currency'];
            }
            if (isset($tax['tax_category_id'])) {
                $this->usedTaxCategoryIds[] = $tax['tax_category_id'];
            }
        }

        // Find and remove existing TaxTotal to prevent duplicates
        $existingTaxTotals = $this->dom->getElementsByTagName('cac:TaxTotal');
        if ($existingTaxTotals->length === 0) {
            $existingTaxTotals = $this->dom->getElementsByTagNameNS($this->ns_cac_uri, 'TaxTotal');
        }
        while ($existingTaxTotals->length > 0) {
            $node = $existingTaxTotals->item(0);

            // A detached node has no parent; without this guard the loop would never end.
            if ($node === null || $node->parentNode === null) {
                break;
            }

            $node->parentNode->removeChild($node);
        }

        $totalTaxAmount = 0;
        foreach ($taxTotals as $tax) {
            $totalTaxAmount += (float) $tax['tax_amount'];
        }

        $taxTotalElement = $this->createElement('cac', 'TaxTotal');
        $monetaryTotal = $this->dom->getElementsByTagName('cac:LegalMonetaryTotal')->item(0);
        if (! $monetaryTotal) {
            $monetaryTotal = $this->dom->getElementsByTagNameNS($this->ns_cac_uri, 'LegalMonetaryTotal')->item(0);
        }
        $insertBefore = $monetaryTotal ?: $this->getFirstLineElement();
        if ($insertBefore) {
            $this->rootElement->insertBefore($taxTotalElement, $insertBefore);
        } else {
            $this->rootElement->appendChild($taxTotalElement);
        }
        $this->addChildElement($taxTotalElement, 'cbc', 'TaxAmount', $this->formatAmount($totalTaxAmount), ['currencyID' => $taxTotals[0]['currency'] ?? 'EUR']);

        foreach ($taxTotals as $index => $tax) {
            $taxSubtotal = $this->addChildElement($taxTotalElement, 'cac', 'TaxSubtotal');
            $this->addChildElement($taxSubtotal, 'cbc', 'TaxableAmount', $this->formatAmount((float) $tax['taxable_amount']), ['currencyID' => $tax['currency']]);
            $this->addChildElement($taxSubtotal, 'cbc', 'TaxAmount', $this->formatAmount((float) $tax['tax_amount']), ['currencyID' => $tax['currency']]);

            $taxCategory = $this->addChildElement($taxSubtotal, 'cac', 'TaxCategory');
            $this->addChildElement($taxCategory, 'cbc', 'ID', $tax['tax_category_id']);
            // Name weggelaten voor PEPPOL compliance (UBL-CR-504)
            // Category O carries no rate (BR-48 allows leaving it out, as BR-O-05 demands on the lines)
            if (strtoupper((string) $tax['tax_category_id']) !== 'O') {
                $this->addChildElement($taxCategory, 'cbc', 'Percent', $this->formatAmount((float) $tax['tax_percent']));
            }

            // Exemption reason code and text, between Percent and TaxScheme as the UBL schema orders them
            if ($exemptions[$index]['code'] !== null) {
                $this->addChildElement($taxCategory, 'cbc', 'TaxExemptionReasonCode', $exemptions[$index]['code']);
            }

            if ($exemptions[$index]['text'] !== null) {
                $this->addChildElement($taxCategory, 'cbc', 'TaxExemptionReason', $exemptions[$index]['text']);
            }

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
        $this->usedCurrencyCodes[] = $currency;
        $this->usedTaxCategoryIds[] = $taxCategoryId;

        // Remember it for validate(): BR-CO-11 and BR-CO-12 sum these amounts, and the taxable
        // amount of a VAT category includes them.
        $tracked = ['amount' => $amount, 'tax_category_id' => $taxCategoryId, 'tax_percent' => $taxPercent];
        if ($isCharge) {
            $this->documentCharges[] = $tracked;
        } else {
            $this->documentAllowances[] = $tracked;
        }

        $allowanceCharge = $this->addChildElement($this->rootElement, 'cac', 'AllowanceCharge');
        $this->addChildElement($allowanceCharge, 'cbc', 'ChargeIndicator', $isCharge ? 'true' : 'false');
        $this->addChildElement($allowanceCharge, 'cbc', 'AllowanceChargeReason', $reason);
        $this->addChildElement($allowanceCharge, 'cbc', 'Amount', $this->formatAmount($amount), ['currencyID' => $currency]);

        $taxCategory = $this->addChildElement($allowanceCharge, 'cac', 'TaxCategory');
        $this->addChildElement($taxCategory, 'cbc', 'ID', $taxCategoryId);
        // BR-O-06 and BR-O-07: a discount or charge in category O carries no VAT rate
        if (strtoupper($taxCategoryId) !== 'O') {
            $this->addChildElement($taxCategory, 'cbc', 'Percent', $this->formatAmount($taxPercent));
        }
        $taxScheme = $this->addChildElement($taxCategory, 'cac', 'TaxScheme');
        $this->addChildElement($taxScheme, 'cbc', 'ID', 'VAT');

        return $this;
    }

    public function addPaymentTerms(
        ?string $note = null,
        ?float $discount_percent = null,
        ?float $discount_amount = null,
        ?string $discount_date = null
    ): self {
        $paymentTerms = $this->addChildElement($this->rootElement, 'cac', 'PaymentTerms');
        if ($note) {
            $this->addChildElement($paymentTerms, 'cbc', 'Note', $note);
        }

        return $this;
    }

    public function addPaymentMeans(
        string $paymentMeansCode,
        ?string $paymentMeansName,
        string $paymentId,
        string $account_iban,
        ?string $account_name,
        ?string $bic,
        ?string $channel_code = null,
        ?string $due_date = null
    ): self {
        $this->usedPaymentMeansCodes[] = $paymentMeansCode;

        $paymentMeans = $this->addChildElement($this->rootElement, 'cac', 'PaymentMeans');
        $this->addChildElement($paymentMeans, 'cbc', 'PaymentMeansCode', $paymentMeansCode, $paymentMeansName ? ['name' => $paymentMeansName] : []);
        $this->addChildElement($paymentMeans, 'cbc', 'PaymentID', $paymentId);

        $payeeFinancialAccount = $this->addChildElement($paymentMeans, 'cac', 'PayeeFinancialAccount');
        // IBAN zonder schemeID per UBL-CR-654
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

    /**
     * Add the delivery (BG-13): the actual delivery date (BT-72) and, optionally, where the goods went.
     *
     * Only the date is required. The location ID (BT-71) is written only when you pass one, under
     * its scheme; a GLN under 0088 must carry a valid check digit (PEPPOL-COMMON-R040), so never
     * make one up. The address is written with the parts you pass. The country (BT-80) is what an
     * intra-community supply needs (BR-IC-12): addDelivery($date, country: 'NL') is enough.
     */
    public function addDelivery(
        string $deliveryDate,
        ?string $locationId = null,
        string $locationSchemeId = '0088',
        ?string $street = null,
        ?string $additional_street = null,
        ?string $city = null,
        ?string $postal_code = null,
        ?string $country = null,
        ?string $party_name = null
    ): self {
        $delivery = $this->addChildElement($this->rootElement, 'cac', 'Delivery');
        $this->addChildElement($delivery, 'cbc', 'ActualDeliveryDate', $deliveryDate);

        $hasAddress = $street !== null || $city !== null || $postal_code !== null || $country !== null;

        if ($locationId !== null || $hasAddress) {
            $deliveryLocation = $this->addChildElement($delivery, 'cac', 'DeliveryLocation');

            if ($locationId !== null) {
                $this->addChildElement($deliveryLocation, 'cbc', 'ID', $locationId, ['schemeID' => $locationSchemeId]);
            }

            if ($hasAddress) {
                // The order of the UBL schema: street, additional street, city, postal zone, country
                $address = $this->addChildElement($deliveryLocation, 'cac', 'Address');
                if ($street !== null) {
                    $this->addChildElement($address, 'cbc', 'StreetName', $street);
                }
                if ($additional_street) {
                    $this->addChildElement($address, 'cbc', 'AdditionalStreetName', $additional_street);
                }
                if ($city !== null) {
                    $this->addChildElement($address, 'cbc', 'CityName', $city);
                }
                if ($postal_code !== null) {
                    $this->addChildElement($address, 'cbc', 'PostalZone', $postal_code);
                }
                if ($country !== null) {
                    $countryElement = $this->addChildElement($address, 'cac', 'Country');
                    $this->addChildElement($countryElement, 'cbc', 'IdentificationCode', strtoupper($country));
                }
            }
        }

        if ($party_name) {
            $deliveryParty = $this->addChildElement($delivery, 'cac', 'DeliveryParty');
            $partyName = $this->addChildElement($deliveryParty, 'cac', 'PartyName');
            $this->addChildElement($partyName, 'cbc', 'Name', $party_name);
        }

        return $this;
    }

    public function addAccountingCustomerParty(
        string $endpointId,
        string $endpointSchemeID,
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
        ?string $contactEmail = null,
        ?string $vatNumber = null
    ): self {
        $this->usedEndpointSchemeIds[] = $endpointSchemeID;

        $this->usedSchemeIds[] = $endpointSchemeID;

        $customerParty = $this->addChildElement($this->rootElement, 'cac', 'AccountingCustomerParty');
        $party = $this->addChildElement($customerParty, 'cac', 'Party');

        $this->addChildElement($party, 'cbc', 'EndpointID', $endpointId, ['schemeID' => $endpointSchemeID]);

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

        // PartyTaxScheme - only add if VAT number is provided (BR-CO-09: must start with country code)
        if ($vatNumber) {
            // Validate that VAT number starts with a 2-letter country code (BR-CO-09). The prefix is
            // an ISO 3166-1 code, which is upper case, so a lower case number is written in upper case.
            $vatNumber = strtoupper($vatNumber);
            if (! preg_match('/^[A-Z]{2}/', $vatNumber)) {
                throw new \InvalidArgumentException(
                    "VAT number must start with a 2-letter ISO 3166-1 alpha-2 country code (e.g., 'NL', 'BE'). Got: '{$vatNumber}'"
                );
            }
            $partyTaxScheme = $this->addChildElement($party, 'cac', 'PartyTaxScheme');
            $this->addChildElement($partyTaxScheme, 'cbc', 'CompanyID', $vatNumber);
            $taxScheme = $this->addChildElement($partyTaxScheme, 'cac', 'TaxScheme');
            $this->addChildElement($taxScheme, 'cbc', 'ID', 'VAT');
        }

        $partyLegalEntity = $this->addChildElement($party, 'cac', 'PartyLegalEntity');
        $this->addChildElement($partyLegalEntity, 'cbc', 'RegistrationName', $name);
        if ($registrationNumber) {
            // For Dutch customers: use correct schemeID (0106 for KVK, 0190 for OIN)
            $schemeID = (strtoupper($country) === 'NL') ? '0106' : '0208';
            $this->addChildElement($partyLegalEntity, 'cbc', 'CompanyID', $registrationNumber, ['schemeID' => $schemeID]);
            $this->usedSchemeIds[] = $schemeID;
            $this->usedRegistrationSchemeIds[] = $schemeID;
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
        string $endpointSchemeID,
        string $partyId,
        string $name,
        string $street,
        string $postalCode,
        string $city,
        string $country,
        string $vatNumber,
        ?string $additionalStreet = null
    ): self {
        $this->usedEndpointSchemeIds[] = $endpointSchemeID;

        $this->usedSchemeIds[] = $endpointSchemeID;

        $supplierParty = $this->addChildElement($this->rootElement, 'cac', 'AccountingSupplierParty');
        $party = $this->addChildElement($supplierParty, 'cac', 'Party');

        // EndpointID
        $this->addChildElement($party, 'cbc', 'EndpointID', $endpointId, ['schemeID' => $endpointSchemeID]);

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

    protected function getFirstLineElement(): ?DOMElement
    {
        $lineTag = $this->isCreditNote ? 'CreditNoteLine' : 'InvoiceLine';
        $lines = $this->dom->getElementsByTagName('cac:'.$lineTag);
        if ($lines->length === 0) {
            $lines = $this->dom->getElementsByTagNameNS($this->ns_cac_uri, $lineTag);
        }

        return $lines->length > 0 ? $lines->item(0) : null;
    }
}
