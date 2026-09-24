---
title: "VAT numbers"
nav_order: 9
description: "Check a European VAT number with ViesService: checkVat(), checkFullVatNumber(), the result array, and how to tell an invalid number from a VIES outage."
---

# VAT numbers

`Darvis\UblPeppol\ViesService` asks VIES whether a VAT number exists. VIES is the European Commission's service that forwards the question to the tax office of the member state.

## Requirements

`ViesService` uses PHP's `SoapClient`, so it needs the `soap` extension. Composer does not check this for you. Without the extension the call fails with the PHP error `Class "SoapClient" not found`, which the service does not catch.

```bash
php -m | grep soap
```

Every call is a live request to `ec.europa.eu`. The connection timeout is 10 seconds.

## Check a number

```php
use Darvis\UblPeppol\ViesService;

$vies = new ViesService();

// Country code and number apart
$result = $vies->checkVat('BE', '0999000228');

// Or the full number; the first two characters are the country code
$result = $vies->checkFullVatNumber('BE0999000228');
```

Spaces are removed, the country code is made upper case, and a country prefix inside the number (`checkVat('BE', 'BE0999000228')`) is removed. Greece uses `EL` in VAT numbers, not `GR`.

In Laravel you can also resolve it from the container with `app(ViesService::class)`. The package registers no binding for it, so you get a new instance.

## The result

Both methods return an array and never throw for an answer from VIES.

| Key | When VIES answered | When the call failed |
| --- | --- | --- |
| `valid` | `true` or `false` | `false` |
| `name` | The registered name, or an empty string | `null` |
| `address` | The registered address, or an empty string | `null` |
| `countryCode` | The cleaned country code | The country code as you passed it |
| `vatNumber` | The cleaned number without prefix | The number as you passed it |
| `fullVatNumber` | Country code plus number | `null` |
| `checked_at` | `Y-m-d H:i:s` | `Y-m-d H:i:s` |
| `error` | `null` | A message, see below |

`name` and `address` hold what VIES returned. That can be an empty string while `valid` is `true`, so do not depend on them.

`checkFullVatNumber()` with fewer than three characters returns only `valid`, `error` (`VAT number too short`) and `checked_at`.

## Tell "invalid" from "VIES is down"

`valid` is `false` in both cases. The difference is `error`:

```php
$result = $vies->checkFullVatNumber($vatNumber);

if ($result['valid']) {
    // The number exists.
} elseif ($result['error'] === null) {
    // VIES answered: this number does not exist.
} else {
    // The question was never answered. Treat the number as unknown, and try again later.
    logger()->warning('VIES check failed', $result);
}
```

Never block an invoice or a customer on the third case: the question was not answered, so you know nothing about the number.

| `error` | Meaning |
| --- | --- |
| `Invalid VAT number format` | VIES refused the input (`INVALID_INPUT`) |
| `VIES service temporarily unavailable` | `SERVICE_UNAVAILABLE` |
| `Member state service unavailable` | `MS_UNAVAILABLE`: the tax office of that country does not answer |
| `Connection timeout` | `TIMEOUT` |
| `Server is busy, please try again later` | `SERVER_BUSY` |
| `Too many concurrent requests` | `MS_MAX_CONCURRENT_REQ` or `GLOBAL_MAX_CONCURRENT_REQ` |
| `VIES check failed: ...` | Any other SOAP fault, with the original text |

## What a valid answer proves

It proves the number is registered for trade within the EU at the moment you ask. It does not prove the number belongs to the company you are invoicing; compare `name` and `address` yourself when they are filled.

## Use it in a Laravel form

A closure rule is a validation rule written as a function ([Laravel docs](https://laravel.com/docs/validation#using-closures)). This one refuses a number VIES says does not exist, and lets the form through when VIES gave no answer:

```php
// app/Http/Controllers/CustomerController.php
use Darvis\UblPeppol\ViesService;

$request->validate([
    'vat_number' => [
        'required',
        function (string $attribute, mixed $value, \Closure $fail) {
            $result = app(ViesService::class)->checkFullVatNumber((string) $value);

            if (! $result['valid'] && $result['error'] === null) {
                $fail('This VAT number is not known in VIES.');
            }
        },
    ],
]);
```

A request waits for VIES here. Cache the result when you check the same number often:

```php
use Illuminate\Support\Facades\Cache;

$result = Cache::remember('vies:'.$vatNumber, now()->addDay(), function () use ($vatNumber) {
    return app(ViesService::class)->checkFullVatNumber($vatNumber);
});
```

Do not cache a result that has an `error`; that would keep an outage for a day. Check `$result['error']` and call `Cache::forget()` when it is set.

## Check only the format, without VIES

`UblValidator::validateVatNumber()` checks the prefix and the characters without a network call. See [Validation](validation.md#check-single-values-with-ublvalidator).

## Test your code without calling VIES

See [Testing](testing.md#replace-viesservice).
