# Changelog

All notable changes to **darvis/ubl-peppol** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

**The generated XML changes** for a VAT breakdown in category `K`, `AE`, `G` or `O`, so this is a minor release. Such a document was rejected before; see Fixed. Documents with the standard rate (`S`) are byte for byte the same. The eight documents of `php examples/validate/generate_samples.php` pass the official OpenPEPPOL Schematron rules, release 2026.5 (`CEN-EN16931-UBL` and `PEPPOL-EN16931-UBL`), without an error or a warning.

### Added
- **A knowledge base on VAT categories in code.** `Darvis\UblPeppol\Vat\VatCategory` is an enum of the nine categories PEPPOL allows outside Italy (`S`, `Z`, `E`, `AE`, `K`, `G`, `O`, `L`, `M`), each with `label()`, `description()` (when to use it), `requiresExemptionReason()`, `defaultExemptionReasonCode()`, `exemptionReasonText('en'|'nl'|'fr')`, `requiresZeroRate()` and `rules()` (the EN 16931 rules by code). `VatCategory::guide()` returns it all as arrays, for a select list or a help page. `Darvis\UblPeppol\Vat\VatExemptionReason` holds the VATEX code list with `isKnown()`, `name()`, `categoryOf()` and `codes()`. A new docs page [VAT categories](https://arviddejong.github.io/ubl-peppol/vat-categories.html) explains the choice, reverse charge versus intra-community supply included
- **Exemption reasons in the VAT breakdown** (BT-120, BT-121), in both builders. An entry of `addTaxTotal()` takes `tax_exemption_reason_code` and `tax_exemption_reason`, written between `cbc:Percent` and `cac:TaxScheme`. What you do: nothing for `K`, `AE`, `G` and `O`; for `E` pass the code or a text
- `validate()` of both builders reports `[BR-E-10]` for an exempt breakdown without a reason, `[BR-IC-11]` and `[BR-IC-12]` for category `K` without a delivery date or deliver to country, and `[BR-O-11]` for category `O` next to another category
- `UblValidator::resolveTaxExemption()` and `UblValidator::validateVatBreakdown()`, which the builders use

### Changed
- `UblValidator::isValidTaxCategory()` accepts `L` (Canary Islands) and `M` (Ceuta and Melilla), which PEPPOL allows; its error message lists them
- `addTaxTotal()` throws an `InvalidArgumentException` for a reason on a category that takes none (`[BR-S-10]`, `[BR-Z-10]`, `[BR-AF-10]`, `[BR-AG-10]`), for a code outside the VATEX list (`[BR-CL-22]`) and for a code of another category, such as `VATEX-EU-IC` with `AE` (PEPPOL-EN16931-P0104 to P0111). These keys did not exist before, so no existing call is affected

### Fixed
- **An intra-community supply, reverse charge, export or not-subject document was rejected.** The VAT breakdown of category `K`, `AE`, `G` or `O` had no exemption reason, which the receiver refuses with the fatal BR-IC-10, BR-AE-10, BR-G-10 or BR-O-10, and there was no way to pass one. Now the builders write the code that belongs to the category (`VATEX-EU-IC`, `VATEX-EU-AE`, `VATEX-EU-G`, `VATEX-EU-O`) when you pass none. What you do: nothing
- Dutch builder: `addDelivery()` with a country and no street, city or location dropped the country. It is written now as the delivery address (BT-80), which BR-IC-12 asks for an intra-community supply. With a street or a city nothing changes

## [1.10.1] - 2026-09-21

### Fixed
- **A document dated today was refused as "in the future".** `addInvoiceHeader()` of both builders threw `Invoice date cannot be in the future` for today's date passed as a `YYYY-MM-DD` string, and the Dutch `addCreditNoteHeader()`, new in 1.10.0, also for a `DateTime` of today. The parsed date carried the time of the moment it was parsed, which is later than "today" at midnight. A date of tomorrow is still refused. What you do: nothing; a workaround that passed a `DateTime` instead of a string keeps working

## [1.10.0] - 2026-09-21

**The generated XML changes**, so this is a minor release. Every change follows the [PEPPOL BIS Billing 3.0 specification](https://docs.peppol.eu/poacc/billing/3.0/bis/); the business term (BT) and the rule are named with each. No public method changed its signature. The six documents of `php examples/validate/generate_samples.php` and six documents from a production application pass the UBL 2.1 XSD and the official OpenPEPPOL Schematron rules, release 2026.5 (`CEN-EN16931-UBL` and `PEPPOL-EN16931-UBL`, which holds the NL-R rules), without an error or a warning.

### Changed
- **`cbc:AccountingCost` (BT-19, buyer accounting reference) is no longer written by default**, in both builders. Before: `addInvoiceHeader()` wrote `<cbc:AccountingCost>4025:123:4343</cbc:AccountingCost>`, the value from the PEPPOL example file, into every invoice. Now: the element is left out, which the specification allows (0..1). What you do: nothing, unless your customer gave you a booking reference; then call the new `addAccountingCost('their reference')`
- **Dutch builder: the supplier's `PartyLegalEntity/cbc:RegistrationName` (BT-27, seller name) is the `$partyName` you pass.** Before: the literal `SupplierOfficialName Ltd`, in every invoice. What you do: nothing
- **Dutch builder: the supplier's VAT number is no longer written as its legal registration (`PartyLegalEntity/cbc:CompanyID`, BT-30) under scheme `0106`.** Scheme `0106` means "KvK number", and NL-R-003 wants a KvK number (`0106`) or an OIN (`0190`) there. Before: `<cbc:CompanyID schemeID="0106">NL123456789B01</cbc:CompanyID>`. That was wrong for every caller who passed a VAT number as `$companyId`, which is what the argument is for; it passed validation only because the Schematron tests the scheme and not the number. Now: the element is written from the new `addSupplierLegalRegistration()`, or, as before and byte for byte the same, when `$companyId` is 8 digits; otherwise it is left out (BT-30 is 0..1, and BR-CO-26 is met by the VAT number in BT-31). **What you do: call `addSupplierLegalRegistration('your KvK number')` once per invoice.** A Dutch receiver may expect the KvK number
- **Dutch builder: the customer's `PartyLegalEntity/cbc:CompanyID` (BT-47) gets scheme `0106` only for a customer in the Netherlands** (NL-R-005). Before: `schemeID="0106"` for every customer, also a Belgian or German one. Now: for a customer in another country the `schemeID`, which is optional, is left out; a `$companyId` that starts with a country prefix is a VAT number and is not written as a registration. A Dutch customer with a KvK number gets the same XML as before. What you do: for a foreign customer whose register you know, or a Dutch customer with an OIN, call the new `addCustomerLegalRegistration($number, $schemeId)`
- **Dutch builder: the customer's `cac:PartyIdentification/cbc:ID` (BT-46, buyer identifier) gets the endpoint's `schemeID` only when it is that kind of identifier.** Before: every `$partyId` got the scheme of the endpoint, so an internal customer number went out as `<cbc:ID schemeID="0106">CUST-710</cbc:ID>`, a KvK number that is none. The official Schematron answers that with the warning PEPPOL-COMMON-R054, and under scheme `0208` with the fatal PEPPOL-COMMON-R043. Now: the scheme (BT-46-1, optional) is written when `$partyId` equals `$endpointId`, is 8 digits under `0106` or 20 digits under `0190`; otherwise it is left out. A customer whose KvK number is passed as `$partyId` gets the same XML as before. What you do: nothing
- **Dutch builder: `addAllowanceCharge()` writes `cac:TaxCategory` (BT-95 for a discount, BT-102 for a charge) at 0% too.** BR-32 and BR-37 require the category on every document level allowance and charge. Before: left out when `$taxPercent` was 0, so a zero rated, exempt or reverse charge discount was rejected. Now: `cbc:ID`, `cbc:Percent` `0.00` and the tax scheme; for category `O` no percent, as BR-O-06 and BR-O-07 demand. With a percentage above 0 nothing changes
- **Belgian builder: a customer VAT number (BT-48) in lower case is accepted and written in upper case** (BR-CO-09), as the Dutch builder already did. Before: `be0999000228` threw
- `validate()` of the Belgian builder counts the document level allowances and charges, so a result that was invalid can now be valid; see Fixed

### Added
- `addAccountingCost(string $value)` on both builders (BT-19). The Dutch builder sorts it into place; the Belgian builder inserts it directly behind `cbc:DocumentCurrencyCode`, where the schema wants it, whenever it is called after the header, also on a credit note
- `UblNlBis3Service::addSupplierLegalRegistration(string $identifier, string $schemeId = '0106')` and `addCustomerLegalRegistration(...)` for BT-30 and BT-47, callable before or after the party method. The scheme must be a 4 digit ISO 6523 ICD code (BR-CL-11)
- Dutch builder: `addLegalMonetaryTotal()` writes `cbc:AllowanceTotalAmount` (BT-107) and `cbc:PrepaidAmount` (BT-113) from the keys `allowance_total_amount` and `prepaid_amount` when they are more than zero, in schema order, as the Belgian builder does. Before they were dropped, so an invoice with a discount or a prepayment could not satisfy BR-CO-13 and BR-CO-16 at the receiver. Passing `0` or leaving them out gives the same XML as before; `cbc:ChargeTotalAmount` is written as before, also when `0.00`
- **The Dutch builder builds credit notes.** `UblNlBis3Service` has `createCreditNoteDocument()`, `addCreditNoteHeader($number, $issueDate)`, `addBillingReference($invoiceNumber, $invoiceDate = null)`, `addCreditNoteLine($lineData)` and `isCreditNote()`, with the signatures of the Belgian builder. Before: a host app that picks the builder by country and runs one code path over both died on a Dutch credit note with `Call to undefined method Darvis\UblPeppol\UblNlBis3Service::createCreditNoteDocument()`. The document is a `<CreditNote>` with type code 381, `cbc:CreditedQuantity` on the lines and positive amounts, sorted into the element order of the UBL 2.1 CreditNote schema, which differs from `<Invoice>`. `addBuyerReference()` works on it. Without a billing reference `generateXml()` throws an `InvalidArgumentException` that starts with `[BR-55] [NL-R-001]` and `validate()` reports the same error. `addInvoiceHeader()` and `addInvoiceLine()` throw a `RuntimeException` on a credit note, and the credit note methods throw one on an invoice. Invoices are byte for byte the same. What you do: nothing; if you sent Dutch credit notes through the Belgian builder as a workaround, you can stop
- `examples/validate/generate_samples.php` writes six sample documents for the check against an official validator, and `CONTRIBUTING.md` describes that check

### Fixed
- **Belgian `validate()` failed every correct document with a charge or a discount.** `addAllowanceCharge()` was not passed to the check, so BR-CO-12 or BR-CO-11 and BR-S-08 fired, and the suggested corrections left the charge out. The builder now tracks them and the check includes them
- Belgian `addInvoiceLine()` without `tax_scheme_id` wrote an empty `cac:TaxScheme/cbc:ID` with a PHP warning; it defaults to `VAT` now, like the Dutch builder. `addLegalMonetaryTotal()` without `charge_total_amount` gave a PHP warning; it is `0` now, with the same XML
- Dutch `generateXml()` without `createDocument()` died with a PHP `Error` since 1.8.0. It throws the documented `RuntimeException` again: `Root element is not initialized. Call createDocument() before adding elements.`
- The Dutch example data had a VAT number as the customer's registration number and an Italian tax code as the endpoint of a Dutch customer

## [1.9.1] - 2026-09-21

Documentation only: nothing in `src/` changes. If you built on the old text, these are the claims that were wrong.

### Added
- Documentation pages [Installation](https://arviddejong.github.io/ubl-peppol/installation.html), with a "Check that it works" section, and [Testing](https://arviddejong.github.io/ubl-peppol/testing.html): `Http::fake()` for `PeppolService`, with and without the `peppol_logs` table, and a container mock for `ViesService`
- "Your first invoice" is now one complete Dutch invoice you can copy and run; the Belgian and the credit note page each have a complete example as well. Every complete example in the docs and the README was run against this version
- `tests/DocsSiteTest.php` checks that the home page links every page, that links between pages resolve, and that the FAQ stays between six and ten questions

### Fixed
- **`getCorrections()` was described as "values the builder fixed for you" and "the corrections that were applied"** (README, API reference, Boost guideline and skill). Nothing is applied. It returns the totals the Belgian validator calculated, only when the amounts do not add up; the Dutch builder always returns an empty array
- **`validate()` was described as one check.** The Dutch `validate()` checks code formats and NL-R-003, 005, 007, 008 and 009, does not check amounts and calls an empty document valid. The Belgian `validate()` checks the totals and refuses a document without lines or totals. Credit note rules (BR-55, positive totals) are only enforced by `generateXml()`
- The docs recommended `app(UblNlBis3Service::class)`. That binding is a singleton and a builder holds one document, so the second invoice in a request or queue worker throws `Document is already initialized`. The docs now say `new UblNlBis3Service()`
- `sendInvoice()` calls `$invoice->update(['peppol_sent_at' => now()])` after a successful send. The docs never said so; a model without that column gets `'success' => false` for a send that worked. Documented, with `sendUblXml()` as the alternative
- The Dutch example used the IBAN `NL12 ABNA 0123 4567 89`, which the builder refuses with `Invalid IBAN format` (spaces, and a wrong checksum), and a VAT number as the endpoint with scheme `0106`
- The Belgian page said to pass `tax_category_name` with "BTCC" values such as `Taux standard`, and listed rules `ubl-BE-01`, `ubl-BE-10` and `ubl-BE-14`. The builder ignores `tax_category_name`, writes no category name and implements none of those rules
- The package was said to apply the Dutch "NLCIUS" rules and to detect KvK and OIN numbers automatically. It writes the PEPPOL BIS Billing 3.0 customization ID, checks five NL-R rules, and only rewrites scheme `0210` to `0106` for a Dutch customer
- `PeppolService` was said to be compatible with named providers, with an example URL. It sends one HTTP Basic `POST` with `Content-Type: application/xml`; ask your provider whether it accepts that
- The error table on the sending page quoted Dutch messages (`PEPPOL_URL is niet geconfigureerd`, `Factuur succesvol verzonden naar Peppol netwerk`). The real messages are `Peppol URL is not configured (PEPPOL_URL)` and `Invoice successfully sent to Peppol network`
- The company number page used a class `KvkService` that does not exist; it is `CompanyRegistrationService`. `number` in the result is the cleaned input (`HRB12345`), not the input as typed
- The VAT page printed `$result['error']` for an invalid number, which is `null` when VIES answered. `valid` false with `error` null means "does not exist"; with an error it means "VIES did not answer". The `soap` extension is required for `ViesService` and `bcmath` for IBAN checks; neither was mentioned
- `UblValidator::validateVatNumber('NL123456789')` was documented as an error. It passes: only the country prefix and the characters are checked
- The troubleshooting page quoted messages the package never writes (`Invalid date format`, `Invoice number cannot be empty`, `Invalid VAT number format`), advised `htmlspecialchars()` on invoice data, which double-escapes because the builders escape already, and said `addChildElement()` adds custom elements, while it is protected. It now quotes the real messages
- The validation page showed invented validator output, an upload API for the Dutch validator that is not part of this package, and headings in Dutch
- The credit note page showed `createDocument()` after `createCreditNoteDocument()` on one builder, which throws, and XML with a `BuyerReference` the builder cannot write
- Documented the values the builders write that you cannot set yet: `DocumentCurrencyCode` `EUR`, `AccountingCost` `4025:123:4343`, and in the Dutch builder the supplier `RegistrationName` `SupplierOfficialName Ltd`; and that the Belgian `validate()` fails a correct document with a charge or a discount (BR-CO-11, BR-CO-12)

### Changed
- README in the order of the other darvis packages, with one working quick start and sections for Laravel Boost and Testing
- FAQ rewritten as ten questions the way they are asked, and the site description, requirements and keywords brought in line with `composer.json` and the code

## [1.9.0] - 2026-09-21

### Fixed
- **`testConnection()` reported "Connection successful" for almost every answer.** Only a 401 counted
  as a failure, so a refused login (403), a wrong `PEPPOL_URL` (404) and a provider that was down
  (5xx) all looked fine. Those are failures now, each with its own message. A 2xx is
  `Connection successful` as before. Any other answer, such as the 405 most providers give to a GET
  on their send URL, still counts as a success but says what happened:
  `Peppol provider reached (HTTP 405); the credentials were not refused`. If you show or match the
  message, check it against the new texts.
- The documentation presented credit notes as something both builders do. Only `UblBeBis3Service`
  builds them; the Dutch builder does invoices only. The README, the docs, the FAQ and the Boost
  guideline and skill now say so, and the skill names the right calls (`createCreditNoteDocument()`
  and `addCreditNoteLine()`).
- The documentation said the package converts negative amounts on a credit note. That is true for
  the lines only. The tax total and the monetary total are written as you pass them, and
  `generateXml()` throws on a negative total (BR-CN-03, BR-CN-04). The docs now tell you to pass
  positive totals.
- The credit notes page said the CreditNote schema does not allow `BuyerReference`. It does; this
  builder does not support it yet and throws, which is what the page now says.

## [1.8.0] - 2026-09-21

### Fixed
- **Sending failed without the `peppol_logs` table.** The table is opt-in, but `sendInvoice()` and
  `sendUblXml()` always wrote a log row before they sent, so an application that had not published
  the migration got a `QueryException` (`no such table: peppol_logs`) instead of a sent invoice.
  `peppol:cleanup` failed the same way. A document is now sent with or without the table. Without it
  nothing is recorded and the result has `'log_id' => null`; with it everything is as before.
  `PeppolLog::tableExists()` tells you which of the two applies. If your code uses `log_id`, allow
  for null.
- **A Dutch invoice built in the order the documentation showed was rejected.** `UblNlBis3Service`
  wrote the elements in the order of the calls, and the example in the docs and the Boost guideline
  added the invoice lines before the tax total and the monetary total, which the UBL schema does
  not allow. The builder now puts the elements under `<Invoice>` in schema order when
  `generateXml()` runs, whatever the order of the calls. Lines, document references and allowances
  keep the order you added them in. A document that was already built in schema order comes out
  byte for byte as before, so nothing a receiver accepted changes. The Belgian builder is unchanged.
- The Laravel page named the command `peppol:cleanup-logs` with a default of 90 days. It is
  `peppol:cleanup`, and it keeps `log_retention_days` days, 60 by default.

## [1.7.1] - 2026-09-21

### Added
- Social preview image for the documentation site (`docs/assets/images/social-preview.png`), set as the default Open Graph and Twitter card image in `docs/_config.yml`

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

[Unreleased]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.10.1...HEAD
[1.10.1]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.10.0...v1.10.1
[1.10.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.9.1...v1.10.0
[1.9.1]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.9.0...v1.9.1
[1.9.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.8.0...v1.9.0
[1.8.0]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.7.1...v1.8.0
[1.7.1]: https://github.com/ArvidDeJong/ubl-peppol/compare/v1.7.0...v1.7.1
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
