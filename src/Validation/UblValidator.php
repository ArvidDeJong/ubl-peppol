<?php

namespace Darvis\UblPeppol\Validation;

use Darvis\UblPeppol\Constants\UnitCodes;

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
        // Basic check: at least 2 characters for country code + 1 character for the number
        if (strlen($vatNumber) < 3) {
            return false;
        }

        $countryCode = strtoupper(substr($vatNumber, 0, 2));
        $number = substr($vatNumber, 2);

        // Check if country code is valid (ISO 3166-1 alpha-2)
        $validCountryCodes = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'GB', 'HR', 'HU', 'IE', 'IT', 'LT',
            'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI'
        ];

        if (!in_array($countryCode, $validCountryCodes, true)) {
            return false;
        }

        // Basic format check (alphanumeric, no spaces)
        return ctype_alnum($number) && !preg_match('/\s/', $number);
    }
}
