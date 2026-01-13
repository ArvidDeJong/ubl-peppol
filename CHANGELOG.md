# Changelog

All notable changes to this package will be documented in this file.

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
