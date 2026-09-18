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
- **Keep the country rules apart.** `UblNlBis3Service` and `UblBeBis3Service` are separate classes because a field one country requires the other rejects. Don't merge them behind a flag, and don't copy a fix across without checking that country's rules.
- Keep the public API compatible within 1.x: the method names of both builders, `InvoiceValidationResult`, the static helpers on `UblValidator`, `ViesService`, `CompanyRegistrationService`, `PeppolService`, the config keys, the publish tags and the `peppol:cleanup` command.
- A change a user notices (a different element in the output, a new validation rule that fires, another default) is a minor release, not a patch. Output that a receiver accepted before and rejects now is breaking, whatever the specification says.
- Write code, comments and messages in English.
- Update `docs/`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.
- The documentation in `docs/` is also the website. Don't write `{{ }}` or `{% %}` there; Jekyll would render it.

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
