# Changelog

All notable changes to **darvis/ubl-peppol** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.7.0] - 2026-09-18

Upgrading is `composer update darvis/ubl-peppol`. Two things change behaviour rather than just adding to it:

- If you set `log_retention_days`, `peppol:cleanup` now actually follows it. It used to delete anything older than 60 days no matter what you configured, so a higher setting starts keeping logs longer from this version on. Pass `--days` to override it per run.
- The generated XML is unchanged, and so are the config keys and the publish tags.

Also included are the changes from the 1.6.1 section below, which was never released on its own.

### Added
- `UblPeppolConfig` with named accessors is the one place that reads the package config, so a caller cannot quietly disagree with the config file about a default
- Documentation site at [arviddejong.github.io/ubl-peppol](https://arviddejong.github.io/ubl-peppol/), with `llms.txt`, a Laravel Boost guideline and skill in `resources/boost/`, `SECURITY.md`, `CONTRIBUTING.md`, issue forms and a pull request template
- Pest on Orchestra Testbench, Pint and Larastan level 8, with the `test`, `lint`, `format` and `analyse` composer scripts. CI runs PHP 8.2 to 8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies
- `tests/Unit/StandaloneCoreTest.php` guards the promise that the package works without Laravel: only the service provider, `PeppolService`, `PeppolLog` and the cleanup command may import `Illuminate\…`
- `tests/Laravel/ServiceProviderTest.php` covers the config defaults, the bindings, both publish tags, the command and the log table

### Changed
- `addBuyerReference()` on a credit note explains that this builder does not support it yet, instead of claiming the UBL schema forbids it. The schema does allow `cbc:BuyerReference` in a `CreditNote`; placing it correctly is still open
- `UblNlBis3Service` is bound by its class name and aliased as `ubl-peppol`, so `app(UblNlBis3Service::class)` and `app('ubl-peppol')` both return the same instance. `app('ubl-peppol')` keeps working
- The config keys are in alphabetical order and each has its own comment block. No key, default or behaviour changed
- Code comments and docblocks are in English throughout
- Documentation pages were renamed and merged to match the site navigation. `installation.md` and `quick-start.md` became `getting-started.md`, the country pages became `netherlands.md` and `belgium.md`, `vies-validation.md` became `vat-numbers.md`, `company-registration-validation.md` became `company-numbers.md`, and `laravel-integration.md` became `laravel.md`

### Fixed
- `log_retention_days` did nothing. The `peppol:cleanup` command had 60 hard-coded in its signature, so raising the setting did not keep logs any longer. Without `--days` the command now follows the config, and `--days` still wins
- `PeppolService` could not be constructed at all without PEPPOL credentials: the typed `string` properties were assigned `null`, so resolving the service from the container failed with a TypeError before `validateCredentials()` could report which setting was missing
- `ViesService` crashed on a VAT number that `preg_replace` could not clean, because the result was passed straight into `strtoupper()` and `substr()`
- `generateXml()` promised a string while `DOMDocument::saveXML()` can return false; it now throws instead of returning the wrong type
- `UnitCodes` listed `TNE`, `KWH` and `KWT` twice. The values were identical, so no code changed meaning, but the duplicates are gone
- Removing existing `TaxTotal` elements looped on a node that could be detached, which could never end
- `CompanyRegistrationService::cleanNumber()` promised a string and could return null
- The service provider called `loadMigrationsFrom()` on a directory holding only `create_peppol_logs_table.php.stub`. Laravel only loads files matching `*_*.php`, so the migration was never loaded automatically despite the comment saying it was. The log table is now documented for what it has always been: opt-in, through `vendor:publish --tag=ubl-peppol-migrations`
- `publishes()` and the command registration ran on every request instead of only in the console

### Removed
- `docs/internal/` with the Windsurf and Copilot instructions, and `docs/copilot-best-practices.md`. Internal working notes do not belong in a published package

## [1.6.1] - 2026-02-11

_Never released on its own; these changes are part of the next release._


### Changed

- Aligned the Dutch service class name casing to `UblNlBis3Service` across the container binding and implementation for consistency with documentation and tests.
- Updated project documentation to state PHP 8.2+ as the minimum requirement.

## [1.6.0] - 2026-02-10

### Added

- **Credit Notes Support** - Full PEPPOL BIS Billing 3.0 / EN 16931 compliant Credit Notes
  - `createCreditNoteDocument()` - Initialize a Credit Note document (root element `<CreditNote>`)
  - `addCreditNoteHeader(string $creditNoteNumber, $issueDate)` - Add Credit Note header with type code 381
  - `addBillingReference(string $invoiceNumber, ?string $issueDate)` - Reference to original invoice (required by BR-55)
  - `addCreditNoteLine(array $lineData)` - Add credit note lines with `<CreditedQuantity>` element
  - `isCreditNote()` - Check if current document is a Credit Note
  - Automatic conversion of negative amounts to positive (PEPPOL requires positive amounts)
  - BR-55 validation: Credit Notes must have a BillingReference
  - Complete documentation in `docs/credit-notes.md`
  - Full test coverage in `tests/Feature/CreditNoteBeTest.php`

- **UblValidator** - Enhanced validation with document-level allowances and charges
  - Added BR-CO-11 validation: Sum of document allowances must match AllowanceTotalAmount
  - Added BR-CO-12 validation: Sum of document charges must match ChargeTotalAmount
  - Added BR-S-08 validation: TaxableAmount per VAT category = line amounts - allowances + charges
  - New parameters `$documentAllowances` and `$documentCharges` for `validateInvoiceTotals()`
  - Improved error messages with detailed breakdown per category
  - Corrections now include `allowance_total_amount`, `charge_total_amount`, and per-category details

- **UblNlBis3Service** - BR-27 validation for invoice lines
  - Validates that item net price (BT-146) is not negative
  - Validates that line extension amount is not negative
  - Clear error messages with Dutch solution suggestions

### Changed

- **UblNlBis3Service** - Code style improvements (PSR-12 compliance, consistent spacing)

## [1.5.0] - 2026-01-13

### Added

- **ViesService** - EU VAT number validation via VIES (VAT Information Exchange System)
  - `checkVat(string $countryCode, string $vatNumber)` - Validate VAT number with country code
  - `checkFullVatNumber(string $fullVatNumber)` - Validate full VAT number (e.g., BE0999000228)
  - Real-time validation against EU VIES database
  - Returns company name, address, and registration status
  - Automatic input cleaning (removes spaces, country prefixes)
  - Error message translation for common VIES errors
  - Framework-independent implementation (no Laravel dependencies)
  - Complete documentation in `docs/vies-validation.md`

- **CompanyRegistrationService** - Company registration number validation for multiple EU countries
  - `validate(string $number, string $countryCode)` - Validate registration number
  - `getSupportedCountries()` - Get list of supported countries with format info
  - Support for 5 European countries:
    - **Netherlands (NL)**: KVK - 8 digits
    - **Belgium (BE)**: KBO - 10 digits with mod97 checksum validation
    - **Luxembourg (LU)**: RCS - 1 letter + 6 digits
    - **France (FR)**: SIREN (9 digits) or SIRET (14 digits)
    - **Germany (DE)**: Handelsregister - HRA/HRB + 1-6 digits
  - Automatic input cleaning (removes spaces, dots, dashes)
  - Detailed validation responses with formatted numbers
  - Country-specific format validation and checksum verification
  - Framework-independent implementation
  - Complete documentation in `docs/company-registration-validation.md`

### Changed

- **Belgian EndpointID specification** - EndpointID now uses VAT number WITHOUT country prefix
  - For Belgium: EndpointID should be `0999000228` (not `BE0999000228`)
  - Scheme ID `0208` remains the same (Belgian VAT)
  - VAT number parameter still includes prefix (e.g., `BE0999000228`)
  - Updated documentation in `docs/belgium-implementation.md`
  - Updated code examples in `docs/api-reference.md`
  - Added clear distinction between EndpointID and VAT number formats

### Documentation

- Added comprehensive VIES validation guide (`docs/vies-validation.md`)
  - Basic usage examples
  - Response structure documentation
  - All EU member states listed
  - Error handling best practices
  - Caching and rate limiting strategies
  - Laravel integration examples
  - Testing and mocking examples
- Updated Belgian implementation guide with correct EndpointID format
- Updated API reference with EndpointID parameter clarifications
- Added EndpointID format examples for Belgium and Netherlands

## [1.4.1] - 2026-01-12

### Changed

- Simplified README.md, moved detailed documentation to `/docs`

## [1.4.0] - 2026-01-12

### Added

- Laravel integration documentation (`docs/laravel-integration.md`)
- `loadMigrationsFrom()` for automatic migration loading in Laravel

### Changed

- Translated `PeppolService` from Dutch to English (log messages, error messages, docblocks)
- Made package standalone-compatible (UBL services work without Laravel)
- Moved `illuminate/support` from `require` to `suggest` in composer.json
- Updated `pestphp/pest` to ^3.0 for Laravel 12 compatibility
- Added `orchestra/testbench` ^9.0|^10.0 for package testing

## [1.3.0] - 2026-01-12

### Added

- **Invoice Validation System** - Complete EN16931/Peppol BIS Billing 3.0 compliance validation
  - `UblValidator::validateInvoiceTotals()` - Validates invoice totals according to Peppol rules
  - `InvoiceValidationResult` class - Structured validation result with errors, warnings, and corrections
  - `UblBeBis3Service::validate()` - Validate invoice before generating XML
  - `UblBeBis3Service::calculateTotals()` - Calculate correct totals based on invoice lines
  - `UblBeBis3Service::generateXml(bool $validateFirst)` - Optional validation before XML generation

### Validation Rules Implemented

- **BR-CO-10**: Sum of Invoice line net amounts = Line extension amount
- **BR-CO-13**: Invoice total amount without VAT = Line extension amount - allowances + charges
- **BR-CO-15**: Invoice total amount with VAT = Invoice total without VAT + Invoice total VAT amount
- **BR-CO-16**: Amount due for payment = Invoice total with VAT - Paid amount
- Tax amount calculation per category (taxable_amount × tax_percent)
- Taxable amount per category matches sum of invoice lines

### Changed

- `UblBeBis3Service` now tracks invoice lines, totals, and tax totals internally for validation
- Added tracking properties: `$invoiceLines`, `$totals`, `$taxTotals`, `$allowanceTotalAmount`, `$chargeTotalAmount`, `$prepaidAmount`
- `addInvoiceLine()` now stores line data for validation
- `addLegalMonetaryTotal()` now stores totals for validation
- `addTaxTotal()` now stores tax totals for validation

### Features

- Automatic correction suggestions when validation fails
- Dutch error messages for better user experience
- Support for multiple tax categories (different VAT percentages)
- Tolerance of €0.01 for rounding differences

## [1.2.5] - 2025-12-15

### Fixed

- Allowed `UblBeBis3Service::addPaymentMeans` and `addPaymentTerms` to accept omitted optional parameters by defaulting the nullable arguments to `null`

## [1.2.4] - 2025-12-15

- Prevented undefined index errors in `UblBeBis3Service::addInvoiceLine` by deriving `line_extension_amount` from `quantity * price_amount` when not provided

### Changed

- Updated Belgian implementation guide to include explicit `null` placeholders for unused payment parameters

## [1.2.3] - 2025-09-02

### Fixed

- Fixed PEPPOL UBL validation errors for complete compliance
- Fixed CustomizationID encoding issues for PEPPOL BIS Billing 3.0
- Fixed Dutch customer CompanyID to use KVK number (8 digits) instead of VAT number for schemeID 0106
- Fixed Italian Codice Fiscale format validation (RSSMRA85M01H501Z) for schemeID 0210
- Removed TaxCategory Name elements from TaxTotal for UBL-CR-504 compliance
- Removed TaxTotal elements from InvoiceLine for UBL-CR-561 compliance
- Removed ClassifiedTaxCategory Name elements from InvoiceLine Items for UBL-CR-597 compliance

### Changed

- Updated test data with correct KVK number format for Dutch customers
- Updated EndpointID format for Italian customers to valid Codice Fiscale
- Reorganized test_data.php files per country (be/test_data.php, nl/test_data.php)

## [1.2.2] - 2025-09-02

### Changed

- Translated all documentation from Dutch to English for international accessibility
- Updated docs/README.md, docs/api-reference.md, docs/belgium-implementation.md, docs/netherlands-implementation.md
- Updated docs/validation.md and docs/troubleshooting.md to English
- Translated WINDSURF_INSTRUCTIONS.md to English for AI assistant compatibility
- Updated composer.json keywords formatting for better readability
- Removed hardcoded version from composer.json to use Git tags for versioning

### Fixed

- Fixed IBAN example in test_data.php for correct validation
- Fixed Packagist version mismatch by removing version field from composer.json

## [1.2.1] - 2025-09-02

### Changed

- Harmonized method signatures between UblBeBis3Service and UblNlBis3Service for consistent API
- Updated parameter names for consistency: `endpointScheme` → `endpointSchemeID`, `means_code` → `paymentMeansCode`, etc.
- Enhanced browser-based examples with download functionality
- Updated WINDSURF_INSTRUCTIONS.md with step-by-step implementation guide

### Added

- Added comprehensive Windsurf AI instructions for package usage
- Added author information to README.md

## [1.2.0] - 2025-09-01

### Added

- Added correct Belgian BTCC values for TaxCategory Names ("Taux standard", "Taux zéro")
- Added support for multiple PEPPOL validation standards (Belgium, Italy, Netherlands)
- Added automatic schemeID="0106" for Dutch KVK numbers in CompanyID
- Added second AdditionalDocumentReference for Belgian UBL compliance (ubl-BE-01)

### Changed

- Updated CustomizationID to standard PEPPOL value for general compliance
- Improved TaxTotal element positioning in InvoiceLine for correct XSD validation
- Enhanced test data with valid Italian Codice Fiscale format
- Updated project.md with Dutch and Belgian validator links

### Fixed

- Fixed ubl-BE-10 Schematron validation error with correct BTCC values
- Fixed ubl-BE-14 validation error by positioning TaxTotal in InvoiceLine correctly
- Fixed XSD validation errors through correct element ordering
- Fixed PEPPOL Italy validator warnings (UBL-CR-504, UBL-CR-561, UBL-CR-597)
- Fixed Dutch KVK number validation by adding schemeID
- Fixed Italian Codice Fiscale format validation

### Removed

- Removed TaxCategory Name elements where not required for PEPPOL compliance
- Removed TaxTotal from InvoiceLine for general PEPPOL standard compliance

## [1.1.0] - 2025-09-01

### Added

- Added support for generating credit notes and corrective invoices
- Added Belgian implementation (EN 16931) specific functionality
- Added validation for Belgian VAT numbers
- Added support for multiple document types (invoices, credit notes, corrective invoices, invoice lists)

### Changed

- Updated README from Dutch to English
- Improved error messages and validation
- Enhanced documentation with more detailed examples
- Optimized XML generation for better performance

### Fixed

- Fixed issues with decimal number formatting
- Resolved namespace handling in generated XML

## [1.0.1] - 2025-07-11

### Changed

- Removed duplicate examples directory (src/examples)
- Updated author name in composer.json

## [1.0.0] - 2025-07-10

### Added

- Initial public release of the package
- Functionality for generating UBL/PEPPOL invoices
- Laravel Service Provider
- Example code

[Unreleased]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.7.0...HEAD
[1.7.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.4.1...v1.5.0
[1.4.1]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.2.5...v1.3.0
[1.2.5]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.2.4...v1.2.5
[1.2.4]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.2.3...v1.2.4
[1.2.3]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/ArvidDeJong/ubl-peppol/releases/tag/v1.0.0
