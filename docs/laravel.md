---
title: Laravel integration
nav_order: 9
description: "Using the package inside a Laravel application: the service provider, the config file, the log table and the cleanup command."
---

# Laravel Integration

This package provides seamless Laravel integration for sending UBL invoices via the Peppol network.

## Requirements

- Laravel 11, 12 or 13
- PHP 8.2+ with the DOM extension

Laravel is optional: the invoice builders and the validator work without it. This page is about what the package adds when there *is* an application around it.

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
    'log_retention_days' => env('PEPPOL_LOG_RETENTION_DAYS', 60),
    'password' => env('PEPPOL_PASSWORD'),
    'url' => env('PEPPOL_URL'),
    'username' => env('PEPPOL_USERNAME'),
];
```

Publishing is optional. Without it the defaults apply and the `.env` values below are still picked up.

Add the following to your `.env` file:

```env
PEPPOL_URL=https://your-peppol-provider.com/api
PEPPOL_USERNAME=your-username
PEPPOL_PASSWORD=your-password
```

## The log table

`peppol_logs` records what was sent and what came back. It is **opt-in**: an application that only generates XML never needs it. To use it, publish the migration and run it:

```bash
php artisan vendor:publish --tag=ubl-peppol-migrations
php artisan migrate
```

Rows are cleaned up by `php artisan peppol:cleanup`, which keeps `log_retention_days` days (60 by default). Pass `--days=30` to override it for one run. Schedule it if you send a lot:

```php
// routes/console.php
Schedule::command('peppol:cleanup')->weekly();
```

## Usage

### Generating UBL XML

Resolve a builder from the container, or new one up; both work. Pick it by the receiver's country.

```php
use Darvis\UblPeppol\UblNlBis3Service;

$ubl = app(UblNlBis3Service::class);

$ubl->createDocument();
$ubl->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14');
// supplier, customer, lines, tax total, monetary total

$xml = $ubl->generateXml(validateFirst: true);
```

A document is built element by element, in the order the UBL schema fixes. [Dutch invoices](netherlands.md) and [Belgian invoices](belgium.md) each walk through a complete one.

### Sending via Peppol

```php
use Darvis\UblPeppol\PeppolService;

$peppolService = app(PeppolService::class);

// Send with Invoice model
$result = $peppolService->sendInvoice($invoice, $xml);

// Send XML directly
$result = $peppolService->sendUblXml($xml, 'INV-2026-001');

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
