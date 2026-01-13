# Company Registration Number Validation

The `CompanyRegistrationService` provides validation for company registration numbers across multiple European countries. This service validates format, checksums, and provides detailed information about each registration type.

## Overview

Different European countries use different systems for company registration:

- **Netherlands (NL)**: KVK (Kamer van Koophandel) - 8 digits
- **Belgium (BE)**: KBO (Kruispuntbank van Ondernemingen) - 10 digits with mod97 checksum
- **Luxembourg (LU)**: RCS (Registre de Commerce et des Sociétés) - 1 letter + 6 digits
- **France (FR)**: SIREN (9 digits) or SIRET (14 digits)
- **Germany (DE)**: Handelsregister - HRA/HRB + 1-6 digits

## Basic Usage

### Validate a Registration Number

```php
use Darvis\UblPeppol\CompanyRegistrationService;

$service = new CompanyRegistrationService();

// Validate Dutch KVK number
$result = $service->validate('12345678', 'NL');

if ($result['valid']) {
    echo "Valid KVK number: " . $result['formatted'];
    echo "Type: " . $result['type_name'];
} else {
    echo "Invalid: " . $result['error'];
}
```

## Response Structure

All validation methods return a consistent array structure:

```php
[
    'valid' => true,                    // Boolean: number is valid
    'country' => 'NL',                  // String: ISO country code
    'country_name' => 'Netherlands',    // String: Country name
    'number' => '12345678',             // String: Original input (cleaned)
    'formatted' => '12345678',          // String: Formatted number (null if invalid)
    'type' => 'KVK',                    // String: Registration type code
    'type_name' => 'Kamer van Koophandel', // String: Full type name
    'error' => null                     // String|null: Error message if invalid
]
```

## Country-Specific Validation

### Netherlands (NL) - KVK

**Format**: 8 digits

```php
$result = $service->validate('12345678', 'NL');

// Valid examples:
// 12345678
// 68184566

// Invalid examples:
// 1234567 (too short)
// 123456789 (too long)
// ABC12345 (contains letters)
```

**Response**:
```php
[
    'valid' => true,
    'country' => 'NL',
    'country_name' => 'Netherlands',
    'number' => '12345678',
    'formatted' => '12345678',
    'type' => 'KVK',
    'type_name' => 'Kamer van Koophandel',
    'error' => null,
]
```

### Belgium (BE) - KBO

**Format**: 10 digits with mod97 checksum validation

The last 2 digits are a checksum: `97 - (first 8 digits mod 97)`

```php
$result = $service->validate('0681845662', 'BE');

// Valid example: 0681845662
// Calculation: 97 - (06818456 % 97) = 97 - 35 = 62 ✓

// Invalid examples:
// 0681845663 (wrong checksum)
// 068184566 (too short)
```

**Response**:
```php
[
    'valid' => true,
    'country' => 'BE',
    'country_name' => 'Belgium',
    'number' => '0681845662',
    'formatted' => '0681845662',
    'type' => 'KBO',
    'type_name' => 'Kruispuntbank van Ondernemingen',
    'error' => null,
]
```

### Luxembourg (LU) - RCS

**Format**: 1 letter + 6 digits (usually starts with 'B')

```php
$result = $service->validate('B123456', 'LU');

// Valid examples:
// B123456
// A999999
// b123456 (automatically uppercased)

// Invalid examples:
// 123456 (missing letter)
// BB12345 (two letters)
// B12345 (too short)
```

**Response**:
```php
[
    'valid' => true,
    'country' => 'LU',
    'country_name' => 'Luxembourg',
    'number' => 'B123456',
    'formatted' => 'B123456',
    'type' => 'RCS',
    'type_name' => 'Registre de Commerce et des Sociétés',
    'error' => null,
]
```

### France (FR) - SIREN / SIRET

**SIREN Format**: 9 digits (company identifier)

```php
$result = $service->validate('732829320', 'FR');

// Response includes:
[
    'valid' => true,
    'country' => 'FR',
    'country_name' => 'France',
    'number' => '732829320',
    'formatted' => '732829320',
    'type' => 'SIREN',
    'type_name' => 'Système d\'Identification du Répertoire des Entreprises',
    'error' => null,
]
```

**SIRET Format**: 14 digits (establishment identifier = SIREN + NIC)

```php
$result = $service->validate('73282932000074', 'FR');

// Response includes:
[
    'valid' => true,
    'country' => 'FR',
    'country_name' => 'France',
    'number' => '73282932000074',
    'formatted' => '73282932000074',
    'type' => 'SIRET',
    'type_name' => 'Système d\'Identification du Répertoire des Établissements',
    'siren' => '732829320',      // First 9 digits
    'nic' => '00074',             // Last 5 digits
    'error' => null,
]
```

### Germany (DE) - Handelsregister

**Format**: HRA or HRB + 1-6 digits

- **HRA**: Handelsregister Abteilung A (Personengesellschaften - partnerships)
- **HRB**: Handelsregister Abteilung B (Kapitalgesellschaften - corporations/GmbH)

```php
$result = $service->validate('HRB 12345', 'DE');

// Valid examples:
// HRB 12345
// HRB12345 (space optional)
// HRA 1
// hrb 999999 (case insensitive)

// Invalid examples:
// HR 12345 (missing A or B)
// HRC 12345 (invalid letter)
// HRB 1234567 (too many digits)
```

**Response**:
```php
[
    'valid' => true,
    'country' => 'DE',
    'country_name' => 'Germany',
    'number' => 'HRB 12345',
    'formatted' => 'HRB 12345',
    'type' => 'HRB',
    'type_name' => 'Handelsregister Abteilung B (Kapitalgesellschaften)',
    'registration_number' => '12345',
    'error' => null,
]
```

## Helper Methods

### Get Supported Countries

```php
$countries = $service->getSupportedCountries();

// Returns:
[
    'NL' => [
        'name' => 'Netherlands',
        'type' => 'KVK',
        'type_name' => 'Kamer van Koophandel',
        'format' => '8 digits',
        'example' => '12345678',
    ],
    'BE' => [...],
    'LU' => [...],
    'FR' => [...],
    'DE' => [...],
]
```

## Input Cleaning

The service automatically cleans input by removing:
- Spaces
- Dots (.)
- Dashes (-)

Letters are preserved for Luxembourg (RCS) and Germany (Handelsregister).

```php
// All these are equivalent:
$service->validate('12345678', 'NL');
$service->validate('12 34 56 78', 'NL');
$service->validate('12.34.56.78', 'NL');
$service->validate('12-34-56-78', 'NL');
```

## Laravel Integration

### Validation Rule

Create a custom validation rule:

```php
use Darvis\UblPeppol\KvkService;

$request->validate([
    'kvk_number' => [
        'required',
        function ($attribute, $value, $fail) use ($country) {
            $service = app(KvkService::class);
            $result = $service->validate($value, $country);
            
            if (!$result['valid']) {
                $fail($result['error'] ?? 'Invalid company registration number');
            }
        }
    ]
]);
```

### Service Container

Bind to Laravel's service container:

```php
// In AppServiceProvider
use Darvis\UblPeppol\KvkService;

$this->app->singleton(KvkService::class, function ($app) {
    return new KvkService();
});

// Use anywhere
$service = app(KvkService::class);
```

## Error Handling

When validation fails, the response includes a descriptive error message:

```php
$result = $service->validate('123', 'NL');

// Returns:
[
    'valid' => false,
    'country' => 'NL',
    'country_name' => 'Netherlands',
    'number' => '123',
    'formatted' => null,
    'type' => 'KVK',
    'type_name' => 'Kamer van Koophandel',
    'error' => 'Invalid format. Expected 8 digits.',
]
```

### Unsupported Countries

```php
$result = $service->validate('12345', 'US');

// Returns:
[
    'valid' => false,
    'country' => 'US',
    'number' => '12345',
    'type' => null,
    'error' => 'Unsupported country code: US',
]
```

## Best Practices

### 1. Store Clean Numbers

Store registration numbers without formatting:

```php
$result = $service->validate($input, $country);

if ($result['valid']) {
    // Store the cleaned number
    $company->registration_number = $result['number'];
    $company->registration_type = $result['type'];
    $company->save();
}
```

### 2. Display Formatted Numbers

Use the formatted version for display:

```php
$result = $service->validate($company->registration_number, $company->country);

echo $result['formatted']; // HRB 12345 (with space for DE)
```

### 3. Validate Before Saving

Always validate before storing:

```php
public function store(Request $request)
{
    $service = app(KvkService::class);
    $result = $service->validate($request->kvk_number, $request->country);
    
    if (!$result['valid']) {
        return back()->withErrors(['kvk_number' => $result['error']]);
    }
    
    // Continue with saving...
}
```

### 4. Country-Specific Forms

Show appropriate format hints based on country:

```php
$countries = $service->getSupportedCountries();

foreach ($countries as $code => $info) {
    echo "{$info['name']}: {$info['format']} (e.g., {$info['example']})";
}
```

## Validation Summary Table

| Country | Type | Format | Example | Checksum |
|---------|------|--------|---------|----------|
| NL | KVK | 8 digits | 12345678 | No |
| BE | KBO | 10 digits | 0681845662 | Yes (mod97) |
| LU | RCS | 1 letter + 6 digits | B123456 | No |
| FR | SIREN | 9 digits | 732829320 | No |
| FR | SIRET | 14 digits | 73282932000074 | No |
| DE | HR | HRA/HRB + 1-6 digits | HRB 12345 | No |

## See Also

- [VIES VAT Validation](vies-validation.md) - Validate EU VAT numbers
- [Belgium Implementation](belgium-implementation.md) - Belgian UBL specifics
- [Netherlands Implementation](netherlands-implementation.md) - Dutch UBL specifics
- [API Reference](api-reference.md) - Complete API documentation
