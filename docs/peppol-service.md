---
title: "Sending invoices"
nav_order: 13
description: "Send UBL XML to your PEPPOL access point provider from Laravel with PeppolService: sendUblXml(), sendInvoice(), testConnection() and the result array."
---

# Sending invoices

`Darvis\UblPeppol\PeppolService` posts a finished XML document to your access point provider. It is part of the Laravel layer: it uses Laravel's HTTP client, log and config.

## What it sends

One HTTP `POST` to `PEPPOL_URL`, with:

- HTTP Basic authentication from `PEPPOL_USERNAME` and `PEPPOL_PASSWORD`
- the headers `Content-Type: application/xml` and `Accept: application/json`
- the XML as the request body

Ask your provider whether its API accepts exactly that. `PeppolService` has no other mode: no API key header, no JSON envelope, no OAuth. With such a provider, build the XML with this package and send it with your own HTTP code.

Set the three values first; see [Installation](installation.md#install-in-a-laravel-application).

## Test the connection

```php
// php artisan tinker
app(\Darvis\UblPeppol\PeppolService::class)->testConnection();
```

This sends one `GET` to `PEPPOL_URL` with your credentials. It sends no document.

| Answer of the provider | `success` | `message` |
| --- | --- | --- |
| 2xx | `true` | `Connection successful` |
| 401 | `false` | `Authentication failed - check credentials` |
| 403 | `false` | `Access denied - the credentials are not allowed to use this URL` |
| 404 | `false` | `Peppol URL not found - check PEPPOL_URL` |
| 500 and higher | `false` | `The Peppol provider answered with a server error (HTTP 503)` |
| Anything else, such as 405 or 400 | `true` | `Peppol provider reached (HTTP 405); the credentials were not refused` |
| No answer at all | `false` | `Cannot connect to Peppol provider`, with the reason in `error` |

A send address often accepts only a `POST`, so a 405 on this `GET` is normal and counts as success. The result also has `status_code`; it is `0` when there was no answer.

## Send a document

```php
// app/Http/Controllers/InvoiceSendController.php
namespace App\Http\Controllers;

use App\Actions\BuildInvoiceXml;
use App\Models\Invoice;
use Darvis\UblPeppol\PeppolService;
use Illuminate\Http\RedirectResponse;

class InvoiceSendController extends Controller
{
    public function __invoke(Invoice $invoice, BuildInvoiceXml $build, PeppolService $peppol): RedirectResponse
    {
        $xml = $build->handle($invoice);

        $result = $peppol->sendUblXml($xml, $invoice->number);

        if (! $result['success']) {
            return back()->withErrors(['peppol' => $result['message'].': '.$result['error']]);
        }

        $invoice->update(['sent_to_peppol_at' => now()]);

        return back()->with('status', 'Invoice sent.');
    }
}
```

Laravel injects `PeppolService` because it is type-hinted in the method. `BuildInvoiceXml` is the class from [Laravel integration](laravel.md#build-an-invoice-inside-laravel); `Invoice`, its `number` and its `sent_to_peppol_at` column are your own.

`sendUblXml(string $ublXml, ?string $invoiceNumber = null)` posts the XML. The invoice number is only used for the log.

## The result

`sendUblXml()` and `sendInvoice()` return an array and do not throw for an HTTP problem.

| Key | On success | On failure |
| --- | --- | --- |
| `success` | `true`: the provider answered with a 2xx status | `false` |
| `status_code` | The HTTP status | The HTTP status, or `0` when the request itself failed |
| `message` | `Invoice successfully sent to Peppol network` | `Error sending to Peppol network` |
| `response` | The JSON answer as an array, or the raw body when it is not JSON | Not present |
| `error` | Not present | The response body, or the exception message |
| `log_id` | The `id` of the `peppol_logs` row, or `null` without the table | The same |

`success` means your **provider accepted the request**. Delivery to the receiver happens later, inside the PEPPOL network. Whether it arrived is something you read from your provider, not from this package.

They do throw a `RuntimeException` before any request when a credential is missing:

```text
Peppol URL is not configured (PEPPOL_URL)
Peppol username is not configured (PEPPOL_USERNAME)
Peppol password is not configured (PEPPOL_PASSWORD)
```

## `sendInvoice()` updates your model

`sendInvoice(object $invoice, string $ublXml)` takes your own invoice object. It reads `$invoice->id` and, when present, `$invoice->invoice_nr`, and stores both on the log row.

After a successful send it also does this, when the object has an `update` method, which every Eloquent model has:

```php
$invoice->update(['peppol_sent_at' => now()]);
```

So an Eloquent model needs:

1. a nullable `peppol_sent_at` timestamp column, and
2. `peppol_sent_at` in `$fillable` (or an unguarded model).

```php
// database/migrations/2026_01_15_000000_add_peppol_sent_at_to_invoices_table.php
Schema::table('invoices', function (Blueprint $table) {
    $table->timestamp('peppol_sent_at')->nullable();
});
```

**Without the column, a send that worked is reported as a failure.** The document is already with your provider when the update throws. The exception is caught, the result becomes `'success' => false` with `status_code` `0` and the database error in `error`, and the log row is changed from `success` to `error`. Code that retries on failure then sends the invoice twice.

If you do not want that column, use `sendUblXml()` as in the example above and record the send yourself. `sendUblXml()` never touches your model.

## What is written to your application log

Every send writes to Laravel's default log channel:

| Level | Message | Context |
| --- | --- | --- |
| info | `Peppol: Sending invoice` or `Peppol: Sending UBL XML` | `invoice_id`, `invoice_nr` |
| info | `Peppol: Response received` | `status_code` and the full response body |
| error | `Peppol: Error sending` | The exception message |

The response body of a provider can contain invoice data. Keep that in mind when your logs go to an external service. The password is never logged.

## Read the configuration back

```php
app(\Darvis\UblPeppol\PeppolService::class)->getConfig();
// ['url' => 'https://...', 'username' => '...', 'password_configured' => true]
```

The service is a singleton and reads the config when it is created. See [The config file](laravel.md#the-config-file).

## Send from a queue

Sending waits for your provider, so a queued job (a task Laravel runs in the background, see the [Laravel docs](https://laravel.com/docs/queues)) keeps the request fast:

```php
// app/Jobs/SendInvoiceToPeppol.php
namespace App\Jobs;

use App\Actions\BuildInvoiceXml;
use App\Models\Invoice;
use Darvis\UblPeppol\PeppolService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendInvoiceToPeppol implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Invoice $invoice) {}

    public function handle(BuildInvoiceXml $build, PeppolService $peppol): void
    {
        $result = $peppol->sendUblXml($build->handle($this->invoice), $this->invoice->number);

        // Retry only when the provider was not reached or had a server error.
        if (! $result['success'] && ($result['status_code'] === 0 || $result['status_code'] >= 500)) {
            throw new RuntimeException($result['error']);
        }

        if (! $result['success']) {
            $this->fail($result['error']);

            return;
        }

        $this->invoice->update(['sent_to_peppol_at' => now()]);
    }
}
```

A 4xx answer means the provider refused the document. Sending the same XML again gives the same answer, so the job fails at once instead of retrying.

Build the XML inside the job with `new UblNlBis3Service()`. A queue worker is one long-running application, and the container's Dutch builder can hold only one document; see [Laravel integration](laravel.md#do-not-take-the-dutch-builder-from-the-container-twice).

## Next steps

- [Testing](testing.md) shows how to test this without calling your provider
- [Troubleshooting](troubleshooting.md#sending) lists the errors
