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

For a Dutch supplier or customer, `validate()` accepts only `0106` and `0190` as the endpoint scheme. The legal registration has its own scheme; see [The legal registration of the supplier and the customer](#the-legal-registration-of-the-supplier-and-the-customer). The package does not detect the scheme from the number: you pass it. One value is changed for you: for a customer in `NL`, the scheme `0210` is written as `0106`.

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

A charge (`true`) adds to the invoice, an allowance (`false`) is a discount. The amount may not be negative and the reason is required. The VAT category is always written, because every document level allowance and charge needs one (BT-95 and BT-102, rules BR-32 and BR-37). For a zero rated, exempt or reverse charge amount pass the category (`Z`, `E`, `AE`) and `0.0`; for category `O` (not subject to VAT) no rate is written (BR-O-06, BR-O-07). Put the total of your discounts in `allowance_total_amount` and the total of your charges in `charge_total_amount`.

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

Two keys are optional and written when they are more than zero: `allowance_total_amount` (BT-107, the total of the document level discounts) and `prepaid_amount` (BT-113, what was already paid). A receiver checks `tax_exclusive_amount` = lines - allowances + charges (BR-CO-13) and `payable_amount` = `tax_inclusive_amount` - `prepaid_amount` (BR-CO-16), so pass them whenever you have a discount or a prepayment. `ChargeTotalAmount` is always written, also when it is `0.00`.

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

## The legal registration of the supplier and the customer

`cac:PartyLegalEntity/cbc:CompanyID` is the legal registration identifier: BT-30 for the supplier, BT-47 for the customer. For a Dutch party it must be a KvK number (scheme `0106`) or an OIN (scheme `0190`); that is what the rules NL-R-003 and NL-R-005 check. It is not the VAT number, which has its own place (BT-31 and BT-48).

Pass it with its own method. Call it before or after the party method:

```php
$ubl->addSupplierLegalRegistration('12345678');                       // KvK number, scheme 0106
$ubl->addSupplierLegalRegistration('00000001234567890123', '0190');   // OIN

$ubl->addCustomerLegalRegistration('87654321');                       // Dutch customer
$ubl->addCustomerLegalRegistration('0999000228', '0208');             // Belgian customer, enterprise number
```

The scheme is a four digit ISO 6523 ICD code (BR-CL-11); anything else throws an `InvalidArgumentException`, and so does an empty number.

Without these methods the builder derives the element from the party arguments:

| Party | Argument | What is written |
| --- | --- | --- |
| Supplier | `$companyId` is 8 digits | `<cbc:CompanyID schemeID="0106">` with that number |
| Supplier | `$companyId` is anything else, such as your VAT number | No `CompanyID` in `PartyLegalEntity`. The VAT number is still written in `PartyTaxScheme` |
| Customer | `$companyId` of a customer in `NL` | `<cbc:CompanyID schemeID="0106">` |
| Customer | `$companyId` of a customer in another country | `<cbc:CompanyID>` without a `schemeID`, which is optional |
| Customer | `$companyId` starts with two letters, so it is a VAT number | No `CompanyID`. Pass a VAT number as `$vatNumber` |

Until 1.10.0 the builder wrote the supplier's VAT number under scheme `0106`, and scheme `0106` for every customer. For a Dutch supplier, always call `addSupplierLegalRegistration()`.

## The buyer accounting reference

`addAccountingCost(string $value)` writes `cbc:AccountingCost` (BT-19), the reference the buyer uses to book the invoice. It is optional; leave the call out when your customer did not give you one. `generateXml()` puts it between the currency and the buyer reference. A second call replaces the first.

Until 1.10.0 `addInvoiceHeader()` wrote `<cbc:AccountingCost>4025:123:4343</cbc:AccountingCost>`, the value from the PEPPOL example file, into every invoice.

## What you cannot set yet

| Element | Value written | What to know |
| --- | --- | --- |
| `cbc:DocumentCurrencyCode` | `EUR` | The document currency is always EUR |
| `cac:AdditionalDocumentReference` | Only the `cbc:ID` | The `$documentType` argument is ignored |

If one of them blocks you, [open an issue](https://github.com/ArvidDeJong/ubl-peppol/issues).

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
