---
title: "Installation"
nav_order: 2
description: "Install darvis/ubl-peppol with Composer, set the PEPPOL_URL, PEPPOL_USERNAME and PEPPOL_PASSWORD values, publish the config and check that it works."
---

# Installation

## Requirements

| What | Needed for |
| --- | --- |
| PHP 8.2 or newer with the `dom` and `libxml` extensions | Everything |
| The `bcmath` extension | An IBAN in `UblNlBis3Service::addPaymentMeans()`, and `UblValidator::validateIban()` |
| The `soap` extension | `ViesService` |
| Laravel 11, 12 or 13 | Only the Laravel layer: `PeppolService`, the `PeppolLog` model and `peppol:cleanup` |

Composer checks `dom` and `libxml` for you. It does not check `bcmath` and `soap`, so check those yourself:

```bash
php -m | grep -E "bcmath|soap"
```

## Install in any PHP project

1. Install the package:

   ```bash
   composer require darvis/ubl-peppol
   ```

2. There is no step 2. The builders take everything through their method arguments and read no configuration. Go to [Check that it works](#check-that-it-works).

## Install in a Laravel application

1. Install the package:

   ```bash
   composer require darvis/ubl-peppol
   ```

   Laravel discovers the service provider `Darvis\UblPeppol\UblPeppolServiceProvider` by itself. You do not add it to `bootstrap/providers.php`.

2. Only when you want to **send** invoices: add the address and the login of your access point provider to `.env`. An access point provider is the company that delivers your documents to the PEPPOL network. The three values come from that provider, usually from its customer portal or its onboarding mail.

   ```ini
   PEPPOL_URL=https://provider.example/api/send
   PEPPOL_USERNAME=your-username
   PEPPOL_PASSWORD=your-password
   ```

   `PeppolService` sends the XML as the body of an HTTP `POST` to `PEPPOL_URL`, with HTTP Basic authentication and the header `Content-Type: application/xml`. Ask your provider whether its API accepts that. A provider that expects JSON or an API key header does not work with `PeppolService`; you can still build the XML with this package and send it with your own code.

3. Optional: publish the config file when you want to change a value in PHP instead of in `.env`.

   ```bash
   php artisan vendor:publish --tag=ubl-peppol-config
   ```

   This writes `config/ubl-peppol.php`:

   | Key | Env variable | Default | What it does |
   | --- | --- | --- | --- |
   | `log_retention_days` | `PEPPOL_LOG_RETENTION_DAYS` | `60` | How many days `peppol:cleanup` keeps a log row |
   | `password` | `PEPPOL_PASSWORD` | none | Password for your access point provider |
   | `url` | `PEPPOL_URL` | none | The address `PeppolService` posts the XML to |
   | `username` | `PEPPOL_USERNAME` | none | Username for your access point provider |

4. Optional: create the `peppol_logs` table when you want a record of every attempt to send.

   ```bash
   php artisan vendor:publish --tag=ubl-peppol-migrations
   php artisan migrate
   ```

   Sending works without this table. The result of a send then has `'log_id' => null`.

5. When your application caches its config, clear the cache after you change `.env`:

   ```bash
   php artisan config:clear
   ```

## Check that it works

### The builder

Create `check.php` in the root of your project:

```php
<?php
// check.php

require __DIR__.'/vendor/autoload.php';

use Darvis\UblPeppol\UblNlBis3Service;

$ubl = new UblNlBis3Service();
$ubl->createDocument();
$ubl->addInvoiceHeader('TEST-001', '2026-01-15', '2026-02-14');

echo $ubl->generateXml();
```

Run it:

```bash
php check.php
```

You should see an XML document that starts like this:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" ...>
  <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
  <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
  <cbc:ID>TEST-001</cbc:ID>
```

That is only a header, not a valid invoice, but it proves the package is installed and the `dom` extension works. Delete `check.php` afterwards.

If you see `Class "Darvis\UblPeppol\UblNlBis3Service" not found` or `Class "DOMDocument" not found`, go to [Troubleshooting](troubleshooting.md#installation).

### The Laravel layer

Run the cleanup command. It needs no credentials and no table:

```bash
php artisan peppol:cleanup
```

Without the `peppol_logs` table you should see:

```text
There is no peppol_logs table, so there is nothing to clean up. Publish it with: php artisan vendor:publish --tag=ubl-peppol-migrations
```

With the table you should see `Deleting Peppol logs older than 60 days...` followed by `✓ 0 log(s) deleted.` Either answer proves the service provider is loaded. `Command "peppol:cleanup" is not defined.` means it is not; see [Troubleshooting](troubleshooting.md#command-peppolcleanup-is-not-defined).

### The connection to your provider

After step 2, open `php artisan tinker` and run:

```php
app(\Darvis\UblPeppol\PeppolService::class)->testConnection();
```

This sends one `GET` request to `PEPPOL_URL` and sends no invoice. A working setup returns `'success' => true`. The message is `Connection successful` for a 2xx answer, or `Peppol provider reached (HTTP 405); the credentials were not refused` when the address only accepts a `POST`, which is normal. Every other answer is explained in [Sending invoices](peppol-service.md#test-the-connection).

## Next step

[Your first invoice](getting-started.md) builds a complete Dutch invoice.
