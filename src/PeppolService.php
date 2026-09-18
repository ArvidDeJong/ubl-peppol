<?php

namespace Darvis\UblPeppol;

use Darvis\UblPeppol\Models\PeppolLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PeppolService
{
    private string $baseUrl;

    private string $username;

    private string $password;

    public function __construct()
    {
        // Cast to string: the config falls back to env(), which is null when the variable is unset.
        // Without the cast, resolving this service from the container in an application that does not
        // send invoices fails with a TypeError before validateCredentials() ever runs.
        $this->baseUrl = (string) config('ubl-peppol.url');
        $this->username = (string) config('ubl-peppol.username');
        $this->password = (string) config('ubl-peppol.password');
    }

    /**
     * Send a UBL invoice to the Peppol network.
     *
     * The invoice is your own model, not one of ours: anything with an id and an invoice number
     * will do. Both end up on the log row, so the result can be traced back to the invoice.
     *
     * @param  object{id: int|string, invoice_nr?: string|null}  $invoice
     * @param  string  $ublXml  UBL XML content
     * @return array<string, mixed>
     */
    public function sendInvoice(object $invoice, string $ublXml): array
    {
        $this->validateCredentials();

        // Create log entry with status pending
        $peppolLog = PeppolLog::create([
            'invoice_id' => $invoice->id,
            'invoice_nr' => $invoice->invoice_nr ?? null,
            'status' => 'pending',
            'sent_at' => now(),
        ]);

        try {
            Log::info('Peppol: Sending invoice', [
                'invoice_id' => $invoice->id,
                'invoice_nr' => $invoice->invoice_nr ?? null,
            ]);

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/json',
                ])
                ->withBody($ublXml, 'application/xml')
                ->post($this->baseUrl);

            $statusCode = $response->status();
            $responseBody = $response->body();

            Log::info('Peppol: Response received', [
                'invoice_id' => $invoice->id,
                'status_code' => $statusCode,
                'response' => $responseBody,
            ]);

            if ($response->successful()) {
                // Update log with success
                $peppolLog->update([
                    'status' => 'success',
                    'http_status_code' => $statusCode,
                    'message' => 'Invoice successfully sent to Peppol network',
                    'response' => $response->json(),
                ]);

                // Mark invoice as sent to Peppol (if model supports this)
                if (method_exists($invoice, 'update')) {
                    $invoice->update(['peppol_sent_at' => now()]);
                }

                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'message' => 'Invoice successfully sent to Peppol network',
                    'response' => $response->json() ?? $responseBody,
                    'log_id' => $peppolLog->id,
                ];
            }

            // Update log with error
            $peppolLog->update([
                'status' => 'error',
                'http_status_code' => $statusCode,
                'message' => 'Error sending to Peppol network',
                'error' => $responseBody,
            ]);

            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' => 'Error sending to Peppol network',
                'error' => $responseBody,
                'log_id' => $peppolLog->id,
            ];

        } catch (\Exception $e) {
            Log::error('Peppol: Error sending', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            // Update log with error
            $peppolLog->update([
                'status' => 'error',
                'http_status_code' => 0,
                'message' => 'Error sending to Peppol network',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Error sending to Peppol network',
                'error' => $e->getMessage(),
                'log_id' => $peppolLog->id,
            ];
        }
    }

    /**
     * Send a UBL invoice directly (without Invoice model)
     */
    public function sendUblXml(string $ublXml, ?string $invoiceNumber = null): array
    {
        $this->validateCredentials();

        // Create log entry with status pending
        $peppolLog = PeppolLog::create([
            'invoice_nr' => $invoiceNumber,
            'status' => 'pending',
            'sent_at' => now(),
        ]);

        try {
            Log::info('Peppol: Sending UBL XML', [
                'invoice_nr' => $invoiceNumber,
            ]);

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/json',
                ])
                ->withBody($ublXml, 'application/xml')
                ->post($this->baseUrl);

            $statusCode = $response->status();
            $responseBody = $response->body();

            Log::info('Peppol: Response received', [
                'invoice_nr' => $invoiceNumber,
                'status_code' => $statusCode,
                'response' => $responseBody,
            ]);

            if ($response->successful()) {
                $peppolLog->update([
                    'status' => 'success',
                    'http_status_code' => $statusCode,
                    'message' => 'Invoice successfully sent to Peppol network',
                    'response' => $response->json(),
                ]);

                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'message' => 'Invoice successfully sent to Peppol network',
                    'response' => $response->json() ?? $responseBody,
                    'log_id' => $peppolLog->id,
                ];
            }

            $peppolLog->update([
                'status' => 'error',
                'http_status_code' => $statusCode,
                'message' => 'Error sending to Peppol network',
                'error' => $responseBody,
            ]);

            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' => 'Error sending to Peppol network',
                'error' => $responseBody,
                'log_id' => $peppolLog->id,
            ];

        } catch (\Exception $e) {
            Log::error('Peppol: Error sending', [
                'invoice_nr' => $invoiceNumber,
                'error' => $e->getMessage(),
            ]);

            $peppolLog->update([
                'status' => 'error',
                'http_status_code' => 0,
                'message' => 'Error sending to Peppol network',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Error sending to Peppol network',
                'error' => $e->getMessage(),
                'log_id' => $peppolLog->id,
            ];
        }
    }

    /**
     * Test the connection with the Peppol provider
     */
    public function testConnection(): array
    {
        $this->validateCredentials();

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl);

            return [
                'success' => $response->status() !== 401,
                'status_code' => $response->status(),
                'message' => $response->status() === 401
                    ? 'Authentication failed - check credentials'
                    : 'Connection successful',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Cannot connect to Peppol provider',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate that all credentials are configured
     */
    private function validateCredentials(): void
    {
        if (empty($this->baseUrl)) {
            throw new \RuntimeException('Peppol URL is not configured (PEPPOL_URL)');
        }

        if (empty($this->username)) {
            throw new \RuntimeException('Peppol username is not configured (PEPPOL_USERNAME)');
        }

        if (empty($this->password)) {
            throw new \RuntimeException('Peppol password is not configured (PEPPOL_PASSWORD)');
        }
    }

    /**
     * Get the current configuration (without password)
     */
    public function getConfig(): array
    {
        return [
            'url' => $this->baseUrl,
            'username' => $this->username,
            'password_configured' => ! empty($this->password),
        ];
    }
}
