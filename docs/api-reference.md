---
title: "API reference"
nav_order: 14
description: "Every public method of both invoice builders, InvoiceValidationResult, UblValidator, ViesService, CompanyRegistrationService, PeppolService and PeppolLog."
---

# API reference

All classes live in the namespace `Darvis\UblPeppol`. Every `add...()` method returns the builder, so calls can be chained. Arguments are positional; PHP named arguments work as well.

## The two builders side by side

`UblNlBis3Service` builds Dutch invoices and credit notes, `UblBeBis3Service` builds Belgian ones. The method names are the same; the arguments are not always.

### Document

| Method | `UblNlBis3Service` | `UblBeBis3Service` |
| --- | --- | --- |
| `createDocument(): self` | Starts an `<Invoice>`. Throws a `RuntimeException` on a second call | The same |
| `generateXml()` without `createDocument()` | Throws a `RuntimeException` | Returns an empty XML declaration |
| `generateXml(bool $validateFirst = false): string` | Puts the elements in schema order and returns the XML. With `true` it calls `validate()` first and throws an `InvalidArgumentException` on errors | Returns the XML. With `true` the same, plus suggested corrections in the message. On a credit note it always checks the [credit note rules](credit-notes.md#the-rules-generatexml-enforces) |
| `validate(): InvoiceValidationResult` | Code formats and five Dutch rules | Totals and code formats. See [Validation](validation.md#the-two-builders-check-different-things) |
| `enableStrictCodelistValidation(?string $jsonPath = null, ?CodelistRegistry $registry = null): self` | See [Strict code lists](validation.md#strict-code-lists) | The same |

### Header and references

```php
addInvoiceHeader(string $invoiceNumber, $issueDate, $dueDate): self
addAccountingCost(string $value): self                 // BT-19, optional
addBuyerReference(?string $buyerRef = 'BUYER_REF'): self
addOrderReference(string $orderNumber = 'PO-001'): self
addAdditionalDocumentReference(string $id, ?string $documentType = null): self
```

The same in both builders, with two differences: the Dutch builder ignores `$documentType`, and the Belgian `addBuyerReference()` throws on a credit note. The date arguments have no type declaration in the code; a `YYYY-MM-DD` string or a `\DateTime` is accepted.

### Parties

`UblNlBis3Service`:

```php
addAccountingSupplierParty(
    string $endpointId,
    string $endpointSchemeID,
    string $partyId,
    string $partyName,
    string $street,
    string $postalCode,
    string $city,
    string $countryCode,
    string $companyId,                  // the supplier's VAT number
    ?string $additionalStreet = null
): self

addAccountingCustomerParty(
    string $endpointId,
    string $endpointSchemeID,
    string $partyId,
    string $partyName,
    string $street,
    string $postalCode,
    string $city,
    string $countryCode,
    ?string $additionalStreet = null,
    ?string $companyId = null,          // the customer's registration number (KvK)
    ?string $contactName = null,
    ?string $contactPhone = null,
    ?string $contactEmail = null,
    ?string $vatNumber = null,          // with the country prefix
    string $taxSchemeId = 'VAT'
): self
```

Only in `UblNlBis3Service`, for the legal registration identifier (BT-30, BT-47):

```php
addSupplierLegalRegistration(string $identifier, string $schemeId = '0106'): self
addCustomerLegalRegistration(string $identifier, string $schemeId = '0106'): self
```

See [Dutch invoices](netherlands.md#the-legal-registration-of-the-supplier-and-the-customer).

The customer's `$partyId` (BT-46) is whatever you call this customer, for example your own customer number. The Dutch builder writes the endpoint's scheme on it only when it is the endpoint identifier itself, 8 digits under scheme `0106` (a KvK number) or 20 digits under `0190` (an OIN); otherwise the optional `schemeID` is left out. Until 1.9 every value got the scheme, so `CUST-710` went out as a KvK number and the receiver's validation answered with `PEPPOL-COMMON-R054`.

`UblBeBis3Service` has the same positions with other names: `$name` for `$partyName`, `$country` for `$countryCode`, `$vatNumber` for the supplier's `$companyId`, and `$registrationNumber` for the customer's `$companyId`. It has no `$taxSchemeId` argument.

### Delivery, payment, allowances and charges

`UblNlBis3Service`:

```php
addDelivery(
    string $deliveryDate,
    ?string $locationId = null,
    string $locationSchemeId = '0088',
    ?string $street = null,
    ?string $additionalStreet = null,
    ?string $city = null,
    ?string $postalCode = null,
    ?string $countryCode = null,
    ?string $partyName = null
): self

addPaymentMeans(
    string $paymentMeansCode = '30',
    string $paymentMeansName = 'Credit transfer',
    ?string $paymentId = null,
    ?string $accountId = null,              // IBAN
    ?string $accountName = null,
    ?string $financialInstitutionId = null, // BIC
    ?string $paymentChannelCode = null,
    ?string $paymentDueDate = null
): self

addPaymentTerms(?string $note = null): self

addAllowanceCharge(
    bool $isCharge = true,
    float $amount = 0.0,
    string $reason = '',
    string $taxCategoryId = 'S',
    float $taxPercent = 0.0,
    string $currency = 'EUR'
): self
```

`UblBeBis3Service`:

```php
addDelivery(
    string $deliveryDate,
    string $locationId,
    string $locationSchemeId,
    string $street,
    ?string $additional_street,
    string $city,
    string $postal_code,
    string $country,
    ?string $party_name = null
): self

addPaymentMeans(
    string $paymentMeansCode,
    ?string $paymentMeansName,
    string $paymentId,
    string $account_iban,
    ?string $account_name,
    ?string $bic,
    ?string $channel_code = null,           // not written
    ?string $due_date = null                // not written
): self

addPaymentTerms(
    ?string $note = null,
    ?float $discount_percent = null,        // not written
    ?float $discount_amount = null,         // not written
    ?string $discount_date = null           // not written
): self

addAllowanceCharge(
    bool $isCharge,
    float $amount,
    string $reason,
    string $taxCategoryId,
    float $taxPercent,
    string $currency
): self
```

### Totals and lines

```php
addTaxTotal(array $taxes): self
addLegalMonetaryTotal(array $amounts, string $currency = 'EUR'): self   // Dutch builder
addLegalMonetaryTotal(array $totals, string $currency): self            // Belgian builder
addInvoiceLine(array $lineData): self
```

The array keys are listed under [Dutch invoices](netherlands.md#the-calls) and [Belgian invoices](belgium.md#the-calls). An entry of `addTaxTotal()` also takes `tax_exemption_reason_code` (BT-121) and `tax_exemption_reason` (BT-120); see [VAT categories](vat-categories.md#exemption-reasons).

### Credit notes, in both builders

| Method | What it does |
| --- | --- |
| `createCreditNoteDocument(): self` | Starts a `<CreditNote>` instead of an `<Invoice>` |
| `addCreditNoteHeader(string $creditNoteNumber, $issueDate): self` | The header with type code 381. No due date. `$issueDate` is a `YYYY-MM-DD` string or a `\DateTime`; the Dutch builder refuses a date in the future |
| `addBillingReference(string $originalInvoiceNumber, ?string $originalIssueDate = null): self` | The invoice the credit note corrects. Required on a credit note. The Dutch builder throws on an empty number or a date that is not `YYYY-MM-DD` |
| `addCreditNoteLine(array $lineData): self` | A line with `<cbc:CreditedQuantity>`. Makes quantity, price and line amount positive |
| `isCreditNote(): bool` | `true` after `createCreditNoteDocument()` |

In the Dutch builder since 1.10.0. There, the invoice methods throw a `RuntimeException` on a credit note and the credit note methods throw one on an invoice. See [Credit notes](credit-notes.md).

### Only in `UblBeBis3Service`

| Method | What it does |
| --- | --- |
| `calculateTotals(): array` | Adds up the lines added so far. Returns `totals`, `tax_totals` and `total_tax_amount` |
| `getInvoiceLines(): array`, `getTotals(): array`, `getTaxTotals(): array` | What you passed in so far |

See [Let the builder add up the lines](belgium.md#let-the-builder-add-up-the-lines).

## `Vat\VatCategory`

A string backed enum with the nine VAT categories PEPPOL allows outside Italy: `StandardRate` (`S`), `ZeroRated` (`Z`), `Exempt` (`E`), `ReverseCharge` (`AE`), `IntraCommunitySupply` (`K`), `ExportOutsideEu` (`G`), `NotSubjectToVat` (`O`), `CanaryIslands` (`L`), `CeutaMelilla` (`M`). See [VAT categories](vat-categories.md).

| Method | Returns |
| --- | --- |
| `VatCategory::fromCode(string $code): ?self` | The category, in any case; `null` for an unknown code |
| `label(): string` | The name in the UNCL5305 code list |
| `description(): string` | When to use it |
| `requiresExemptionReason(): bool`, `forbidsExemptionReason(): bool` | Whether the VAT breakdown needs or refuses a reason |
| `defaultExemptionReasonCode(): ?string` | `VATEX-EU-AE`, `VATEX-EU-IC`, `VATEX-EU-G` or `VATEX-EU-O`; `null` for the others |
| `exemptionReasonText(string $language = 'en'): ?string` | The standard text in `en`, `nl` or `fr` |
| `requiresZeroRate(): bool` | Whether the rate must be 0 |
| `rules(): array` | The EN 16931 rules of the category, rule code => text |
| `VatCategory::guide(string $language = 'en'): array` | All of the above for every category |

## `Vat\VatExemptionReason`

The VATEX code list (BT-121). Constants `REVERSE_CHARGE`, `INTRA_COMMUNITY_SUPPLY`, `EXPORT_OUTSIDE_EU`, `NOT_SUBJECT_TO_VAT`.

```php
VatExemptionReason::isKnown(string $code): bool
VatExemptionReason::name(string $code): ?string
VatExemptionReason::categoryOf(string $code): ?VatCategory
VatExemptionReason::codes(): array
```

## `Validation\InvoiceValidationResult`

Returned by `validate()`. Public read-only properties: `bool $isValid`, `array $errors`, `array $warnings`, `array $corrections`.

| Method | Returns |
| --- | --- |
| `isValid(): bool` | `true` without errors |
| `hasErrors(): bool`, `hasWarnings(): bool` | |
| `getErrorsAsString(string $separator = "\n"): string` | |
| `getWarningsAsString(string $separator = "\n"): string` | |
| `getCorrections(): array` | Suggested totals. Nothing is applied to the document |
| `getCorrection(string $key): mixed` | One suggested value, or `null` |
| `toArray(): array` | `is_valid`, `errors`, `warnings`, `corrections` |
| `InvoiceValidationResult::success(): self` | A valid result |
| `InvoiceValidationResult::fromException(\Throwable $e): self` | An invalid result with the exception message as its only error |

## `Validation\UblValidator`

Static helpers for single values. The full table is under [Validation](validation.md#check-single-values-with-ublvalidator).

```php
UblValidator::validateVatNumber(?string $vatNumber): ?string
UblValidator::isValidVatNumber(string $vatNumber): bool
UblValidator::validateIban(?string $iban): ?string
UblValidator::isValidUnitCode(string $unitCode): bool
UblValidator::isValidTaxCategory(string $categoryId): bool
UblValidator::isValidCurrencyCodeFormat(string $currencyCode): bool
UblValidator::isValidSchemeIdFormat(string $schemeId): bool
UblValidator::isValidPaymentMeansCodeFormat(string $paymentMeansCode): bool
UblValidator::isValidClassificationScheme(string $schemeId): bool
UblValidator::getClassificationSchemeDescription(string $schemeId): string
UblValidator::validateInvoiceData(array $data): array
UblValidator::validateBasicCodes(array $codes): InvoiceValidationResult
UblValidator::validateStrictCodelists(array $codes, CodelistRegistry $registry): InvoiceValidationResult
UblValidator::resolveTaxExemption(string $categoryId, ?string $code = null, ?string $text = null): array
UblValidator::validateVatBreakdown(array $breakdown, bool $hasDeliveryDate, ?string $deliveryCountry): InvoiceValidationResult
UblValidator::validateInvoiceTotals(array $invoiceLines, array $totals, array $taxTotals, float $allowanceTotalAmount = 0.0, float $chargeTotalAmount = 0.0, float $prepaidAmount = 0.0, array $documentAllowances = [], array $documentCharges = []): InvoiceValidationResult
```

The last five are what the builders call: `addTaxTotal()` settles the exemption reason with `resolveTaxExemption()`, `validate()` calls the others.

## `Validation\CodelistRegistry`

```php
new CodelistRegistry(array $lists)                      // ['iso4217' => ['EUR'], ...]
CodelistRegistry::fromJsonFile(string $path): self      // throws when the file is missing or not JSON
$registry->isLoaded(string $listName): bool             // false for a missing or empty list
$registry->has(string $listName, string $code): bool    // codes are compared in upper case
```

## `Constants\UnitCodes`

```php
UnitCodes::isValid(string $code): bool
UnitCodes::getDescription(string $code): ?string        // 'hour' for 'HUR'
UnitCodes::getAll(): array                              // every code the package knows
```

## `ViesService`

```php
checkVat(string $countryCode, string $vatNumber): array
checkFullVatNumber(string $fullVatNumber): array
```

Needs the `soap` extension. See [VAT numbers](vat-numbers.md).

## `CompanyRegistrationService`

```php
validate(string $number, string $countryCode): array
getSupportedCountries(): array
```

See [Company numbers](company-numbers.md).

## `PeppolService` (Laravel)

```php
sendUblXml(string $ublXml, ?string $invoiceNumber = null): array
sendInvoice(object $invoice, string $ublXml): array
testConnection(): array
getConfig(): array          // url, username, password_configured
```

The constructor takes no arguments; it reads the `ubl-peppol` config. See [Sending invoices](peppol-service.md).

## `Models\PeppolLog` (Laravel)

```php
PeppolLog::tableExists(): bool
PeppolLog::cleanupOldLogs(int $days = 60): int

// query scopes
PeppolLog::success();
PeppolLog::error();
PeppolLog::pending();
PeppolLog::recent(int $days = 60);
PeppolLog::olderThan(int $days);
```

See [The log table is optional](laravel.md#the-log-table-is-optional).

## `UblPeppolConfig` (Laravel)

The one class that reads the config: `UblPeppolConfig::url()`, `username()`, `password()` (each a string, empty when not set) and `logRetentionDays()` (an integer, default 60).

## Artisan

```bash
php artisan peppol:cleanup [--days=]
```

## Publish tags

| Tag | Publishes |
| --- | --- |
| `ubl-peppol-config` | `config/ubl-peppol.php` |
| `ubl-peppol-migrations` | The migration for the `peppol_logs` table |
