<?php

namespace Darvis\UblPeppol;

class CompanyRegistrationService
{
    /**
     * Validate company registration number for various European countries
     *
     * @param string $number Company registration number
     * @param string $countryCode ISO 2-letter country code (NL, BE, LU, FR, DE)
     * @return array Validation result with details
     */
    public function validate(string $number, string $countryCode): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $cleanNumber = $this->cleanNumber($number);

        return match ($countryCode) {
            'NL' => $this->validateNL($cleanNumber),
            'BE' => $this->validateBE($cleanNumber),
            'LU' => $this->validateLU($cleanNumber),
            'FR' => $this->validateFR($cleanNumber),
            'DE' => $this->validateDE($cleanNumber),
            default => [
                'valid' => false,
                'country' => $countryCode,
                'number' => $number,
                'type' => null,
                'error' => 'Unsupported country code: ' . $countryCode,
            ],
        };
    }

    /**
     * Validate Dutch KVK number (8 digits)
     */
    private function validateNL(string $number): array
    {
        $pattern = '/^[0-9]{8}$/';
        $valid = preg_match($pattern, $number) === 1;

        return [
            'valid' => $valid,
            'country' => 'NL',
            'country_name' => 'Netherlands',
            'number' => $number,
            'formatted' => $valid ? $number : null,
            'type' => 'KVK',
            'type_name' => 'Kamer van Koophandel',
            'error' => $valid ? null : 'Invalid format. Expected 8 digits.',
        ];
    }

    /**
     * Validate Belgian KBO number (10 digits with mod97 checksum)
     */
    private function validateBE(string $number): array
    {
        $pattern = '/^[0-9]{10}$/';
        
        if (preg_match($pattern, $number) !== 1) {
            return [
                'valid' => false,
                'country' => 'BE',
                'country_name' => 'Belgium',
                'number' => $number,
                'formatted' => null,
                'type' => 'KBO',
                'type_name' => 'Kruispuntbank van Ondernemingen',
                'error' => 'Invalid format. Expected 10 digits.',
            ];
        }

        // Validate mod97 checksum
        $basis = (int) substr($number, 0, 8);
        $checksum = (int) substr($number, 8, 2);
        $valid = $checksum === (97 - ($basis % 97));

        return [
            'valid' => $valid,
            'country' => 'BE',
            'country_name' => 'Belgium',
            'number' => $number,
            'formatted' => $valid ? $number : null,
            'type' => 'KBO',
            'type_name' => 'Kruispuntbank van Ondernemingen',
            'error' => $valid ? null : 'Invalid checksum. KBO number failed mod97 validation.',
        ];
    }

    /**
     * Validate Luxembourg RCS number (1 letter + 6 digits)
     */
    private function validateLU(string $number): array
    {
        $pattern = '/^[A-Z][0-9]{6}$/';
        $valid = preg_match($pattern, strtoupper($number)) === 1;

        return [
            'valid' => $valid,
            'country' => 'LU',
            'country_name' => 'Luxembourg',
            'number' => $number,
            'formatted' => $valid ? strtoupper($number) : null,
            'type' => 'RCS',
            'type_name' => 'Registre de Commerce et des Sociétés',
            'error' => $valid ? null : 'Invalid format. Expected 1 letter + 6 digits (e.g., B123456).',
        ];
    }

    /**
     * Validate French SIREN (9 digits) or SIRET (14 digits)
     */
    private function validateFR(string $number): array
    {
        $length = strlen($number);

        if ($length === 9) {
            // SIREN validation
            $pattern = '/^[0-9]{9}$/';
            $valid = preg_match($pattern, $number) === 1;

            return [
                'valid' => $valid,
                'country' => 'FR',
                'country_name' => 'France',
                'number' => $number,
                'formatted' => $valid ? $number : null,
                'type' => 'SIREN',
                'type_name' => 'Système d\'Identification du Répertoire des Entreprises',
                'error' => $valid ? null : 'Invalid SIREN format. Expected 9 digits.',
            ];
        } elseif ($length === 14) {
            // SIRET validation (SIREN + NIC)
            $pattern = '/^[0-9]{14}$/';
            $valid = preg_match($pattern, $number) === 1;

            return [
                'valid' => $valid,
                'country' => 'FR',
                'country_name' => 'France',
                'number' => $number,
                'formatted' => $valid ? $number : null,
                'type' => 'SIRET',
                'type_name' => 'Système d\'Identification du Répertoire des Établissements',
                'siren' => $valid ? substr($number, 0, 9) : null,
                'nic' => $valid ? substr($number, 9, 5) : null,
                'error' => $valid ? null : 'Invalid SIRET format. Expected 14 digits.',
            ];
        }

        return [
            'valid' => false,
            'country' => 'FR',
            'country_name' => 'France',
            'number' => $number,
            'formatted' => null,
            'type' => null,
            'error' => 'Invalid format. Expected 9 digits (SIREN) or 14 digits (SIRET).',
        ];
    }

    /**
     * Validate German Handelsregister number (HRA/HRB + 1-6 digits)
     */
    private function validateDE(string $number): array
    {
        // Accept both with and without spaces
        $upperNumber = strtoupper($number);
        $pattern = '/^HR[AB]\s?[0-9]{1,6}$/';
        $valid = preg_match($pattern, $upperNumber) === 1;

        if ($valid) {
            // Extract type and number
            preg_match('/^(HR[AB])\s?([0-9]{1,6})$/', $upperNumber, $matches);
            $type = $matches[1];
            $registrationNumber = $matches[2];

            return [
                'valid' => true,
                'country' => 'DE',
                'country_name' => 'Germany',
                'number' => $number,
                'formatted' => $type . ' ' . $registrationNumber,
                'type' => $type,
                'type_name' => $type === 'HRA' ? 'Handelsregister Abteilung A (Personengesellschaften)' : 'Handelsregister Abteilung B (Kapitalgesellschaften)',
                'registration_number' => $registrationNumber,
                'error' => null,
            ];
        }

        return [
            'valid' => false,
            'country' => 'DE',
            'country_name' => 'Germany',
            'number' => $number,
            'formatted' => null,
            'type' => null,
            'error' => 'Invalid format. Expected HRA or HRB followed by 1-6 digits (e.g., HRB 12345).',
        ];
    }

    /**
     * Clean number by removing common separators
     */
    private function cleanNumber(string $number): string
    {
        // Remove spaces, dots, dashes, but keep letters for LU and DE
        return preg_replace('/[\s.\-]/', '', trim($number));
    }

    /**
     * Get supported countries
     */
    public function getSupportedCountries(): array
    {
        return [
            'NL' => [
                'name' => 'Netherlands',
                'type' => 'KVK',
                'type_name' => 'Kamer van Koophandel',
                'format' => '8 digits',
                'example' => '12345678',
            ],
            'BE' => [
                'name' => 'Belgium',
                'type' => 'KBO',
                'type_name' => 'Kruispuntbank van Ondernemingen',
                'format' => '10 digits with mod97 checksum',
                'example' => '0681845662',
            ],
            'LU' => [
                'name' => 'Luxembourg',
                'type' => 'RCS',
                'type_name' => 'Registre de Commerce et des Sociétés',
                'format' => '1 letter + 6 digits',
                'example' => 'B123456',
            ],
            'FR' => [
                'name' => 'France',
                'type' => 'SIREN/SIRET',
                'type_name' => 'SIREN (9 digits) or SIRET (14 digits)',
                'format' => '9 or 14 digits',
                'example' => '732829320 or 73282932000074',
            ],
            'DE' => [
                'name' => 'Germany',
                'type' => 'Handelsregister',
                'type_name' => 'HRA (Personengesellschaften) or HRB (Kapitalgesellschaften)',
                'format' => 'HRA/HRB + 1-6 digits',
                'example' => 'HRB 12345',
            ],
        ];
    }
}
