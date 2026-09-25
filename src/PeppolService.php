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
        // The accessors return '' for an unset value. Without that, resolving this service in an
        // application that does not send invoices fails with a TypeError on these typed properties,
        // before validateCredentials() can report which setting is missing.
        $this->baseUrl = UblPeppolConfig::url();
        $this->username = UblPeppolConfig::username();
        $this->password = UblPeppolConfig::password();
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

        // Log entry with status pending, or null when the host app has no log table
        $peppolLog = $this->startLog([
            'invoice_id' => $invoice->id,
            'invoice_nr' => $invoice->invoice_nr ?? null,
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

            // The body can hold invoice data; it is kept on the log row, not in the application log
            Log::info('Peppol: Response received', [
                'invoice_id' => $invoice->id,
                'status_code' => $statusCode,
            ]);

            if ($response->successful()) {
                // Update log with success
                $peppolLog?->update([
                    'status' => 'success',
                    'http_status_code' => $statusCode,
                    'message' => 'Invoice successfully sent to Peppol network',
                    'response' => $response->json(),
                ]);

                // The document is sent now, whatever follows. Marking the host model is a courtesy:
                // a model without the column must not turn this send into a failure, which would
                // invite sending the invoice twice.
                $warning = $this->markSent($invoice);

                return array_filter([
                    'success' => true,
                    'status_code' => $statusCode,
                    'message' => 'Invoice successfully sent to Peppol network',
                    'response' => $response->json() ?? $responseBody,
                    'log_id' => $peppolLog?->id,
                    'warning' => $warning,
                ], fn ($value, $key) => $key !== 'warning' || $value !== null, ARRAY_FILTER_USE_BOTH);
            }

            // Update log with error
            $peppolLog?->update([
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
                'log_id' => $peppolLog?->id,
            ];

        } catch (\Exception $e) {
            Log::error('Peppol: Error sending', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            // Update log with error
            $peppolLog?->update([
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
                'log_id' => $peppolLog?->id,
            ];
        }
    }

    /**
     * Set peppol_sent_at on the host model after a successful send, when the model can be updated.
     *
     * @return string|null A warning when the model could not be marked, null otherwise
     */
    private function markSent(object $invoice): ?string
    {
        if (! method_exists($invoice, 'update')) {
            return null;
        }

        try {
            $invoice->update(['peppol_sent_at' => now()]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Peppol: Invoice sent, but peppol_sent_at could not be set', [
                'invoice_id' => $invoice->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return 'The invoice was sent, but peppol_sent_at could not be set on the model: '.$e->getMessage();
        }
    }

    /**
     * Open a log row for a send, when there is a table to write it to.
     *
     * The peppol_logs table is opt-in: it only exists after the host app published and ran the
     * migration. Without it a document is sent just the same, and the result carries a null log_id.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function startLog(array $attributes): ?PeppolLog
    {
        if (! PeppolLog::tableExists()) {
            return null;
        }

        return PeppolLog::create($attributes + [
            'status' => 'pending',
            'sent_at' => now(),
        ]);
    }

    /**
     * Send a UBL invoice directly (without Invoice model)
     */
    public function sendUblXml(string $ublXml, ?string $invoiceNumber = null): array
    {
        $this->validateCredentials();

        // Log entry with status pending, or null when the host app has no log table
        $peppolLog = $this->startLog([
            'invoice_nr' => $invoiceNumber,
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

            // The body can hold invoice data; it is kept on the log row, not in the application log
            Log::info('Peppol: Response received', [
                'invoice_nr' => $invoiceNumber,
                'status_code' => $statusCode,
            ]);

            if ($response->successful()) {
                $peppolLog?->update([
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
                    'log_id' => $peppolLog?->id,
                ];
            }

            $peppolLog?->update([
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
                'log_id' => $peppolLog?->id,
            ];

        } catch (\Exception $e) {
            Log::error('Peppol: Error sending', [
                'invoice_nr' => $invoiceNumber,
                'error' => $e->getMessage(),
            ]);

            $peppolLog?->update([
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
                'log_id' => $peppolLog?->id,
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

            $status = $response->status();

            // The send URL takes a POST at most providers, so a GET that comes back as 405 or 400
            // still shows the provider was reached and did not refuse the credentials. A refusal,
            // an unknown URL and a server error are failures, not "Connection successful".
            $failure = match (true) {
                $status === 401 => 'Authentication failed - check credentials',
                $status === 403 => 'Access denied - the credentials are not allowed to use this URL',
                $status === 404 => 'Peppol URL not found - check PEPPOL_URL',
                $status >= 500 => "The Peppol provider answered with a server error (HTTP {$status})",
                default => null,
            };

            return [
                'success' => $failure === null,
                'status_code' => $status,
                'message' => $failure ?? ($response->successful()
                    ? 'Connection successful'
                    : "Peppol provider reached (HTTP {$status}); the credentials were not refused"),
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
