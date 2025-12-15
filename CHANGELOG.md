# Changelog

All notable changes to this package will be documented in this file.

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
