# VIES VAT Number Validation

The `ViesService` provides integration with the European Commission's VIES (VAT Information Exchange System) to validate EU VAT numbers in real-time.

## Overview

VIES is the official EU system for validating VAT numbers across member states. This service allows you to verify:
- VAT number validity
- Company name
- Company address
- Registration status

## Basic Usage

### Check VAT Number with Country Code

```php
use Darvis\UblPeppol\ViesService;

$vies = new ViesService();

$result = $vies->checkVat('BE', '0999000228');

if ($result['valid']) {
    echo "Valid VAT number!";
    echo "Company: " . $result['name'];
    echo "Address: " . $result['address'];
} else {
    echo "Invalid: " . $result['error'];
}
```

### Check Full VAT Number

```php
$result = $vies->checkFullVatNumber('BE0999000228');

// Same result structure as checkVat()
```

## Response Structure

Both methods return an array with the following structure:

```php
[
    'valid' => true,                    // Boolean: VAT number is valid
    'name' => 'Company Name BV',        // String: Registered company name
    'address' => 'Street 123, City',    // String: Registered address
    'countryCode' => 'BE',              // String: ISO country code
    'vatNumber' => '0999000228',        // String: VAT number without prefix
    'fullVatNumber' => 'BE0999000228',  // String: Complete VAT number
    'checked_at' => '2024-01-15 10:30:00', // String: Timestamp of check
    'error' => null                     // String|null: Error message if invalid
]
```

### Error Response

When validation fails:

```php
[
    'valid' => false,
    'name' => null,
    'address' => null,
    'countryCode' => 'BE',
    'vatNumber' => '0123456789',
    'fullVatNumber' => null,
    'checked_at' => '2024-01-15 10:30:00',
    'error' => 'Invalid VAT number format'
]
```

## Supported Countries

All EU member states are supported:
- Austria (AT)
- Belgium (BE)
- Bulgaria (BG)
- Croatia (HR)
- Cyprus (CY)
- Czech Republic (CZ)
- Denmark (DK)
- Estonia (EE)
- Finland (FI)
- France (FR)
- Germany (DE)
- Greece (EL)
- Hungary (HU)
- Ireland (IE)
- Italy (IT)
- Latvia (LV)
- Lithuania (LT)
- Luxembourg (LU)
- Malta (MT)
- Netherlands (NL)
- Poland (PL)
- Portugal (PT)
- Romania (RO)
- Slovakia (SK)
- Slovenia (SI)
- Spain (ES)
- Sweden (SE)

## Error Messages

The service translates VIES error codes to readable messages:

| VIES Code | Message |
|-----------|---------|
| INVALID_INPUT | Invalid VAT number format |
| SERVICE_UNAVAILABLE | VIES service temporarily unavailable |
| MS_UNAVAILABLE | Member state service unavailable |
| TIMEOUT | Connection timeout |
| SERVER_BUSY | Server is busy, please try again later |
| MS_MAX_CONCURRENT_REQ | Too many concurrent requests |
| GLOBAL_MAX_CONCURRENT_REQ | Too many concurrent requests |

## Best Practices

### 1. Handle Service Unavailability

The VIES service can be temporarily unavailable. Always handle errors gracefully:

```php
$result = $vies->checkVat('NL', '123456789B01');

if (!$result['valid']) {
    if (str_contains($result['error'], 'unavailable')) {
        // Service is down, skip validation or retry later
        logger()->warning('VIES service unavailable', $result);
    } else {
        // Invalid VAT number
        throw new ValidationException($result['error']);
    }
}
```

### 2. Cache Results

VIES has rate limits. Cache validation results to avoid repeated calls:

```php
$cacheKey = 'vies_' . $fullVatNumber;
$result = Cache::remember($cacheKey, now()->addDays(30), function() use ($vies, $fullVatNumber) {
    return $vies->checkFullVatNumber($fullVatNumber);
});
```

### 3. Timeout Handling

The service has a 10-second timeout. Consider running validation asynchronously for better UX:

```php
// In a queued job
dispatch(function() use ($vatNumber) {
    $vies = new ViesService();
    $result = $vies->checkFullVatNumber($vatNumber);
    
    // Store result in database
    VatValidation::create($result);
});
```

### 4. Input Cleaning

The service automatically cleans input:
- Removes spaces
- Removes country prefix if duplicated
- Converts to uppercase

```php
// All these work the same:
$vies->checkVat('BE', '0999000228');
$vies->checkVat('BE', 'BE0999000228');  // Prefix removed
$vies->checkVat('be', '0999 000 228'); // Cleaned and uppercased
```

## Laravel Integration

### Validation Rule

Use with Laravel validation:

```php
use Darvis\UblPeppol\ViesService;

$request->validate([
    'vat_number' => [
        'required',
        function ($attribute, $value, $fail) {
            $vies = app(ViesService::class);
            $result = $vies->checkFullVatNumber($value);
            
            if (!$result['valid']) {
                $fail($result['error'] ?? 'Invalid VAT number');
            }
        }
    ]
]);
```

### Service Container

Bind to Laravel's service container:

```php
// In AppServiceProvider
use Darvis\UblPeppol\ViesService;

$this->app->singleton(ViesService::class, function ($app) {
    return new ViesService();
});

// Use anywhere
$vies = app(ViesService::class);
```

## Testing

### Mock VIES Responses

For testing, mock the service:

```php
use Darvis\UblPeppol\ViesService;

// In your test
$mock = Mockery::mock(ViesService::class);
$mock->shouldReceive('checkVat')
    ->with('BE', '0999000228')
    ->andReturn([
        'valid' => true,
        'name' => 'Test Company BV',
        'address' => 'Test Street 1',
        'countryCode' => 'BE',
        'vatNumber' => '0999000228',
        'fullVatNumber' => 'BE0999000228',
        'checked_at' => now()->toDateTimeString(),
        'error' => null,
    ]);

$this->app->instance(ViesService::class, $mock);
```

## Limitations

1. **Rate Limits**: VIES has rate limits per IP address
2. **Availability**: Service may be unavailable during maintenance
3. **EU Only**: Only validates EU VAT numbers
4. **Real-time**: Each check makes a live API call (no offline validation)
5. **No Historical Data**: Only checks current registration status

## Alternative: Offline Validation

For basic format validation without VIES:

```php
// Belgian VAT format: BE0999000228 (BE + 10 digits)
if (!preg_match('/^BE[0-9]{10}$/', $vatNumber)) {
    throw new InvalidArgumentException('Invalid Belgian VAT format');
}

// Dutch VAT format: NL123456789B01 (NL + 9 digits + B + 2 digits)
if (!preg_match('/^NL[0-9]{9}B[0-9]{2}$/', $vatNumber)) {
    throw new InvalidArgumentException('Invalid Dutch VAT format');
}
```

## See Also

- [Belgium Implementation](belgium-implementation.md) - Belgian VAT number requirements
- [Netherlands Implementation](netherlands-implementation.md) - Dutch VAT number requirements
- [API Reference](api-reference.md) - Complete API documentation
