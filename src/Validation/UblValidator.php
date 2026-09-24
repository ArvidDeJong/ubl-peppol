<?php

namespace Darvis\UblPeppol\Validation;

use Darvis\UblPeppol\Constants\UnitCodes;
use Darvis\UblPeppol\Vat\VatCategory;
use Darvis\UblPeppol\Vat\VatExemptionReason;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class UblValidator
{
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /**
     * Validates if the given unit code is a valid UN/ECE Recommendation 20 with Rec 21 extension unit code.
     *
     * @param  string  $unitCode  The unit code to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidUnitCode(string $unitCode): bool
    {
        return UnitCodes::isValid($unitCode);
    }

    /**
     * Validates if the given currency code matches ISO 4217 format.
     *
     * @param  string  $currencyCode  The currency code to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidCurrencyCodeFormat(string $currencyCode): bool
    {
        return preg_match('/^[A-Z]{3}$/', strtoupper(trim($currencyCode))) === 1;
    }

    /**
     * Validates if the given scheme ID matches the PEPPOL format (4 digits).
     *
     * @param  string  $schemeId  The scheme ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidSchemeIdFormat(string $schemeId): bool
    {
        return preg_match('/^[0-9]{4}$/', trim($schemeId)) === 1;
    }

    /**
     * Validates if the given payment means code matches UNCL 4461 format (1-3 digits).
     *
     * @param  string  $paymentMeansCode  The payment means code to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidPaymentMeansCodeFormat(string $paymentMeansCode): bool
    {
        return preg_match('/^[0-9]{1,3}$/', trim($paymentMeansCode)) === 1;
    }

    /**
     * Validates if the given classification scheme ID is valid according to UNTDID 7143.
     *
     * @param  string  $schemeId  The classification scheme ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidClassificationScheme(string $schemeId): bool
    {
        // List of valid classification schemes from UNTDID 7143
        $validSchemes = [
            'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ',
            'BA', 'BB', 'BC', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BK', 'BL', 'BM', 'BN', 'BO', 'BP', 'BQ', 'BR', 'BS', 'BT', 'BU', 'BV', 'BW', 'BX', 'BY', 'BZ',
            'CC', 'CG', 'CL', 'CR', 'CV', 'DR', 'DW', 'EC', 'EF', 'EMD', 'EN', 'FS', 'GB', 'GN', 'GMN', 'GS', 'HS', 'IB', 'IN', 'IS', 'IT', 'IZ', 'MA', 'MF', 'MN', 'MP',
            'NB', 'ON', 'PD', 'PL', 'PO', 'PPI', 'PV', 'QS', 'RC', 'RN', 'RU', 'RY', 'SA', 'SG', 'SK', 'SN', 'SRS', 'SRT', 'SRU', 'SRV', 'SRW', 'SRX', 'SRY', 'SRZ',
            'SS', 'SSA', 'SSB', 'SSC', 'SSD', 'SSE', 'SSF', 'SSG', 'SSH', 'SSI', 'SSJ', 'SSK', 'SSL', 'SSM', 'SSN', 'SSO', 'SSP', 'SSQ', 'SSR', 'SSS', 'SST', 'SSU', 'SSV', 'SSW', 'SSX', 'SSY', 'SSZ',
            'ST', 'STA', 'STB', 'STC', 'STD', 'STE', 'STF', 'STG', 'STH', 'STI', 'STJ', 'STK', 'STL', 'STM', 'STN', 'STO', 'STP', 'STQ', 'STR', 'STS', 'STT', 'STU', 'STV', 'STW', 'STX', 'STY', 'STZ',
            'SUA', 'SUB', 'SUC', 'SUD', 'SUE', 'SUF', 'SUG', 'SUH', 'SUI', 'SUJ', 'SUK', 'SUL', 'SUM', 'TG', 'TSN', 'TSO', 'TSP', 'TSQ', 'TSR', 'TSS', 'TST', 'TSU',
            'UA', 'UP', 'VN', 'VP', 'VS', 'VX', 'ZZZ',
        ];

        // PEPPOL specific schemes
        $peppolSchemes = [
            'CPV' => 'Common Procurement Vocabulary',
            'SRV' => 'Service Type Code',
        ];

        $schemeId = strtoupper(trim($schemeId));

        // Check if it's a standard UNTDID 7143 scheme or a PEPPOL specific scheme
        return in_array($schemeId, $validSchemes, true) || array_key_exists($schemeId, $peppolSchemes);
    }

    /**
     * Gets the description of a classification scheme
     *
     * @param  string  $schemeId  The scheme ID
     * @return string The scheme description or empty string if not found
     */
    public static function getClassificationSchemeDescription(string $schemeId): string
    {
        $descriptions = [
            'CPV' => 'Common Procurement Vocabulary',
            'SRV' => 'Service Type Code',
            'STD' => 'Standard',
            'HS' => 'Harmonized System',
            'GS1' => 'GS1 Global Trade Item Number',
            // Add more scheme descriptions as needed
        ];

        return $descriptions[strtoupper($schemeId)] ?? '';
    }

    /**
     * Validates if the given tax category ID is valid.
     *
     * @param  string  $categoryId  The tax category ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidTaxCategory(string $categoryId): bool
    {
        return VatCategory::fromCode($categoryId) !== null;
    }

    /**
     * Settle the exemption reason (BT-121 code, BT-120 text) of one VAT breakdown entry.
     *
     * A category that needs a reason and got none gets the one code that belongs to it
     * (VatCategory::defaultExemptionReasonCode()), so a document with K, AE, G or O meets
     * BR-IC-10, BR-AE-10, BR-G-10 or BR-O-10 without the caller knowing the code. Exempt (E) has no
     * such code; validate() reports it when neither a code nor a text is given (BR-E-10).
     *
     * @return array{code: ?string, text: ?string}
     *
     * @throws \InvalidArgumentException When the category allows no reason (BR-S-10, BR-Z-10, BR-AF-10,
     *                                   BR-AG-10), the code is not in the VATEX list (BR-CL-22) or the
     *                                   code belongs to another category (PEPPOL-EN16931-P0104 to P0111)
     */
    public static function resolveTaxExemption(string $categoryId, ?string $code = null, ?string $text = null): array
    {
        $code = $code === null || trim($code) === '' ? null : strtoupper(trim($code));
        $text = $text === null || trim($text) === '' ? null : trim($text);

        $category = VatCategory::fromCode($categoryId);

        // An unknown category is reported by validateBasicCodes(); the reason is passed on as given.
        if ($category === null) {
            return ['code' => $code, 'text' => $text];
        }

        if ($category->forbidsExemptionReason() && ($code !== null || $text !== null)) {
            $rule = $category->ruleId(10);

            throw new \InvalidArgumentException(
                "[{$rule}] VAT category {$category->value} ({$category->label()}) takes no exemption reason; leave tax_exemption_reason_code and tax_exemption_reason out."
            );
        }

        if ($code !== null) {
            if (! VatExemptionReason::isKnown($code)) {
                throw new \InvalidArgumentException(
                    "[BR-CL-22] Exemption reason code '{$code}' is not in the VATEX code list. See VatExemptionReason::codes()."
                );
            }

            $codeCategory = VatExemptionReason::categoryOf($code);

            if ($codeCategory !== null && $codeCategory !== $category) {
                throw new \InvalidArgumentException(
                    "[PEPPOL-EN16931-P0104 to P0111] Exemption reason code {$code} may only be used with VAT category {$codeCategory->value}, not with {$category->value}."
                );
            }
        }

        if ($category->requiresExemptionReason() && $code === null && $text === null) {
            $code = $category->defaultExemptionReasonCode();
        }

        return ['code' => $code, 'text' => $text];
    }

    /**
     * Check what the VAT categories demand of a finished document, the way a receiver does.
     *
     * The check reads the document itself, not the arguments the builder got, so it judges exactly
     * what would be sent. It covers the category rules of EN 16931 that the builders cannot enforce
     * while you build, because they depend on more than one call:
     *
     * - every category of a line, discount or charge is in the VAT breakdown (BR-S-01 and the like),
     *   only once for a category other than S;
     * - the VAT rate fits the category: above 0 for S, 0 for Z, E, AE, K and G (BR-S-05 to BR-S-07 and
     *   the like), and the VAT amount of a zero rated breakdown is 0 (BR-Z-09 and the like);
     * - the VAT numbers the category needs are there (BR-S-02, BR-AE-02, BR-IC-02 and the like), and
     *   are absent for O (BR-O-02);
     * - the breakdown has an exemption reason where the category needs one (BR-E-10 and the like);
     * - an intra-community supply states the delivery date and country (BR-IC-11, BR-IC-12);
     * - O stands alone (BR-O-11 to BR-O-14).
     *
     * Each message starts with the rule code and says what to pass to which method.
     */
    public static function validateVatCategories(DOMDocument $document): InvoiceValidationResult
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cac', self::NS_CAC);
        $xpath->registerNamespace('cbc', self::NS_CBC);

        $text = fn (string $query, ?DOMNode $context = null): string => trim((string) $xpath->evaluate('string('.$query.')', $context));

        /** @return list<DOMElement> */
        $elements = function (string $query) use ($xpath): array {
            $found = [];
            foreach ($xpath->query($query) ?: [] as $node) {
                if ($node instanceof DOMElement) {
                    $found[] = $node;
                }
            }

            return $found;
        };

        // Where each category is used: the lines, the document level discounts and charges, and the breakdown
        $used = [];

        foreach ($elements('/*/cac:InvoiceLine/cac:Item/cac:ClassifiedTaxCategory | /*/cac:CreditNoteLine/cac:Item/cac:ClassifiedTaxCategory') as $node) {
            $used[] = ['where' => 'line', 'node' => $node];
        }

        foreach ($elements('/*/cac:AllowanceCharge/cac:TaxCategory') as $node) {
            $isCharge = $text('../cbc:ChargeIndicator', $node) === 'true';
            $used[] = ['where' => $isCharge ? 'charge' : 'discount', 'node' => $node];
        }

        $breakdown = $elements('/*/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory');

        $errors = [];
        $categoriesInDocument = [];

        // The rate of each line, discount and charge (BR-x-05, BR-x-06, BR-x-07)
        foreach ($used as $entry) {
            $category = VatCategory::fromCode($text('cbc:ID', $entry['node']));

            if ($category === null) {
                continue;
            }

            $categoriesInDocument[$category->value] = $category;
            $rule = $category->ruleId(['line' => 5, 'discount' => 6, 'charge' => 7][$entry['where']]);
            $rate = $text('cbc:Percent', $entry['node']);

            if ($category === VatCategory::StandardRate && (float) $rate <= 0) {
                $errors[] = "[{$rule}] A {$entry['where']} in category S (standard rate) has VAT rate {$rate}; it must be above 0. For 0% choose the category that says why: K, AE, E, G, Z or O (see VatCategory).";
            } elseif ($category->requiresZeroRate() && $rate !== '' && (float) $rate !== 0.0) {
                $errors[] = "[{$rule}] A {$entry['where']} in category {$category->value} ({$category->label()}) has VAT rate {$rate}; it must be 0.";
            }
        }

        // The breakdown: the tax amount of a category without VAT, and the exemption reason
        $breakdownCount = [];

        foreach ($breakdown as $node) {
            $category = VatCategory::fromCode($text('cbc:ID', $node));

            if ($category === null) {
                continue;
            }

            $categoriesInDocument[$category->value] = $category;
            $breakdownCount[$category->value] = ($breakdownCount[$category->value] ?? 0) + 1;
            $taxAmount = $text('../cbc:TaxAmount', $node);

            if (($category->requiresZeroRate() || $category === VatCategory::NotSubjectToVat) && (float) $taxAmount !== 0.0) {
                $errors[] = "[{$category->ruleId(9)}] The VAT breakdown of category {$category->value} ({$category->label()}) has VAT amount {$taxAmount}; it must be 0.";
            }

            if ($category->requiresExemptionReason() && $text('cbc:TaxExemptionReasonCode', $node) === '' && $text('cbc:TaxExemptionReason', $node) === '') {
                $errors[] = "[{$category->ruleId(10)}] The VAT breakdown of category {$category->value} needs an exemption reason: pass tax_exemption_reason_code (for example VATEX-EU-132-1C) or tax_exemption_reason to addTaxTotal().";
            }
        }

        // Every category of a line, discount or charge is in the breakdown (BR-x-01)
        foreach ($used as $entry) {
            $category = VatCategory::fromCode($text('cbc:ID', $entry['node']));

            if ($category !== null && ! isset($breakdownCount[$category->value])) {
                $errors[] = "[{$category->ruleId(1)}] A {$entry['where']} uses category {$category->value}, but the VAT breakdown has no entry for it: add one to addTaxTotal().";
                $breakdownCount[$category->value] = 0;
            }
        }

        foreach ($breakdownCount as $code => $count) {
            if ($code !== 'S' && $count > 1) {
                $errors[] = '['.VatCategory::from($code)->ruleId(1)."] The VAT breakdown has {$count} entries for category {$code}; it may have one. Add up their amounts.";
            }
        }

        // The VAT numbers the categories need (BR-x-02), or may not have (BR-O-02)
        $sellerVat = $text('/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID') !== '';
        $buyerVat = $text('/*/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID') !== '';
        $buyerRegistration = $text('/*/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID') !== '';
        $hasSeller = $elements('/*/cac:AccountingSupplierParty') !== [];
        $hasBuyer = $elements('/*/cac:AccountingCustomerParty') !== [];

        foreach ($categoriesInDocument as $category) {
            $rule = $category->ruleId(2);

            if ($category === VatCategory::NotSubjectToVat) {
                if ($sellerVat || $buyerVat) {
                    $errors[] = "[{$rule}] A document in category O (not subject to VAT) may carry no VAT number of the seller or the buyer. The builders always write the seller's VAT number, so they cannot build such a document yet.";
                }

                continue;
            }

            if (in_array($category, [VatCategory::CanaryIslands, VatCategory::CeutaMelilla], true)) {
                continue;
            }

            if ($hasSeller && ! $sellerVat) {
                $errors[] = "[{$rule}] Category {$category->value} needs the seller's VAT number (BT-31): pass it to addAccountingSupplierParty().";
            }

            if (! $hasBuyer) {
                continue;
            }

            if ($category === VatCategory::IntraCommunitySupply && ! $buyerVat) {
                $errors[] = "[{$rule}] An intra-community supply (K) needs the buyer's VAT number (BT-48): pass \$vatNumber to addAccountingCustomerParty().";
            }

            if ($category === VatCategory::ReverseCharge && ! $buyerVat && ! $buyerRegistration) {
                $errors[] = "[{$rule}] Reverse charge (AE) needs the buyer's VAT number (BT-48) or legal registration (BT-47): pass \$vatNumber or \$companyId to addAccountingCustomerParty().";
            }
        }

        // An intra-community supply states when and where the goods went (BR-IC-11, BR-IC-12)
        if (isset($categoriesInDocument['K'])) {
            if ($text('/*/cac:Delivery/cbc:ActualDeliveryDate') === '' && $elements('/*/cac:InvoicePeriod') === []) {
                $errors[] = '[BR-IC-11] An intra-community supply (K) needs the actual delivery date: call addDelivery().';
            }

            if ($text('/*/cac:Delivery/cac:DeliveryLocation/cac:Address/cac:Country/cbc:IdentificationCode') === '') {
                $errors[] = '[BR-IC-12] An intra-community supply (K) needs the country the goods are delivered to: pass the country code to addDelivery().';
            }
        }

        // O stands alone (BR-O-11 to BR-O-14)
        if (isset($categoriesInDocument['O']) && count($categoriesInDocument) > 1) {
            $others = implode(', ', array_diff(array_keys($categoriesInDocument), ['O']));
            $errors[] = "[BR-O-11] A document with category O (not subject to VAT) may hold no other VAT category; this one also has {$others}. Send two documents.";
        }

        return new InvoiceValidationResult(
            isValid: empty($errors),
            errors: array_values(array_unique($errors)),
            warnings: [],
            corrections: []
        );
    }

    /**
     * Validates basic code list formats used in UBL/PEPPOL documents.
     *
     * @param  array  $codes  Expected keys: currency_codes, scheme_ids, payment_means_codes, unit_codes, tax_category_ids
     */
    public static function validateBasicCodes(array $codes): InvoiceValidationResult
    {
        $errors = [];
        $warnings = [];

        $currencyCodes = array_unique(array_filter($codes['currency_codes'] ?? []));
        foreach ($currencyCodes as $currencyCode) {
            if (! self::isValidCurrencyCodeFormat($currencyCode)) {
                $errors[] = "Invalid currency code format: '{$currencyCode}'. Expected ISO 4217 alpha-3 (e.g., EUR).";
            }
        }

        $schemeIds = array_unique(array_filter($codes['scheme_ids'] ?? []));
        foreach ($schemeIds as $schemeId) {
            if (! self::isValidSchemeIdFormat($schemeId)) {
                $errors[] = "Invalid schemeID format: '{$schemeId}'. Expected 4 digits (e.g., 0106).";
            }
        }

        $paymentMeansCodes = array_unique(array_filter($codes['payment_means_codes'] ?? []));
        foreach ($paymentMeansCodes as $paymentMeansCode) {
            if (! self::isValidPaymentMeansCodeFormat($paymentMeansCode)) {
                $errors[] = "Invalid payment means code format: '{$paymentMeansCode}'. Expected 1-3 digits.";
            }
        }

        $taxCategoryIds = array_unique(array_filter($codes['tax_category_ids'] ?? []));
        foreach ($taxCategoryIds as $taxCategoryId) {
            if (! self::isValidTaxCategory($taxCategoryId)) {
                $errors[] = "Invalid tax category ID: '{$taxCategoryId}'. Expected one of ".implode(', ', array_column(VatCategory::cases(), 'value')).'.';
            }
        }

        $unitCodes = array_unique(array_filter($codes['unit_codes'] ?? []));
        foreach ($unitCodes as $unitCode) {
            if (! self::isValidUnitCode($unitCode)) {
                $warnings[] = "Unknown unit code: '{$unitCode}'. Ensure it exists in UN/ECE Rec 20/21.";
            }
        }

        return new InvoiceValidationResult(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            corrections: []
        );
    }

    /**
     * Validates codes against strict codelists when lists are loaded.
     *
     * @param  array  $codes  Expected keys: currency_codes, endpoint_scheme_ids, party_scheme_ids,
     *                        registration_scheme_ids, payment_means_codes, tax_category_ids,
     *                        item_classification_ids, tax_exemption_reason_codes,
     *                        allowance_reason_codes, charge_reason_codes
     * @param  CodelistRegistry  $registry  Loaded codelists
     */
    public static function validateStrictCodelists(array $codes, CodelistRegistry $registry): InvoiceValidationResult
    {
        $errors = [];

        $listMapping = [
            'currency_codes' => ['list' => 'iso4217', 'label' => 'ISO4217'],
            'endpoint_scheme_ids' => ['list' => 'eas', 'label' => 'EAS'],
            'party_scheme_ids' => ['list' => 'icd', 'label' => 'ICD'],
            'registration_scheme_ids' => ['list' => 'icd', 'label' => 'ICD'],
            'payment_means_codes' => ['list' => 'uncl4461', 'label' => 'UNCL4461'],
            'tax_category_ids' => ['list' => 'uncl5305', 'label' => 'UNCL5305'],
            'item_classification_ids' => ['list' => 'uncl7143', 'label' => 'UNCL7143'],
            'tax_exemption_reason_codes' => ['list' => 'vatex', 'label' => 'VATEX'],
            'allowance_reason_codes' => ['list' => 'uncl5189', 'label' => 'UNCL5189'],
            'charge_reason_codes' => ['list' => 'uncl7161', 'label' => 'UNCL7161'],
        ];

        foreach ($listMapping as $codeKey => $meta) {
            $values = array_unique(array_filter($codes[$codeKey] ?? []));
            if (empty($values)) {
                continue;
            }

            if (! $registry->isLoaded($meta['list'])) {
                $errors[] = "Strict codelist validation requires list {$meta['label']} to be loaded.";

                continue;
            }

            foreach ($values as $value) {
                if (! $registry->has($meta['list'], (string) $value)) {
                    $errors[] = "Invalid {$meta['label']} code: '{$value}'.";
                }
            }
        }

        return new InvoiceValidationResult(
            isValid: empty($errors),
            errors: $errors,
            warnings: [],
            corrections: []
        );
    }

    /**
     * Validates a VAT number format.
     *
     * @param  string  $vatNumber  The VAT number to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidVatNumber(string $vatNumber): bool
    {
        return self::validateVatNumber($vatNumber) === null;
    }

    /**
     * Validates a VAT number and returns an error message if invalid.
     * Use this method for pre-validation before generating UBL to show user-friendly errors.
     *
     * @param  string|null  $vatNumber  The VAT number to validate (null or empty is allowed for B2C)
     * @return string|null Error message if invalid, null if valid or empty
     */
    public static function validateVatNumber(?string $vatNumber): ?string
    {
        // Empty VAT number is allowed (B2C or unknown)
        if (empty($vatNumber)) {
            return null;
        }

        $vatNumber = trim($vatNumber);

        // Basic check: at least 2 characters for country code + 1 character for the number
        if (strlen($vatNumber) < 3) {
            return 'VAT number must be at least 3 characters (2-letter country code + number)';
        }

        $countryCode = strtoupper(substr($vatNumber, 0, 2));
        $number = substr($vatNumber, 2);

        // Check if it starts with 2 letters
        if (! preg_match('/^[A-Z]{2}/', strtoupper($vatNumber))) {
            return "VAT number must start with a 2-letter country code (e.g., 'NL', 'BE'). Got: '{$vatNumber}'";
        }

        // Check if country code is valid (EU + XI for Northern Ireland)
        $validCountryCodes = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'GB', 'HR', 'HU', 'IE', 'IT', 'LT',
            'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
        ];

        if (! in_array($countryCode, $validCountryCodes, true)) {
            return "Invalid country code '{$countryCode}' in VAT number. Must be a valid EU country code.";
        }

        // Basic format check (alphanumeric, no spaces)
        if (! ctype_alnum($number) || preg_match('/\s/', $number)) {
            return 'VAT number can only contain letters and numbers (no spaces or special characters)';
        }

        return null;
    }

    /**
     * Validates invoice data before UBL generation.
     * Returns an array of error messages, empty if all valid.
     *
     * @param  array  $data  Invoice data to validate
     * @return array Array of error messages (empty if valid)
     */
    public static function validateInvoiceData(array $data): array
    {
        $errors = [];

        // Validate supplier VAT number
        if (isset($data['supplier_vat_number'])) {
            $error = self::validateVatNumber($data['supplier_vat_number']);
            if ($error) {
                $errors[] = "Supplier: {$error}";
            }
        }

        // Validate customer VAT number (optional for B2C)
        if (isset($data['customer_vat_number']) && ! empty($data['customer_vat_number'])) {
            $error = self::validateVatNumber($data['customer_vat_number']);
            if ($error) {
                $errors[] = "Customer: {$error}";
            }
        }

        // Validate IBAN if provided
        if (isset($data['iban']) && ! empty($data['iban'])) {
            $error = self::validateIban($data['iban']);
            if ($error) {
                $errors[] = "Bank account: {$error}";
            }
        }

        // Validate required fields
        $requiredFields = [
            'invoice_number' => 'Invoice number',
            'issue_date' => 'Invoice date',
            'due_date' => 'Due date',
            'supplier_name' => 'Supplier name',
            'customer_name' => 'Customer name',
        ];

        foreach ($requiredFields as $field => $label) {
            if (! isset($data[$field]) || empty(trim($data[$field] ?? ''))) {
                $errors[] = "{$label} is required";
            }
        }

        return $errors;
    }

    /**
     * Validates an IBAN and returns an error message if invalid.
     *
     * @param  string|null  $iban  The IBAN to validate
     * @return string|null Error message if invalid, null if valid or empty
     */
    public static function validateIban(?string $iban): ?string
    {
        if (empty($iban)) {
            return null;
        }

        // Normalize IBAN (remove spaces and convert to uppercase)
        $iban = strtoupper(str_replace(' ', '', $iban));

        // Check minimum length
        if (strlen($iban) < 15) {
            return 'IBAN is too short';
        }

        // Check maximum length
        if (strlen($iban) > 34) {
            return 'IBAN is too long';
        }

        // Check format: 2 letters + 2 digits + alphanumeric
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return 'Invalid IBAN format';
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
        if ((int) bcmod($converted, '97') !== 1) {
            return 'Invalid IBAN checksum';
        }

        return null;
    }

    /**
     * Validates invoice totals according to EN16931/Peppol BIS Billing 3.0 rules.
     *
     * This validates:
     * - BR-CO-10: Sum of Invoice line net amounts = Line extension amount
     * - BR-CO-11: Sum of allowances on document level = AllowanceTotalAmount
     * - BR-CO-12: Sum of charges on document level = ChargeTotalAmount
     * - BR-CO-13: Invoice total amount without VAT = Line extension amount - allowances + charges
     * - BR-CO-15: Invoice total amount with VAT = Invoice total without VAT + Invoice total VAT amount
     * - BR-CO-16: Amount due for payment = Invoice total with VAT - Paid amount
     * - BR-S-08: For each VAT category: TaxableAmount = sum of line amounts - allowances + charges for that category
     *
     * @param  array  $invoiceLines  Array of invoice lines with 'line_extension_amount' key
     * @param  array  $totals  Array with keys: line_extension_amount, tax_exclusive_amount, tax_inclusive_amount, payable_amount
     * @param  array  $taxTotals  Array of tax subtotals with 'taxable_amount', 'tax_amount', 'tax_percent' keys
     * @param  float  $allowanceTotalAmount  Total of allowances from LegalMonetaryTotal (default 0)
     * @param  float  $chargeTotalAmount  Total of charges from LegalMonetaryTotal (default 0)
     * @param  float  $prepaidAmount  Amount already paid (default 0)
     * @param  array  $documentAllowances  Array of document-level allowances with 'amount', optionally 'tax_category_id', 'tax_percent'
     * @param  array  $documentCharges  Array of document-level charges with 'amount', optionally 'tax_category_id', 'tax_percent'
     */
    public static function validateInvoiceTotals(
        array $invoiceLines,
        array $totals,
        array $taxTotals,
        float $allowanceTotalAmount = 0.0,
        float $chargeTotalAmount = 0.0,
        float $prepaidAmount = 0.0,
        array $documentAllowances = [],
        array $documentCharges = []
    ): InvoiceValidationResult {
        $errors = [];
        $warnings = [];

        // BR-CO-11: Sum of allowances on document level = AllowanceTotalAmount
        $sumOfAllowances = 0.0;
        foreach ($documentAllowances as $allowance) {
            $sumOfAllowances += (float) ($allowance['amount'] ?? 0);
        }
        if (abs($sumOfAllowances - $allowanceTotalAmount) > 0.01) {
            $errors[] = sprintf(
                'BR-CO-11: Sum of document allowances (%.2f) does not match AllowanceTotalAmount (%.2f)',
                $sumOfAllowances,
                $allowanceTotalAmount
            );
        }

        // BR-CO-12: Sum of charges on document level = ChargeTotalAmount
        $sumOfCharges = 0.0;
        foreach ($documentCharges as $charge) {
            $sumOfCharges += (float) ($charge['amount'] ?? 0);
        }
        if (abs($sumOfCharges - $chargeTotalAmount) > 0.01) {
            $errors[] = sprintf(
                'BR-CO-12: Sum of document charges (%.2f) does not match ChargeTotalAmount (%.2f)',
                $sumOfCharges,
                $chargeTotalAmount
            );
        }

        // Calculate sum of invoice line amounts and validate each line
        $sumOfLineAmounts = 0.0;
        foreach ($invoiceLines as $index => $line) {
            $lineId = $line['id'] ?? ($index + 1);

            if (! isset($line['line_extension_amount'])) {
                // Calculate from price_amount * quantity if not set
                if (isset($line['price_amount'], $line['quantity'])) {
                    $lineAmount = (float) $line['price_amount'] * (float) $line['quantity'];
                } else {
                    $errors[] = "Line {$lineId}: Missing line_extension_amount or price_amount/quantity";

                    continue;
                }
            } else {
                $lineAmount = (float) $line['line_extension_amount'];

                // BR-CALC-01: Validate that line_extension_amount = price_amount × quantity
                if (isset($line['price_amount'], $line['quantity'])) {
                    $expectedLineAmount = round((float) $line['price_amount'] * (float) $line['quantity'], 2);
                    if (abs($lineAmount - $expectedLineAmount) > 0.01) {
                        $errors[] = sprintf(
                            'Line %s: LineExtensionAmount (%.2f) does not match PriceAmount (%.2f) × Quantity (%.2f) = %.2f',
                            $lineId,
                            $lineAmount,
                            (float) $line['price_amount'],
                            (float) $line['quantity'],
                            $expectedLineAmount
                        );
                    }
                }
            }
            $sumOfLineAmounts += $lineAmount;
        }

        // BR-CO-10: Sum of Invoice line net amounts = Line extension amount
        $lineExtensionAmount = (float) ($totals['line_extension_amount'] ?? 0);
        if (abs($sumOfLineAmounts - $lineExtensionAmount) > 0.01) {
            $errors[] = sprintf(
                'BR-CO-10: Sum of invoice lines (%.2f) does not match LineExtensionAmount (%.2f). Difference: %.2f',
                $sumOfLineAmounts,
                $lineExtensionAmount,
                $sumOfLineAmounts - $lineExtensionAmount
            );
        }

        // Calculate expected tax exclusive amount
        $expectedTaxExclusiveAmount = $lineExtensionAmount - $allowanceTotalAmount + $chargeTotalAmount;
        $taxExclusiveAmount = (float) ($totals['tax_exclusive_amount'] ?? 0);

        // BR-CO-13: Invoice total amount without VAT
        if (abs($expectedTaxExclusiveAmount - $taxExclusiveAmount) > 0.01) {
            $errors[] = sprintf(
                'BR-CO-13: TaxExclusiveAmount (%.2f) must equal LineExtensionAmount (%.2f) - allowances (%.2f) + charges (%.2f) = %.2f',
                $taxExclusiveAmount,
                $lineExtensionAmount,
                $allowanceTotalAmount,
                $chargeTotalAmount,
                $expectedTaxExclusiveAmount
            );
        }

        // Calculate and validate tax amounts per category (BR-S-08)
        // TaxableAmount per category = sum of line amounts - allowances + charges for that category
        $calculatedTaxByCategory = [];

        // First, sum line amounts by category
        foreach ($invoiceLines as $line) {
            $taxCategoryId = $line['tax_category_id'] ?? 'S';
            $taxPercent = (float) ($line['tax_percent'] ?? 21);
            $lineAmount = (float) ($line['line_extension_amount'] ?? ((float) $line['price_amount'] * (float) $line['quantity']));

            $key = $taxCategoryId.'_'.$taxPercent;
            if (! isset($calculatedTaxByCategory[$key])) {
                $calculatedTaxByCategory[$key] = [
                    'line_amount' => 0.0,
                    'allowance_amount' => 0.0,
                    'charge_amount' => 0.0,
                    'taxable_amount' => 0.0,
                    'tax_percent' => $taxPercent,
                    'tax_category_id' => $taxCategoryId,
                ];
            }
            $calculatedTaxByCategory[$key]['line_amount'] += $lineAmount;
        }

        // Subtract document-level allowances per category
        foreach ($documentAllowances as $allowance) {
            $taxCategoryId = $allowance['tax_category_id'] ?? 'S';
            $taxPercent = (float) ($allowance['tax_percent'] ?? 21);
            $amount = (float) ($allowance['amount'] ?? 0);

            $key = $taxCategoryId.'_'.$taxPercent;
            if (! isset($calculatedTaxByCategory[$key])) {
                $calculatedTaxByCategory[$key] = [
                    'line_amount' => 0.0,
                    'allowance_amount' => 0.0,
                    'charge_amount' => 0.0,
                    'taxable_amount' => 0.0,
                    'tax_percent' => $taxPercent,
                    'tax_category_id' => $taxCategoryId,
                ];
            }
            $calculatedTaxByCategory[$key]['allowance_amount'] += $amount;
        }

        // Add document-level charges per category
        foreach ($documentCharges as $charge) {
            $taxCategoryId = $charge['tax_category_id'] ?? 'S';
            $taxPercent = (float) ($charge['tax_percent'] ?? 21);
            $amount = (float) ($charge['amount'] ?? 0);

            $key = $taxCategoryId.'_'.$taxPercent;
            if (! isset($calculatedTaxByCategory[$key])) {
                $calculatedTaxByCategory[$key] = [
                    'line_amount' => 0.0,
                    'allowance_amount' => 0.0,
                    'charge_amount' => 0.0,
                    'taxable_amount' => 0.0,
                    'tax_percent' => $taxPercent,
                    'tax_category_id' => $taxCategoryId,
                ];
            }
            $calculatedTaxByCategory[$key]['charge_amount'] += $amount;
        }

        // Calculate final taxable amounts per category
        foreach ($calculatedTaxByCategory as $key => &$category) {
            $category['taxable_amount'] = $category['line_amount'] - $category['allowance_amount'] + $category['charge_amount'];
        }
        unset($category);

        // Calculate expected tax amounts
        $totalCalculatedTax = 0.0;
        foreach ($calculatedTaxByCategory as $key => &$category) {
            $category['calculated_tax'] = round($category['taxable_amount'] * ($category['tax_percent'] / 100), 2);
            $totalCalculatedTax += $category['calculated_tax'];
        }
        unset($category);

        // Validate tax subtotals (BR-S-08)
        $totalProvidedTax = 0.0;
        $totalProvidedTaxableAmount = 0.0;
        foreach ($taxTotals as $index => $taxSubtotal) {
            $taxableAmount = (float) ($taxSubtotal['taxable_amount'] ?? 0);
            $taxAmount = (float) ($taxSubtotal['tax_amount'] ?? 0);
            $taxPercent = (float) ($taxSubtotal['tax_percent'] ?? 0);
            $taxCategoryId = $taxSubtotal['tax_category_id'] ?? 'S';

            $totalProvidedTax += $taxAmount;
            $totalProvidedTaxableAmount += $taxableAmount;

            // BR-S-08: Check if taxable amount matches calculated (line amounts - allowances + charges)
            $key = $taxCategoryId.'_'.$taxPercent;
            if (isset($calculatedTaxByCategory[$key])) {
                $expectedTaxableAmount = $calculatedTaxByCategory[$key]['taxable_amount'];
                if (abs($taxableAmount - $expectedTaxableAmount) > 0.01) {
                    $cat = $calculatedTaxByCategory[$key];
                    $errors[] = sprintf(
                        'BR-S-08 TaxSubtotal %d: TaxableAmount (%.2f) does not match calculated for category %s %.0f%% (lines: %.2f - allowances: %.2f + charges: %.2f = %.2f)',
                        $index + 1,
                        $taxableAmount,
                        $taxCategoryId,
                        $taxPercent,
                        $cat['line_amount'],
                        $cat['allowance_amount'],
                        $cat['charge_amount'],
                        $expectedTaxableAmount
                    );
                }

                // Check tax amount calculation
                $expectedTaxAmount = round($taxableAmount * ($taxPercent / 100), 2);
                if (abs($taxAmount - $expectedTaxAmount) > 0.01) {
                    $errors[] = sprintf(
                        'TaxSubtotal %d: TaxAmount (%.2f) does not match calculation: %.2f × %.0f%% = %.2f',
                        $index + 1,
                        $taxAmount,
                        $taxableAmount,
                        $taxPercent,
                        $expectedTaxAmount
                    );
                }
            }
        }

        // Check if total taxable amount matches tax exclusive amount (with allowances/charges)
        $expectedTotalTaxableAmount = $lineExtensionAmount - $allowanceTotalAmount + $chargeTotalAmount;
        if (abs($totalProvidedTaxableAmount - $expectedTotalTaxableAmount) > 0.01) {
            $errors[] = sprintf(
                'Sum of TaxableAmounts (%.2f) does not match TaxExclusiveAmount (LineExtension %.2f - Allowances %.2f + Charges %.2f = %.2f)',
                $totalProvidedTaxableAmount,
                $lineExtensionAmount,
                $allowanceTotalAmount,
                $chargeTotalAmount,
                $expectedTotalTaxableAmount
            );
        }

        // BR-CO-15: Invoice total amount with VAT = Invoice total without VAT + Invoice total VAT amount
        $taxInclusiveAmount = (float) ($totals['tax_inclusive_amount'] ?? 0);
        $expectedTaxInclusiveAmount = $taxExclusiveAmount + $totalProvidedTax;
        if (abs($taxInclusiveAmount - $expectedTaxInclusiveAmount) > 0.01) {
            $errors[] = sprintf(
                'BR-CO-15: TaxInclusiveAmount (%.2f) must equal TaxExclusiveAmount (%.2f) + TaxAmount (%.2f) = %.2f',
                $taxInclusiveAmount,
                $taxExclusiveAmount,
                $totalProvidedTax,
                $expectedTaxInclusiveAmount
            );
        }

        // BR-CO-16: Amount due for payment = Invoice total with VAT - Paid amount
        $payableAmount = (float) ($totals['payable_amount'] ?? 0);
        $expectedPayableAmount = $taxInclusiveAmount - $prepaidAmount;
        if (abs($payableAmount - $expectedPayableAmount) > 0.01) {
            $errors[] = sprintf(
                'BR-CO-16: PayableAmount (%.2f) must equal TaxInclusiveAmount (%.2f) - PrepaidAmount (%.2f) = %.2f',
                $payableAmount,
                $taxInclusiveAmount,
                $prepaidAmount,
                $expectedPayableAmount
            );
        }

        // Add correction suggestions
        $corrections = [];
        if (! empty($errors)) {
            $corrections = [
                'line_extension_amount' => round($sumOfLineAmounts, 2),
                'allowance_total_amount' => round($sumOfAllowances, 2),
                'charge_total_amount' => round($sumOfCharges, 2),
                'tax_exclusive_amount' => round($sumOfLineAmounts - $sumOfAllowances + $sumOfCharges, 2),
                'total_tax_amount' => round($totalCalculatedTax, 2),
                'tax_inclusive_amount' => round($sumOfLineAmounts - $sumOfAllowances + $sumOfCharges + $totalCalculatedTax, 2),
                'payable_amount' => round($sumOfLineAmounts - $sumOfAllowances + $sumOfCharges + $totalCalculatedTax - $prepaidAmount, 2),
                'tax_subtotals' => array_values(array_map(function ($cat) {
                    return [
                        'tax_category_id' => $cat['tax_category_id'],
                        'tax_percent' => $cat['tax_percent'],
                        'line_amount' => round($cat['line_amount'], 2),
                        'allowance_amount' => round($cat['allowance_amount'], 2),
                        'charge_amount' => round($cat['charge_amount'], 2),
                        'taxable_amount' => round($cat['taxable_amount'], 2),
                        'tax_amount' => $cat['calculated_tax'],
                    ];
                }, $calculatedTaxByCategory)),
            ];
        }

        return new InvoiceValidationResult(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            corrections: $corrections
        );
    }
}
