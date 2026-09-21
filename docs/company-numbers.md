---
title: "Company numbers"
nav_order: 9
description: "Check the format of a company registration number: Dutch KvK, Belgian KBO with its checksum, Luxembourg RCS, French SIREN and SIRET, German HRA and HRB."
---

# Company numbers

`Darvis\UblPeppol\CompanyRegistrationService` checks whether a company registration number has the right **format** for its country. It makes no network call and does not know whether the company exists.

## Check a number

```php
use Darvis\UblPeppol\CompanyRegistrationService;

$service = new CompanyRegistrationService();

$result = $service->validate('0681.845.662', 'BE');

if ($result['valid']) {
    $number = $result['formatted'];   // "0681845662"
} else {
    $message = $result['error'];      // for example "Invalid checksum. KBO number failed mod97 validation."
}
```

The arguments are the number first, then the two-letter country code. Spaces, dots and dashes are removed from the number before the check, and the country code is made upper case.

## What is checked per country

| Country | `type` | Format | Checksum | Example |
| --- | --- | --- | --- | --- |
| `NL` | `KVK` | 8 digits | No | `12345678` |
| `BE` | `KBO` | 10 digits | Yes: the last two digits are 97 minus (the first eight digits modulo 97) | `0681845662` |
| `LU` | `RCS` | 1 letter and 6 digits | No | `B123456` |
| `FR` | `SIREN` or `SIRET` | 9 digits (SIREN) or 14 digits (SIRET) | No | `732829320`, `73282932000074` |
| `DE` | `HRA` or `HRB` | `HRA` or `HRB` and 1 to 6 digits, space optional | No | `HRB 12345` |

`getSupportedCountries()` returns this list as an array with the keys `name`, `type`, `type_name`, `format` and `example` per country code.

## The result

`validate()` always returns an array and never throws.

| Key | Holds |
| --- | --- |
| `valid` | `true` or `false` |
| `country` | The country code, upper case |
| `country_name` | The English name of the country |
| `number` | The number after cleaning: `12 34 56 78` becomes `12345678`, `HRB 12345` becomes `HRB12345` |
| `formatted` | The number for display, or `null` when invalid. Germany gets a space (`HRB 12345`), Luxembourg is made upper case |
| `type` | `KVK`, `KBO`, `RCS`, `SIREN`, `SIRET`, `HRA` or `HRB`. `null` when a French or German number has the wrong shape |
| `type_name` | The full name of the register. Missing when `type` is `null` |
| `error` | `null` when valid, otherwise a sentence |

Extra keys: a valid SIRET adds `siren` (the first 9 digits) and `nic` (the last 5). A valid German number adds `registration_number` (the digits).

A country the service does not know returns `valid` false, `type` null and the error `Unsupported country code: US`. That result has no `country_name`, `formatted` or `type_name` key, and `number` is the input as you passed it.

## The error messages

| Country | `error` |
| --- | --- |
| `NL` | `Invalid format. Expected 8 digits.` |
| `BE` | `Invalid format. Expected 10 digits.` or `Invalid checksum. KBO number failed mod97 validation.` |
| `LU` | `Invalid format. Expected 1 letter + 6 digits (e.g., B123456).` |
| `FR` | `Invalid format. Expected 9 digits (SIREN) or 14 digits (SIRET).`, or `Invalid SIREN format. Expected 9 digits.` and `Invalid SIRET format. Expected 14 digits.` when the length is right but a character is not a digit |
| `DE` | `Invalid format. Expected HRA or HRB followed by 1-6 digits (e.g., HRB 12345).` |

## Use it in a Laravel form

```php
// app/Http/Controllers/CustomerController.php
use Darvis\UblPeppol\CompanyRegistrationService;

$country = (string) $request->input('country');

$request->validate([
    'registration_number' => [
        'required',
        function (string $attribute, mixed $value, \Closure $fail) use ($country) {
            $result = app(CompanyRegistrationService::class)->validate((string) $value, $country);

            if (! $result['valid']) {
                $fail($result['error']);
            }
        },
    ],
]);
```

Store `$result['number']`, not the raw input, so the value goes into an invoice without dots and spaces.

## Where the number goes in an invoice

- Netherlands: the KvK number is the `endpointId` with scheme `0106`, and the `companyId` of the customer. See [Dutch invoices](netherlands.md#scheme-ids-what-kind-of-number-is-this).
- Belgium: the enterprise number is the `endpointId` with scheme `0208`, and the `registrationNumber` of the customer. See [Belgian invoices](belgium.md#scheme-id-0208-the-enterprise-number).

To check whether a VAT number exists, use [VAT numbers](vat-numbers.md).
