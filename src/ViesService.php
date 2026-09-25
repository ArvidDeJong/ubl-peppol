<?php

namespace Darvis\UblPeppol;

use SoapClient;
use SoapFault;

class ViesService
{
    private const WSDL_URL = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService.wsdl';

    /**
     * Seconds to wait for VIES to connect and to answer. VIES is slow at times; without a limit a
     * request hangs until PHP gives up.
     */
    private const TIMEOUT = 15;

    /**
     * Check VAT number via VIES (EU VAT Information Exchange System)
     *
     * @param  string  $countryCode  ISO 2-letter country code (e.g., NL, BE, DE)
     * @param  string  $vatNumber  VAT number without country prefix
     */
    public function checkVat(string $countryCode, string $vatNumber): array
    {
        // SoapClient reads its answer under default_socket_timeout; limit it for this call only
        $previousTimeout = ini_set('default_socket_timeout', (string) self::TIMEOUT);

        try {
            $client = $this->createClient();

            // Clean up the VAT number - remove spaces and country prefix if present
            // preg_replace returns null on a regex failure; '' keeps the string functions below safe.
            $cleanVatNumber = preg_replace('/\s+/', '', $vatNumber) ?? '';
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
            return $this->unknown($countryCode, $vatNumber, $this->translateError($e->getMessage()));
        } catch (\Throwable $e) {
            // Anything else, such as a missing soap extension or a network error, is also "unknown",
            // never "invalid": error is not null, so the caller can tell the two apart
            return $this->unknown($countryCode, $vatNumber, 'VIES check failed: '.$e->getMessage());
        } finally {
            if ($previousTimeout !== false) {
                ini_set('default_socket_timeout', $previousTimeout);
            }
        }
    }

    /**
     * The SOAP client for VIES. The WSDL is cached in memory and on disk, so it is not fetched
     * again for every check.
     */
    protected function createClient(): SoapClient
    {
        if (! class_exists(SoapClient::class)) {
            throw new \RuntimeException('the soap PHP extension is not installed');
        }

        return new SoapClient(self::WSDL_URL, [
            'exceptions' => true,
            'connection_timeout' => self::TIMEOUT,
            'cache_wsdl' => WSDL_CACHE_BOTH,
        ]);
    }

    /**
     * The result when VIES gave no answer: valid is false and error says why.
     *
     * @return array<string, mixed>
     */
    private function unknown(string $countryCode, string $vatNumber, string $error): array
    {
        return [
            'valid' => false,
            'name' => null,
            'address' => null,
            'countryCode' => $countryCode,
            'vatNumber' => $vatNumber,
            'fullVatNumber' => null,
            'checked_at' => date('Y-m-d H:i:s'),
            'error' => $error,
        ];
    }

    /**
     * Check VAT number from full VAT number (with country prefix)
     *
     * @param  string  $fullVatNumber  Full VAT number including country code (e.g., NL123456789B01)
     */
    public function checkFullVatNumber(string $fullVatNumber): array
    {
        $cleanVat = preg_replace('/\s+/', '', $fullVatNumber) ?? '';

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
