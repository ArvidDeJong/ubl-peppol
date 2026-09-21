---
title: "Laravel integration"
nav_order: 10
description: "What darvis/ubl-peppol adds in Laravel: the container bindings, the ubl-peppol config file, the optional peppol_logs table and the peppol:cleanup command."
---

# Laravel integration

Laravel is optional. The builders, `UblValidator`, `ViesService` and `CompanyRegistrationService` are plain PHP. The Laravel layer is four classes:

| Class | What it does |
| --- | --- |
| `Darvis\UblPeppol\UblPeppolServiceProvider` | Registers the config, the bindings, the publish tags and the command. Laravel discovers it by itself |
| `Darvis\UblPeppol\PeppolService` | Posts the XML to your access point provider. See [Sending invoices](peppol-service.md) |
| `Darvis\UblPeppol\Models\PeppolLog` | The Eloquent model for the optional `peppol_logs` table |
| `Darvis\UblPeppol\Console\CleanupPeppolLogsCommand` | The `peppol:cleanup` command |

The package has no routes, views, middleware, events or translations.

[Installation](installation.md#install-in-a-laravel-application) has the setup steps. This page explains what you get.

## Build an invoice inside Laravel

Create a new builder for every document:

```php
// app/Actions/BuildInvoiceXml.php
namespace App\Actions;

use App\Models\Invoice;
use Darvis\UblPeppol\UblNlBis3Service;

class BuildInvoiceXml
{
    public function handle(Invoice $invoice): string
    {
        $ubl = new UblNlBis3Service();

        $ubl->createDocument();
        $ubl->addInvoiceHeader(
            $invoice->number,
            $invoice->issued_at->format('Y-m-d'),
            $invoice->due_at->format('Y-m-d'),
        );
        // ... parties, payment, tax total, monetary total and lines,
        // as in "Your first invoice"

        return $ubl->generateXml(validateFirst: true);
    }
}
```

`App\Models\Invoice` and its columns are your own; the package has no invoice model. The dates are formatted because the builder accepts a `YYYY-MM-DD` string or a `\DateTime`, and refuses the `CarbonImmutable` that a model returns when you use immutable dates. [Your first invoice](getting-started.md) has the complete list of calls.

### Do not take the Dutch builder from the container twice

The service provider binds `UblNlBis3Service` as a **singleton** (one shared instance per application), with the alias `ubl-peppol`. `app(UblNlBis3Service::class)` and `app('ubl-peppol')` return that same instance every time, and a builder holds one document. The second invoice in the same request, queue worker or test therefore throws:

```text
Document is already initialized. Avoid initializing the document multiple times.
```

Use `new UblNlBis3Service()` instead, as above. `UblBeBis3Service` has no binding, so `app(UblBeBis3Service::class)` does give a new instance each time.

## The config file

The config key is `ubl-peppol`. Publishing the file is optional; without it the defaults and your `.env` values apply.

```bash
php artisan vendor:publish --tag=ubl-peppol-config
```

| Key | Env variable | Default | What it does |
| --- | --- | --- | --- |
| `log_retention_days` | `PEPPOL_LOG_RETENTION_DAYS` | `60` | How many days `peppol:cleanup` keeps a log row |
| `password` | `PEPPOL_PASSWORD` | none | Password for your access point provider |
| `url` | `PEPPOL_URL` | none | The address `PeppolService` posts the XML to |
| `username` | `PEPPOL_USERNAME` | none | Username for your access point provider |

Only `PeppolService` and `peppol:cleanup` read these values. The builders read no configuration.

`PeppolService` is a singleton as well, and it reads the three credentials when it is created. When you change the config at runtime, for example per tenant, create the service with `new PeppolService()` after the change.

After you change `.env` on a server that caches its config, run `php artisan config:clear`.

## The log table is optional

`peppol_logs` keeps one row per attempt to send. It is not created by itself:

```bash
php artisan vendor:publish --tag=ubl-peppol-migrations
php artisan migrate
```

The first command copies a migration named `..._create_peppol_logs_table.php` to `database/migrations`.

Without the table, sending works and the result has `'log_id' => null`. With the table, `PeppolService` writes a row with status `pending` before the request and updates it to `success` or `error` afterwards.

| Column | Type | Holds |
| --- | --- | --- |
| `id` | big integer | |
| `invoice_id` | unsigned big integer, nullable | The `id` of the object you passed to `sendInvoice()` |
| `invoice_nr` | string, nullable | The invoice number |
| `status` | `pending`, `success` or `error` | |
| `http_status_code` | integer, nullable | The HTTP status of the provider, `0` when the request itself failed |
| `message` | text, nullable | `Invoice successfully sent to Peppol network` or `Error sending to Peppol network` |
| `error` | text, nullable | The response body of a refusal, or the exception message |
| `response` | JSON, nullable | The JSON answer of the provider on success |
| `sent_at` | timestamp, nullable | When the attempt started |
| `created_at`, `updated_at` | timestamps | |

### Read the log

```php
use Darvis\UblPeppol\Models\PeppolLog;

PeppolLog::success()->get();          // status success
PeppolLog::error()->get();            // status error
PeppolLog::pending()->get();          // started and never finished
PeppolLog::recent(7)->get();          // created in the last 7 days (default 60)
PeppolLog::olderThan(90)->get();      // created more than 90 days ago

PeppolLog::where('invoice_id', $invoice->id)->latest()->first();

PeppolLog::tableExists();             // false until you ran the migration
```

A scope is a named query filter on an Eloquent model ([Laravel docs](https://laravel.com/docs/eloquent#local-scopes)). Querying the model without the table throws a database error, so ask `PeppolLog::tableExists()` first in code that must work in both situations.

## Clean up old log rows

```bash
php artisan peppol:cleanup             # deletes rows older than log_retention_days (60)
php artisan peppol:cleanup --days=30   # deletes rows older than 30 days, for this run
```

The output is `Deleting Peppol logs older than 60 days...` and then `✓ 3 log(s) deleted.` Without the table the command says there is nothing to clean up and ends successfully.

Schedule it when you send often:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('peppol:cleanup')->weekly();
```

In your own code, `PeppolLog::cleanupOldLogs(int $days = 60)` deletes the same rows and returns how many.

## Next steps

- [Sending invoices](peppol-service.md)
- [Testing](testing.md) your own code with `Http::fake()`
