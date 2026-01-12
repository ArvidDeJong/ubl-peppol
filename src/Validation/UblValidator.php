<?php

namespace Darvis\UblPeppol\Validation;

use Darvis\UblPeppol\Constants\UnitCodes;
use Darvis\UblPeppol\Validation\InvoiceValidationResult;

class UblValidator
{
    /**
     * Validates if the given unit code is a valid UN/ECE Recommendation 20 with Rec 21 extension unit code.
     *
     * @param string $unitCode The unit code to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidUnitCode(string $unitCode): bool
    {
        return UnitCodes::isValid($unitCode);
    }

    /**
     * Validates if the given classification scheme ID is valid according to UNTDID 7143.
     *
     * @param string $schemeId The classification scheme ID to validate
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
            'UA', 'UP', 'VN', 'VP', 'VS', 'VX', 'ZZZ'
        ];

        // PEPPOL specific schemes
        $peppolSchemes = [
            'CPV' => 'Common Procurement Vocabulary',
            'SRV' => 'Service Type Code'
        ];

        $schemeId = strtoupper(trim($schemeId));
        
        // Check if it's a standard UNTDID 7143 scheme or a PEPPOL specific scheme
        return in_array($schemeId, $validSchemes, true) || array_key_exists($schemeId, $peppolSchemes);
    }
    
    /**
     * Gets the description of a classification scheme
     *
     * @param string $schemeId The scheme ID
     * @return string The scheme description or empty string if not found
     */
    public static function getClassificationSchemeDescription(string $schemeId): string
    {
        $descriptions = [
            'CPV' => 'Common Procurement Vocabulary',
            'SRV' => 'Service Type Code',
            'STD' => 'Standard',
            'HS' => 'Harmonized System',
            'GS1' => 'GS1 Global Trade Item Number'
            // Add more scheme descriptions as needed
        ];
        
        return $descriptions[strtoupper($schemeId)] ?? '';
    }

    /**
     * Validates if the given tax category ID is valid.
     *
     * @param string $categoryId The tax category ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidTaxCategory(string $categoryId): bool
    {
        $validCategories = ['S', 'Z', 'E', 'AE', 'K', 'G', 'O'];
        return in_array(strtoupper($categoryId), $validCategories, true);
    }

    /**
     * Validates a VAT number format.
     *
     * @param string $vatNumber The VAT number to validate
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
     * @param string|null $vatNumber The VAT number to validate (null or empty is allowed for B2C)
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
        if (!preg_match('/^[A-Z]{2}/', strtoupper($vatNumber))) {
            return "VAT number must start with a 2-letter country code (e.g., 'NL', 'BE'). Got: '{$vatNumber}'";
        }

        // Check if country code is valid (EU + XI for Northern Ireland)
        $validCountryCodes = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'GB', 'HR', 'HU', 'IE', 'IT', 'LT',
            'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI'
        ];

        if (!in_array($countryCode, $validCountryCodes, true)) {
            return "Invalid country code '{$countryCode}' in VAT number. Must be a valid EU country code.";
        }

        // Basic format check (alphanumeric, no spaces)
        if (!ctype_alnum($number) || preg_match('/\s/', $number)) {
            return 'VAT number can only contain letters and numbers (no spaces or special characters)';
        }

        return null;
    }

    /**
     * Validates invoice data before UBL generation.
     * Returns an array of error messages, empty if all valid.
     *
     * @param array $data Invoice data to validate
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
        if (isset($data['customer_vat_number']) && !empty($data['customer_vat_number'])) {
            $error = self::validateVatNumber($data['customer_vat_number']);
            if ($error) {
                $errors[] = "Customer: {$error}";
            }
        }

        // Validate IBAN if provided
        if (isset($data['iban']) && !empty($data['iban'])) {
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
            if (!isset($data[$field]) || empty(trim($data[$field] ?? ''))) {
                $errors[] = "{$label} is required";
            }
        }

        return $errors;
    }

    /**
     * Validates an IBAN and returns an error message if invalid.
     *
     * @param string|null $iban The IBAN to validate
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
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return 'Invalid IBAN format';
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
        if ((int)bcmod($converted, '97') !== 1) {
            return 'Invalid IBAN checksum';
        }

        return null;
    }

    /**
     * Validates invoice totals according to EN16931/Peppol BIS Billing 3.0 rules.
     * 
     * This validates:
     * - BR-CO-10: Sum of Invoice line net amounts = Line extension amount
     * - BR-CO-13: Invoice total amount without VAT = Line extension amount - allowances + charges
     * - BR-CO-15: Invoice total amount with VAT = Invoice total without VAT + Invoice total VAT amount
     * - BR-CO-16: Amount due for payment = Invoice total with VAT - Paid amount
     * 
     * @param array $invoiceLines Array of invoice lines with 'line_extension_amount' key
     * @param array $totals Array with keys: line_extension_amount, tax_exclusive_amount, tax_inclusive_amount, payable_amount
     * @param array $taxTotals Array of tax subtotals with 'taxable_amount', 'tax_amount', 'tax_percent' keys
     * @param float $allowanceTotalAmount Total of allowances (default 0)
     * @param float $chargeTotalAmount Total of charges (default 0)
     * @param float $prepaidAmount Amount already paid (default 0)
     * @return InvoiceValidationResult
     */
    public static function validateInvoiceTotals(
        array $invoiceLines,
        array $totals,
        array $taxTotals,
        float $allowanceTotalAmount = 0.0,
        float $chargeTotalAmount = 0.0,
        float $prepaidAmount = 0.0
    ): InvoiceValidationResult {
        $errors = [];
        $warnings = [];
        
        // Calculate sum of invoice line amounts and validate each line
        $sumOfLineAmounts = 0.0;
        foreach ($invoiceLines as $index => $line) {
            $lineId = $line['id'] ?? ($index + 1);
            
            if (!isset($line['line_extension_amount'])) {
                // Calculate from price_amount * quantity if not set
                if (isset($line['price_amount'], $line['quantity'])) {
                    $lineAmount = (float)$line['price_amount'] * (float)$line['quantity'];
                } else {
                    $errors[] = "Line {$lineId}: Missing line_extension_amount or price_amount/quantity";
                    continue;
                }
            } else {
                $lineAmount = (float)$line['line_extension_amount'];
                
                // BR-CALC-01: Validate that line_extension_amount = price_amount × quantity
                if (isset($line['price_amount'], $line['quantity'])) {
                    $expectedLineAmount = round((float)$line['price_amount'] * (float)$line['quantity'], 2);
                    if (abs($lineAmount - $expectedLineAmount) > 0.01) {
                        $errors[] = sprintf(
                            "Line %s: LineExtensionAmount (%.2f) does not match PriceAmount (%.2f) × Quantity (%.2f) = %.2f",
                            $lineId,
                            $lineAmount,
                            (float)$line['price_amount'],
                            (float)$line['quantity'],
                            $expectedLineAmount
                        );
                    }
                }
            }
            $sumOfLineAmounts += $lineAmount;
        }
        
        // BR-CO-10: Sum of Invoice line net amounts = Line extension amount
        $lineExtensionAmount = (float)($totals['line_extension_amount'] ?? 0);
        if (abs($sumOfLineAmounts - $lineExtensionAmount) > 0.01) {
            $errors[] = sprintf(
                "BR-CO-10: Sum of invoice lines (%.2f) does not match LineExtensionAmount (%.2f). Difference: %.2f",
                $sumOfLineAmounts,
                $lineExtensionAmount,
                $sumOfLineAmounts - $lineExtensionAmount
            );
        }
        
        // Calculate expected tax exclusive amount
        $expectedTaxExclusiveAmount = $lineExtensionAmount - $allowanceTotalAmount + $chargeTotalAmount;
        $taxExclusiveAmount = (float)($totals['tax_exclusive_amount'] ?? 0);
        
        // BR-CO-13: Invoice total amount without VAT
        if (abs($expectedTaxExclusiveAmount - $taxExclusiveAmount) > 0.01) {
            $errors[] = sprintf(
                "BR-CO-13: TaxExclusiveAmount (%.2f) must equal LineExtensionAmount (%.2f) - allowances (%.2f) + charges (%.2f) = %.2f",
                $taxExclusiveAmount,
                $lineExtensionAmount,
                $allowanceTotalAmount,
                $chargeTotalAmount,
                $expectedTaxExclusiveAmount
            );
        }
        
        // Calculate and validate tax amounts per category
        $calculatedTaxByCategory = [];
        foreach ($invoiceLines as $line) {
            $taxCategoryId = $line['tax_category_id'] ?? 'S';
            $taxPercent = (float)($line['tax_percent'] ?? 21);
            $lineAmount = (float)($line['line_extension_amount'] ?? ((float)$line['price_amount'] * (float)$line['quantity']));
            
            $key = $taxCategoryId . '_' . $taxPercent;
            if (!isset($calculatedTaxByCategory[$key])) {
                $calculatedTaxByCategory[$key] = [
                    'taxable_amount' => 0.0,
                    'tax_percent' => $taxPercent,
                    'tax_category_id' => $taxCategoryId,
                ];
            }
            $calculatedTaxByCategory[$key]['taxable_amount'] += $lineAmount;
        }
        
        // Calculate expected tax amounts
        $totalCalculatedTax = 0.0;
        foreach ($calculatedTaxByCategory as $key => &$category) {
            $category['calculated_tax'] = round($category['taxable_amount'] * ($category['tax_percent'] / 100), 2);
            $totalCalculatedTax += $category['calculated_tax'];
        }
        unset($category);
        
        // Validate tax subtotals
        $totalProvidedTax = 0.0;
        $totalProvidedTaxableAmount = 0.0;
        foreach ($taxTotals as $index => $taxSubtotal) {
            $taxableAmount = (float)($taxSubtotal['taxable_amount'] ?? 0);
            $taxAmount = (float)($taxSubtotal['tax_amount'] ?? 0);
            $taxPercent = (float)($taxSubtotal['tax_percent'] ?? 0);
            $taxCategoryId = $taxSubtotal['tax_category_id'] ?? 'S';
            
            $totalProvidedTax += $taxAmount;
            $totalProvidedTaxableAmount += $taxableAmount;
            
            // Check if taxable amount matches calculated
            $key = $taxCategoryId . '_' . $taxPercent;
            if (isset($calculatedTaxByCategory[$key])) {
                $expectedTaxableAmount = $calculatedTaxByCategory[$key]['taxable_amount'];
                if (abs($taxableAmount - $expectedTaxableAmount) > 0.01) {
                    $errors[] = sprintf(
                        "TaxSubtotal %d: TaxableAmount (%.2f) does not match sum of lines for category %s %.0f%% (%.2f)",
                        $index + 1,
                        $taxableAmount,
                        $taxCategoryId,
                        $taxPercent,
                        $expectedTaxableAmount
                    );
                }
                
                // Check tax amount calculation
                $expectedTaxAmount = round($taxableAmount * ($taxPercent / 100), 2);
                if (abs($taxAmount - $expectedTaxAmount) > 0.01) {
                    $errors[] = sprintf(
                        "TaxSubtotal %d: TaxAmount (%.2f) does not match calculation: %.2f × %.0f%% = %.2f",
                        $index + 1,
                        $taxAmount,
                        $taxableAmount,
                        $taxPercent,
                        $expectedTaxAmount
                    );
                }
            }
        }
        
        // Check if total taxable amount matches line extension amount
        if (abs($totalProvidedTaxableAmount - $lineExtensionAmount) > 0.01) {
            $errors[] = sprintf(
                "Total TaxableAmount (%.2f) does not match LineExtensionAmount (%.2f)",
                $totalProvidedTaxableAmount,
                $lineExtensionAmount
            );
        }
        
        // BR-CO-15: Invoice total amount with VAT = Invoice total without VAT + Invoice total VAT amount
        $taxInclusiveAmount = (float)($totals['tax_inclusive_amount'] ?? 0);
        $expectedTaxInclusiveAmount = $taxExclusiveAmount + $totalProvidedTax;
        if (abs($taxInclusiveAmount - $expectedTaxInclusiveAmount) > 0.01) {
            $errors[] = sprintf(
                "BR-CO-15: TaxInclusiveAmount (%.2f) must equal TaxExclusiveAmount (%.2f) + TaxAmount (%.2f) = %.2f",
                $taxInclusiveAmount,
                $taxExclusiveAmount,
                $totalProvidedTax,
                $expectedTaxInclusiveAmount
            );
        }
        
        // BR-CO-16: Amount due for payment = Invoice total with VAT - Paid amount
        $payableAmount = (float)($totals['payable_amount'] ?? 0);
        $expectedPayableAmount = $taxInclusiveAmount - $prepaidAmount;
        if (abs($payableAmount - $expectedPayableAmount) > 0.01) {
            $errors[] = sprintf(
                "BR-CO-16: PayableAmount (%.2f) must equal TaxInclusiveAmount (%.2f) - PrepaidAmount (%.2f) = %.2f",
                $payableAmount,
                $taxInclusiveAmount,
                $prepaidAmount,
                $expectedPayableAmount
            );
        }
        
        // Add correction suggestions
        $corrections = [];
        if (!empty($errors)) {
            $corrections = [
                'line_extension_amount' => round($sumOfLineAmounts, 2),
                'tax_exclusive_amount' => round($sumOfLineAmounts - $allowanceTotalAmount + $chargeTotalAmount, 2),
                'total_tax_amount' => round($totalCalculatedTax, 2),
                'tax_inclusive_amount' => round($sumOfLineAmounts - $allowanceTotalAmount + $chargeTotalAmount + $totalCalculatedTax, 2),
                'payable_amount' => round($sumOfLineAmounts - $allowanceTotalAmount + $chargeTotalAmount + $totalCalculatedTax - $prepaidAmount, 2),
                'tax_subtotals' => array_values(array_map(function($cat) {
                    return [
                        'tax_category_id' => $cat['tax_category_id'],
                        'tax_percent' => $cat['tax_percent'],
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
