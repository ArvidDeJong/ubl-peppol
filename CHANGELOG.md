# Changelog

All notable changes to this package will be documented in this file.

## [Unreleased]

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
