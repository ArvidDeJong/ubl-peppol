---
title: "Dutch invoices"
nav_order: 4
description: "Build a Dutch PEPPOL invoice with UblNlBis3Service: the fields of every call, the NL-R rules validate() checks, scheme IDs 0106 and 0190, and known limits."
---

# Dutch invoices

`Darvis\UblPeppol\UblNlBis3Service` builds an invoice for a Dutch receiver. [Your first invoice](getting-started.md) has a complete example; this page explains each call and what the builder checks.

The Dutch builder builds **invoices only**. It has no credit note methods. [Credit notes](credit-notes.md) are built with the Belgian builder.

## The order of the calls does not matter

The UBL schema fixes the order of the elements inside `<Invoice>`, and a receiver rejects a document that has them in another order. `generateXml()` puts the elements in schema order for you, so you can call `addInvoiceLine()` before `addTaxTotal()`. Elements of the same kind, such as the lines, keep the order in which you added them.

Two rules remain: call `createDocument()` first, and call it once.

## Scheme IDs: what kind of number is this

An `endpointId` is the address PEPPOL delivers to. The `endpointSchemeID` next to it says what kind of number it is.

| Scheme ID | Number | Format |
| --- | --- | --- |
| `0106` | KvK number (Dutch Chamber of Commerce) | 8 digits |
| `0190` | OIN (Dutch government organisations) | 20 digits |
| `0088` | GLN | 13 digits |
| `0208` | Belgian enterprise number | 10 digits |

For a Dutch supplier or customer, `validate()` accepts only `0106` and `0190` as the endpoint scheme. The package does not detect the scheme from the number: you pass it. One value is changed for you: for a customer in `NL`, the scheme `0210` is written as `0106`.

## The calls

### `addInvoiceHeader(string $invoiceNumber, $issueDate, $dueDate)`

- The number may not be empty and may not be longer than 35 characters.
- A date is a `YYYY-MM-DD` string or a `\DateTime` object. A `DateTimeImmutable` (and so a `CarbonImmutable`) is refused; pass `$date->format('Y-m-d')`.
- The invoice date may not be in the future, and the due date must be after the invoice date.

All problems are reported together in one `InvalidArgumentException` that starts with `Validation error(s) in invoice header:`.

### `addBuyerReference(?string $buyerRef = 'BUYER_REF')` and `addOrderReference(string $orderNumber = 'PO-001')`

PEPPOL requires a buyer reference or an order reference (rule PEPPOL-EN16931-R003). Always pass your own value: when you pass nothing, the builder writes the literal text `BUYER_REF` or `PO-001` into the invoice.

### `addAccountingSupplierParty(...)` and `addAccountingCustomerParty(...)`

The arguments are positional. The [API reference](api-reference.md#parties) lists them in order. An empty required argument throws an `InvalidArgumentException` that lists every missing field.

For the customer, `vatNumber` is optional. When you pass it, it must start with a two-letter country code, otherwise the builder throws `VAT number must start with a 2-letter ISO 3166-1 alpha-2 country code`. Without it, no `PartyTaxScheme` is written.

### `addPaymentMeans(...)`

| Argument | Default | Notes |
| --- | --- | --- |
| `$paymentMeansCode` | `'30'` | Digits only. Between two Dutch parties `validate()` accepts `30`, `48`, `49`, `57`, `58` and `59` |
| `$paymentMeansName` | `'Credit transfer'` | Not written to the XML |
| `$paymentId` | `null` | The payment reference |
| `$accountId` | `null` | The IBAN, without spaces. The checksum is verified; this needs the `bcmath` extension |
| `$accountName` | `null` | Not written to the XML |
| `$financialInstitutionId` | `null` | The BIC, 8 or 11 characters, upper case |
| `$paymentChannelCode`, `$paymentDueDate` | `null` | Not written to the XML |

### `addPaymentTerms(?string $note = null)`

The note is required in practice: an empty note throws `Payment terms note is required and cannot be empty`.

### `addAllowanceCharge(bool $isCharge = true, float $amount = 0.0, string $reason = '', string $taxCategoryId = 'S', float $taxPercent = 0.0, string $currency = 'EUR')`

A charge (`true`) adds to the invoice, an allowance (`false`) is a discount. The amount may not be negative and the reason is required. The VAT category is only written when `$taxPercent` is greater than 0.

### `addTaxTotal(array $taxes)`

One entry per VAT rate. Every entry needs all six keys, otherwise the builder throws `Tax entry #1: ...`:

| Key | Example | Notes |
| --- | --- | --- |
| `taxable_amount` | `425.00` | Not negative |
| `tax_amount` | `89.25` | Not negative |
| `currency` | `'EUR'` | Exactly 3 characters |
| `tax_category_id` | `'S'` | `S`, `Z`, `E`, `AE`, `K`, `G` or `O` |
| `tax_percent` | `21.0` | 0 to 100 |
| `tax_scheme_id` | `'VAT'` | |

Call it once. A second call adds a second `<cac:TaxTotal>`.

### `addLegalMonetaryTotal(array $amounts, string $currency = 'EUR')`

Five keys are required and must be numeric: `line_extension_amount`, `tax_exclusive_amount`, `tax_inclusive_amount`, `charge_total_amount` and `payable_amount`. A missing key throws, for example, `Charge total amount is required`.

### `addInvoiceLine(array $lineData)`

| Key | Required | Notes |
| --- | --- | --- |
| `id` | yes | The line number |
| `quantity` | yes | |
| `unit_code` | yes | A UN/ECE Recommendation 20 code such as `C62` (piece), `HUR` (hour), `DAY`, `KGM`, `MTR`, `LTR` |
| `price_amount` | yes | The price of one unit, without VAT. Not negative |
| `currency` | yes | |
| `name` | yes | |
| `description` | yes | |
| `line_extension_amount` | no | The line total without VAT. Defaults to `price_amount` times `quantity` |
| `tax_category_id` | no | Defaults to `S` |
| `tax_percent` | no | Defaults to `21.00` |
| `accounting_cost` | no | |
| `order_line_id` | no | Needs `addOrderReference()` as well (NL-R-009) |
| `base_quantity` | no | Defaults to `1` |

The VAT scheme of a line is always written as `VAT`.

## What `validate()` checks

The Dutch `validate()` checks codes and five Dutch rules. It does **not** check that your amounts add up, and it reports an empty document as valid.

| Check | Result |
| --- | --- |
| Currency codes are three letters, scheme IDs four digits, payment means codes one to three digits, VAT categories one of `S`, `Z`, `E`, `AE`, `K`, `G`, `O` | Error |
| A unit code that is not in the package's list | Warning |
| NL-R-003: Dutch supplier, endpoint scheme is not `0106` or `0190` | Error |
| NL-R-005: Dutch customer, endpoint scheme is not `0106` or `0190` | Error |
| NL-R-007: Dutch supplier and no `addPaymentMeans()` | Warning |
| NL-R-008: both parties Dutch and a payment means code other than `30`, `48`, `49`, `57`, `58`, `59` | Error |
| NL-R-009: a line has `order_line_id` and there is no `addOrderReference()` | Error |

A warning does not make the document invalid. Read warnings with `$result->warnings` or `$result->getWarningsAsString()`. [Validation](validation.md) has the details.

## What the Dutch builder writes for you

These values are fixed in the current version. You cannot change them through the public methods, so check them in your XML before you send a real invoice.

| Element | Value written | What to know |
| --- | --- | --- |
| `cbc:DocumentCurrencyCode` | `EUR` | The document currency is always EUR |
| `cbc:AccountingCost` (document level) | `4025:123:4343` | Written into every invoice by `addInvoiceHeader()` |
| Supplier `cac:PartyLegalEntity/cbc:RegistrationName` | `SupplierOfficialName Ltd` | Not the `$partyName` you pass |
| Supplier `cac:PartyLegalEntity/cbc:CompanyID` | The `$companyId` argument (your VAT number) with `schemeID="0106"` | `0106` means KvK number |
| Customer `cac:PartyLegalEntity/cbc:CompanyID` | The `$companyId` argument with `schemeID="0106"` | Also for a customer outside the Netherlands |
| `cac:AdditionalDocumentReference` | Only the `cbc:ID` | The `$documentType` argument is ignored |
| `cac:LegalMonetaryTotal` | The five required amounts | `allowance_total_amount` and `prepaid_amount` are not written |

These are open issues in the package, not Dutch rules. If one of them blocks you, [open an issue](https://github.com/ArvidDeJong/ubl-peppol/issues).

## Dutch VAT categories

| Situation | `tax_category_id` | `tax_percent` |
| --- | --- | --- |
| Standard rate | `S` | `21.0` |
| Reduced rate | `S` | `9.0` |
| Zero rate | `Z` | `0.0` |
| Exempt | `E` | `0.0` |
| Reverse charge | `AE` | `0.0` |

## Check the result

Upload the XML to the [Dutch PEPPOL validator](https://test.peppolautoriteit.nl/validate) before you send a first invoice.
