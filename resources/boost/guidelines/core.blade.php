## darvis/ubl-peppol

Builds UBL 2.1 invoices and credit notes that pass PEPPOL BIS Billing 3.0 and EN 16931 validation, for the Netherlands and Belgium. It is a plain PHP library: the builders, the validator and the VAT and company number checks never touch Laravel.

- Pick the builder by the RECEIVER's country, not the sender's: `UblNlBis3Service` applies the Dutch NLCIUS rules, `UblBeBis3Service` the Belgian EN 16931 rules. They are separate classes because a field one country requires is rejected by the other. Never add a country flag to one of them.
- Build a document in the order the specification expects: `createDocument()`, then the header, the parties, the lines, the tax total and the monetary total, then `generateXml()`. The order of elements inside UBL is fixed; adding them out of order produces XML a receiver rejects even though every value is right.
- Validate before sending. `$service->validate()` returns an `InvoiceValidationResult` with `isValid()`, `getErrorsAsString()` and the corrections that were applied; `generateXml(validateFirst: true)` does both and throws on failure. Never ship a first integration without also running the output through an official validator once, because this package checks the rules it implements, not the receiver's full Schematron.
- `UblValidator` is a set of STATIC helpers for single values (unit codes, currency, IBAN, VAT format, tax categories). It does not validate a whole document; that is the builder's `validate()`.
- Amounts on a credit note are positive. The document type and the type code 381 say it is a credit, not a minus sign, and a credit note needs a `BillingReference` to the invoice it corrects (BR-55). The package converts negative amounts for you, so do not pre-negate them.
- A VIES answer of "valid" means the number exists right now, nothing more. VIES is regularly down per member state, so treat a failed lookup as unknown and never block an invoice on it.
- The Laravel layer is optional and separate: the service provider, `PeppolService`, the `PeppolLog` model and the `peppol:cleanup` command. Never import `Illuminate\...` anywhere else in `src/`; a test in the suite fails if you do, because that would break every plain-PHP user.
- The log table is opt-in. It arrives by publishing `--tag=ubl-peppol-migrations`, not automatically, so do not write code that assumes `peppol_logs` exists.
- Config is read through the config file only in the Laravel layer. In plain PHP the builders take everything through their method parameters.

@verbatim
<code-snippet name="Generate and validate a Dutch invoice" lang="php">
use Darvis\UblPeppol\UblNlBis3Service;

$ubl = new UblNlBis3Service();
$ubl->createDocument();
$ubl->addInvoiceHeader('INV-001', '2026-01-15', '2026-02-14');
// ... supplier, customer, lines, tax total, monetary total

$result = $ubl->validate();

if (! $result->isValid()) {
    throw new RuntimeException($result->getErrorsAsString());
}

$xml = $ubl->generateXml();
</code-snippet>
@endverbatim
