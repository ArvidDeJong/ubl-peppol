## darvis/ubl-peppol

Builds UBL 2.1 invoices and credit notes that pass PEPPOL BIS Billing 3.0 and EN 16931 validation, for the Netherlands and Belgium. It is a plain PHP library: the builders, the validator and the VAT and company number checks never touch Laravel.

- Pick the builder by the RECEIVER's country, not the sender's: `UblNlBis3Service` builds Dutch invoices and checks the Dutch NL-R rules, `UblBeBis3Service` builds Belgian invoices and credit notes and checks the totals. They are separate classes because their checks and their signatures differ. Never add a country flag to one of them.
- Build a document in the order of the UBL schema: `createDocument()`, the header, the references, the supplier and the customer, delivery and payment, then the tax total, the monetary total and the lines LAST, then `generateXml()`. The order of elements inside UBL is fixed, and a receiver rejects XML with the right values in the wrong order. The Dutch builder puts the elements under `<Invoice>` in schema order itself when it generates, so there the order of the calls no longer matters. The Belgian builder only moves the totals in front of the lines; for everything else it writes what you call, in the order you call it.
- Validate before sending. `$service->validate()` returns an `InvoiceValidationResult` with `isValid()`, `getErrorsAsString()` and `getCorrections()`; `generateXml(validateFirst: true)` does both and throws on failure. `validate()` never changes the document: the corrections are SUGGESTED totals, only from the Belgian builder and only when the amounts do not add up. The Dutch `validate()` checks code formats and NL-R rules, not amounts, and calls an empty document valid. Credit note rules are only enforced by `generateXml()`. Never ship a first integration without also running the output through an official validator once, because this package checks the rules it implements, not the receiver's full Schematron.
- `UblValidator` is a set of STATIC helpers for single values (unit codes, currency, IBAN, VAT format, tax categories). It does not validate a whole document; that is the builder's `validate()`.
- Amounts on a credit note are positive. The document type and the type code 381 say it is a credit, not a minus sign, and a credit note needs a `BillingReference` to the invoice it corrects (BR-55). Only `UblBeBis3Service` builds credit notes (`createCreditNoteDocument()`, `addCreditNoteHeader()`, `addBillingReference()`, `addCreditNoteLine()`); the Dutch builder does invoices only. `addCreditNoteLine()` makes the price, the quantity and the line amount positive, but the tax total and the monetary total are written as you pass them, and `generateXml()` throws an `InvalidArgumentException` on a negative total (BR-CN-03, BR-CN-04). Pass the totals as positive numbers.
- A VIES answer of "valid" means the number exists right now, nothing more. `ViesService` (needs the `soap` extension) also returns `'valid' => false` when VIES could not answer; then `error` is not null. Treat that as unknown and never block an invoice on it.
- The Laravel layer is optional and separate: the service provider, `PeppolService`, the `PeppolLog` model and the `peppol:cleanup` command. Never import `Illuminate\...` anywhere else in `src/`; a test in the suite fails if you do, because that would break every plain-PHP user.
- The log table is opt-in. It arrives by publishing `--tag=ubl-peppol-migrations`, not automatically, so do not write code that assumes `peppol_logs` exists. `PeppolService` sends without it and then returns `'log_id' => null`; ask `PeppolLog::tableExists()` before you query the model yourself.
- Create a builder per document with `new UblNlBis3Service()`. The container binds the Dutch builder as a singleton, so a second `app(UblNlBis3Service::class)->createDocument()` throws.
- `PeppolService::sendInvoice()` calls `$invoice->update(['peppol_sent_at' => now()])` after a successful send. A host model without that column turns a send that worked into `'success' => false`; add the column or use `sendUblXml()`.
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
