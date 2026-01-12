# Peppol Service

This guide covers sending UBL invoices to the Peppol network via access point providers.

## Overview

The `PeppolService` enables you to send generated UBL invoices directly to the Peppol network through your access point provider (e.g., SupplyDrive, Storecove, etc.).

## Installation

### 1. Publish Configuration

```bash
php artisan vendor:publish --tag=ubl-peppol-config
```

This creates `config/ubl-peppol.php` with the following settings:

```php
return [
    'url' => env('PEPPOL_URL'),
    'username' => env('PEPPOL_USERNAME'),
    'password' => env('PEPPOL_PASSWORD'),
    'log_retention_days' => env('PEPPOL_LOG_RETENTION_DAYS', 60),
];
```

### 2. Publish Migrations

```bash
php artisan vendor:publish --tag=ubl-peppol-migrations
php artisan migrate
```

This creates the `peppol_logs` table for tracking sent invoices.

### 3. Configure Environment

Add your Peppol access point credentials to `.env`:

```env
PEPPOL_URL=https://your-provider.com/api/endpoint
PEPPOL_USERNAME=your_username
PEPPOL_PASSWORD=your_password
```

## Usage

### Basic Usage

```php
use Darvis\UblPeppol\PeppolService;
use Darvis\UblPeppol\UblBeBis3Service;

// 1. Generate UBL XML
$ublService = new UblBeBis3Service();
$ublService->createDocument();
$ublService->addInvoiceHeader('INV-2024-001', '2024-01-15', '2024-02-14');
// ... add more elements ...
$ublXml = $ublService->generateXml();

// 2. Send to Peppol network
$peppolService = new PeppolService();
$result = $peppolService->sendInvoice($invoice, $ublXml);

if ($result['success']) {
    echo "Invoice sent! Log ID: " . $result['log_id'];
} else {
    echo "Error: " . $result['error'];
}
```

### Send XML Without Invoice Model

If you don't have an Invoice model, you can send XML directly:

```php
$result = $peppolService->sendUblXml($ublXml, 'INV-2024-001');
```

### Test Connection

Verify your credentials before sending:

```php
$result = $peppolService->testConnection();

if ($result['success']) {
    echo "Connection OK!";
} else {
    echo "Connection failed: " . $result['message'];
}
```

### Get Configuration

Check current configuration (password is hidden):

```php
$config = $peppolService->getConfig();
// Returns: ['url' => '...', 'username' => '...', 'password_configured' => true/false]
```

## Response Format

All methods return an array with the following structure:

### Success Response

```php
[
    'success' => true,
    'status_code' => 200,
    'message' => 'Factuur succesvol verzonden naar Peppol netwerk',
    'response' => [...],  // Provider response data
    'log_id' => 123,      // PeppolLog record ID
]
```

### Error Response

```php
[
    'success' => false,
    'status_code' => 400,
    'message' => 'Fout bij verzenden naar Peppol netwerk',
    'error' => 'Error details...',
    'log_id' => 123,
]
```

## PeppolLog Model

All sent invoices are logged in the `peppol_logs` table.

### Table Structure

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| invoice_id | bigint | Optional reference to invoice |
| invoice_nr | string | Invoice number |
| status | enum | 'pending', 'success', 'error' |
| http_status_code | int | HTTP response code |
| message | text | Success/error message |
| error | text | Error details |
| response | json | Provider response |
| sent_at | timestamp | When sent |
| created_at | timestamp | Record created |
| updated_at | timestamp | Record updated |

### Query Scopes

```php
use Darvis\UblPeppol\Models\PeppolLog;

// Get successful sends
$successful = PeppolLog::success()->get();

// Get errors
$errors = PeppolLog::error()->get();

// Get pending
$pending = PeppolLog::pending()->get();

// Get recent logs (last N days)
$recent = PeppolLog::recent(7)->get();

// Get logs older than N days
$old = PeppolLog::olderThan(60)->get();
```

### Cleanup Old Logs

```php
// Delete logs older than 60 days (default)
$deleted = PeppolLog::cleanupOldLogs();

// Delete logs older than 30 days
$deleted = PeppolLog::cleanupOldLogs(30);
```

### Scheduled Cleanup

Add to your Laravel scheduler (`routes/console.php` or `app/Console/Kernel.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('peppol:cleanup')->daily();
```

Or create a custom command:

```php
// In a scheduled command
PeppolLog::cleanupOldLogs(config('ubl-peppol.log_retention_days', 60));
```

## Laravel Integration

### Dependency Injection

```php
use Darvis\UblPeppol\PeppolService;

class InvoiceController extends Controller
{
    public function send(Invoice $invoice, PeppolService $peppolService)
    {
        $ublXml = $this->generateUbl($invoice);
        return $peppolService->sendInvoice($invoice, $ublXml);
    }
}
```

### Queue Job Example

```php
use Darvis\UblPeppol\PeppolService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPeppolInvoice implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Invoice $invoice,
        public string $ublXml
    ) {}

    public function handle(PeppolService $peppolService): void
    {
        $result = $peppolService->sendInvoice($this->invoice, $this->ublXml);
        
        if (!$result['success']) {
            throw new \Exception($result['error']);
        }
    }
}
```

## Supported Providers

The `PeppolService` uses HTTP Basic Authentication and sends XML via POST. This is compatible with most Peppol access point providers:

- **SupplyDrive** - `https://rest.supplydrive.com/PEPPOL-SD-MESSAGES-HTTP/LIVE`
- **Storecove** - Check their API documentation
- **Basware** - Check their API documentation
- **Other providers** - Any provider supporting HTTP POST with Basic Auth

## Error Handling

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `PEPPOL_URL is niet geconfigureerd` | Missing URL | Add `PEPPOL_URL` to `.env` |
| `PEPPOL_USERNAME is niet geconfigureerd` | Missing username | Add `PEPPOL_USERNAME` to `.env` |
| `PEPPOL_PASSWORD is niet geconfigureerd` | Missing password | Add `PEPPOL_PASSWORD` to `.env` |
| HTTP 401 | Invalid credentials | Check username/password |
| HTTP 400 | Invalid XML | Validate UBL XML first |

### Validation Before Sending

Always validate your UBL XML before sending:

1. Use online validators (see [Validation](validation.md))
2. Check for required fields
3. Verify VAT numbers and formats
