# Laravel Integration

This package provides seamless Laravel integration for sending UBL invoices via the Peppol network.

## Requirements

- Laravel 11.x or 12.x
- PHP 8.2+

## Installation

```bash
composer require darvis/ubl-peppol
```

The service provider is automatically registered via Laravel's package discovery.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=ubl-peppol-config
```

This creates `config/ubl-peppol.php`:

```php
return [
    'url' => env('PEPPOL_URL'),
    'username' => env('PEPPOL_USERNAME'),
    'password' => env('PEPPOL_PASSWORD'),
];
```

Add the following to your `.env` file:

```env
PEPPOL_URL=https://your-peppol-provider.com/api
PEPPOL_USERNAME=your-username
PEPPOL_PASSWORD=your-password
```

## Database Migration

The package automatically loads its migrations. Run:

```bash
php artisan migrate
```

This creates the `peppol_logs` table for tracking sent invoices.

To publish the migration for customization:

```bash
php artisan vendor:publish --tag=ubl-peppol-migrations
```

## Usage

### Generating UBL XML

```php
use Darvis\UblPeppol\UblNlBis3Service;
use Darvis\UblPeppol\UblBeBis3Service;

// Dutch invoice
$service = new UblNlBis3Service();
$xml = $service->generateInvoice($invoiceData);

// Belgian invoice
$service = new UblBeBis3Service();
$xml = $service->generateInvoice($invoiceData);
```

### Sending via Peppol

```php
use Darvis\UblPeppol\PeppolService;

$peppolService = app(PeppolService::class);

// Send with Invoice model
$result = $peppolService->sendInvoice($invoice, $xml);

// Send XML directly
$result = $peppolService->sendUblXml($xml, 'INV-2024-001');

if ($result['success']) {
    // Invoice sent successfully
    $logId = $result['log_id'];
} else {
    // Handle error
    $error = $result['error'];
}
```

### Testing Connection

```php
$peppolService = app(PeppolService::class);
$result = $peppolService->testConnection();

if ($result['success']) {
    echo 'Connection successful';
} else {
    echo $result['message'];
}
```

### Viewing Logs

```php
use Darvis\UblPeppol\Models\PeppolLog;

// Get all logs
$logs = PeppolLog::all();

// Get logs for specific invoice
$logs = PeppolLog::where('invoice_id', $invoiceId)->get();

// Get failed logs
$logs = PeppolLog::where('status', 'error')->get();
```

## Artisan Commands

### Cleanup Old Logs

```bash
# Delete logs older than 90 days (default)
php artisan peppol:cleanup-logs

# Delete logs older than 30 days
php artisan peppol:cleanup-logs --days=30
```

## Validation

```php
use Darvis\UblPeppol\Validation\UblValidator;

$validator = new UblValidator();
$result = $validator->validate($xml);

if ($result->isValid()) {
    // XML is valid
} else {
    foreach ($result->getErrors() as $error) {
        echo $error;
    }
}
```

## Response Structure

All `PeppolService` methods return an array:

```php
[
    'success' => bool,
    'status_code' => int,
    'message' => string,
    'response' => array|null,  // On success
    'error' => string|null,    // On failure
    'log_id' => int,           // PeppolLog record ID
]
```
