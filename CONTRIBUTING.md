# Contributing

Contributions are welcome: bug reports, fixes, documentation and ideas.

## Before you start

- **Bugs:** open an [issue](https://github.com/ArvidDeJong/ubl-peppol/issues/new/choose) with the invoice data that reproduces it. Invent the names and numbers; never paste real customer data.
- **A receiver rejects your invoice:** check the rule code against the [PEPPOL BIS Billing 3.0 specification](https://docs.peppol.eu/poacc/billing/3.0/bis/) first. If the specification says the package is wrong, that is a bug worth reporting.
- **Features:** open an issue first. A new country profile in particular is a big commitment, so let's agree it fits before you build it.
- **Security issues:** don't open an issue; see [SECURITY.md](SECURITY.md).

## Development

```bash
git clone https://github.com/ArvidDeJong/ubl-peppol.git
cd ubl-peppol
composer install

composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 8
```

CI runs the tests on PHP 8.2-8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.

## Pull requests

- Add or update tests for every change in behaviour.
- **Laravel stays optional.** Only `UblPeppolServiceProvider`, `PeppolService`, `Models\PeppolLog` and `Console\CleanupPeppolLogsCommand` may import `Illuminate\...`. `tests/Unit/StandaloneCoreTest.php` fails on an import anywhere else, because that breaks every user who installed the package without a framework. Tests that need an application go in `tests/Laravel`; the rest runs without one.
- **Keep the country rules apart.** `UblNlBis3Service` and `UblBeBis3Service` are separate classes because their checks and their method signatures differ. Don't merge them behind a flag, and don't copy a fix across without checking that country's rules.
- Keep the public API compatible within 1.x: the method names of both builders, `InvoiceValidationResult`, the static helpers on `UblValidator`, the cases and methods of `Vat\VatCategory` and `Vat\VatExemptionReason`, the keys of an `addTaxTotal()` entry, `ViesService`, `CompanyRegistrationService`, `PeppolService`, the config keys, the publish tags and the `peppol:cleanup` command.
- A change a user notices (a different element in the output, a new validation rule that fires, another default) is a minor release, not a patch. Output that a receiver accepted before and rejects now is breaking, whatever the specification says.
- Write code, comments and messages in English.
- Update `docs/`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.
- The documentation in `docs/` is also the website. Don't write `{{ }}` or `{% %}` there; Jekyll would render it.

## Releases that change the generated XML

`validate()` is not the receiver's Schematron, and neither is the test suite. Before a release that changes what a builder writes, the maintainer checks real output against an official validator:

1. Generate the samples:

   ```bash
   php examples/validate/generate_samples.php
   ```

   It writes one file per case into `examples/validate/out/` (ignored by git), checks that each is well formed and passes `validate()`, and exits with 1 when one does not.

2. Upload the Dutch files (`nl-*.xml`) to the [Dutch PEPPOL validator](https://test.peppolautoriteit.nl/validate) and the Belgian files (`be-*.xml`) to the [Ecosio validator](https://ecosio.com/en/peppol-and-xml-document-validator/), as "PEPPOL BIS Billing 3.0" invoice or credit note.
3. Every file must come back without errors. Look up a rule code in the [PEPPOL BIS Billing 3.0 rules](https://docs.peppol.eu/poacc/billing/3.0/bis/); the specification decides, not the package.
4. A pull request that adds or changes an element adds a case to `generate_samples.php`, and names the business term (such as BT-19) and the rule (such as BR-CO-11) in its tests and in the CHANGELOG.

A changed document is a minor release, never a patch.

## The online validator

`docs/validator.md` runs the official rules in the browser from `docs/assets/validator/`: the rules compiled for SaxonJS, the SaxonJS runtime with its licence, and `release.json`. When OpenPEPPOL publishes a new release of the rules, compile it:

```bash
examples/validate/build-browser-validator.sh <dir with CEN-EN16931-UBL.xslt and PEPPOL-EN16931-UBL.xslt> <unpacked SaxonJS 2 browser release> 2026.11
```

Then check that `docs/assets/validator/samples/valid-dutch-invoice.xml` is still valid on the page and that `broken-intra-community.xml` still reports BR-IC-10, BR-IC-11 and BR-IC-12.

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
