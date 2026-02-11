<?php

namespace Darvis\UblPeppol;

use SoapClient;
use SoapFault;

class ViesService
{
    private const WSDL_URL = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService.wsdl';

    /**
     * Check VAT number via VIES (EU VAT Information Exchange System)
     *
     * @param  string  $countryCode  ISO 2-letter country code (e.g., NL, BE, DE)
     * @param  string  $vatNumber  VAT number without country prefix
     */
    public function checkVat(string $countryCode, string $vatNumber): array
    {
        try {
            $client = new SoapClient(
                self::WSDL_URL,
                [
                    'exceptions' => true,
                    'connection_timeout' => 10,
                    'cache_wsdl' => WSDL_CACHE_MEMORY,
                ]
            );

            // Clean up the VAT number - remove spaces and country prefix if present
            $cleanVatNumber = preg_replace('/\s+/', '', $vatNumber);
            $cleanCountryCode = strtoupper(trim($countryCode));

            // Remove country code prefix from VAT number if present
            if (str_starts_with(strtoupper($cleanVatNumber), $cleanCountryCode)) {
                $cleanVatNumber = substr($cleanVatNumber, strlen($cleanCountryCode));
            }

            $response = $client->checkVat([
                'countryCode' => $cleanCountryCode,
                'vatNumber' => $cleanVatNumber,
            ]);

            return [
                'valid' => (bool) $response->valid,
                'name' => trim((string) ($response->name ?? '')),
                'address' => trim((string) ($response->address ?? '')),
                'countryCode' => $cleanCountryCode,
                'vatNumber' => $cleanVatNumber,
                'fullVatNumber' => $cleanCountryCode.$cleanVatNumber,
                'checked_at' => date('Y-m-d H:i:s'),
                'error' => null,
            ];
        } catch (SoapFault $e) {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'countryCode' => $countryCode,
                'vatNumber' => $vatNumber,
                'fullVatNumber' => null,
                'checked_at' => date('Y-m-d H:i:s'),
                'error' => $this->translateError($e->getMessage()),
            ];
        }
    }

    /**
     * Check VAT number from full VAT number (with country prefix)
     *
     * @param  string  $fullVatNumber  Full VAT number including country code (e.g., NL123456789B01)
     */
    public function checkFullVatNumber(string $fullVatNumber): array
    {
        $cleanVat = preg_replace('/\s+/', '', $fullVatNumber);

        if (strlen($cleanVat) < 3) {
            return [
                'valid' => false,
                'error' => 'VAT number too short',
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        }

        $countryCode = strtoupper(substr($cleanVat, 0, 2));
        $vatNumber = substr($cleanVat, 2);

        return $this->checkVat($countryCode, $vatNumber);
    }

    /**
     * Translate VIES error messages
     */
    private function translateError(string $error): string
    {
        $translations = [
            'INVALID_INPUT' => 'Invalid VAT number format',
            'SERVICE_UNAVAILABLE' => 'VIES service temporarily unavailable',
            'MS_UNAVAILABLE' => 'Member state service unavailable',
            'TIMEOUT' => 'Connection timeout',
            'SERVER_BUSY' => 'Server is busy, please try again later',
            'MS_MAX_CONCURRENT_REQ' => 'Too many concurrent requests',
            'GLOBAL_MAX_CONCURRENT_REQ' => 'Too many concurrent requests',
        ];

        foreach ($translations as $key => $translation) {
            if (str_contains($error, $key)) {
                return $translation;
            }
        }

        return 'VIES check failed: '.$error;
    }
}
