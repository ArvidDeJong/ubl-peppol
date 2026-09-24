---
title: "VAT categories"
nav_order: 9
description: "Which VAT category a PEPPOL invoice line gets (S, Z, E, AE, K, G, O), what intra-community supply and reverse charge demand, and the VatCategory knowledge base."
---

# VAT categories

Every line, every document level discount or charge and every row of the VAT breakdown carries a VAT category: the `tax_category_id` you pass to the builders. The category tells the receiver why a line has the VAT it has, and each category comes with its own rules. A category that fits the amounts but not the reason is still wrong: the receiver's bookkeeping takes it as fact.

Choosing the category is a tax decision. This page explains the options; the bookkeeper of the seller decides which one applies.

## The categories

| Code | Name | Use it when | Rate | Exemption reason |
| --- | --- | --- | --- | --- |
| `S` | Standard rate | You charge VAT, at any rate above zero (21%, 9%, 12%, 6%) | above 0 | not allowed |
| `Z` | Zero rated goods | The supply is taxed at 0% by law. Rare in the Netherlands and Belgium | 0 | not allowed |
| `E` | Exempt from tax | The law exempts the supply, such as medical care, education or financial services | 0 | required, you choose it |
| `AE` | Reverse charge | The buyer accounts for the VAT: a service to a business in another EU country, or a domestic scheme such as subcontracting | 0 | required, `VATEX-EU-AE` by default |
| `K` | Intra-community supply | A supply to a VAT registered business in another EU or EEA country | 0 | required, `VATEX-EU-IC` by default |
| `G` | Export outside the EU | Goods leave the EU, for example to the United Kingdom or Switzerland | 0 | required, `VATEX-EU-G` by default |
| `O` | Not subject to VAT | The transaction falls outside VAT altogether. Nothing else may be on the document | none | required, `VATEX-EU-O` by default |
| `L` | Canary Islands (IGIC) | Spanish tax of the Canary Islands, in place of VAT | 0 or more | not allowed |
| `M` | Ceuta and Melilla (IPSI) | Spanish tax of Ceuta and Melilla, in place of VAT | 0 or more | not allowed |

The Italian split payment (`B`) is not in the list: EN 16931 allows it for domestic Italian invoices only.

## Reverse charge or intra-community supply?

Both mean the invoice shows no VAT and the buyer deals with it in their own country. The difference is the reason, and what the receiver then expects on the invoice.

| | `AE` reverse charge | `K` intra-community supply |
| --- | --- | --- |
| Typical case | A service to a business abroad, or a domestic reverse charge scheme | Goods or services to a VAT registered business in another EU country |
| Standard invoice text | "Reverse charge", in Dutch "Btw verlegd" | "Intra-community supply", in Dutch "Intracommunautaire levering" |
| VAT numbers | Seller, and the buyer's VAT number or legal registration (BR-AE-02) | Seller and buyer (BR-IC-02) |
| Delivery | Not needed | Delivery date (BR-IC-11) and the country the goods go to (BR-IC-12) |

## Exemption reasons

A category that is not taxed at the normal rate needs a reason in the VAT breakdown: a code (BT-121) from the [VATEX code list](https://docs.peppol.eu/poacc/billing/3.0/codelist/vatex/), a text (BT-120), or both. Pass them in the entry of `addTaxTotal()`:

```php
$ubl->addTaxTotal([[
    'taxable_amount' => 1000.00,
    'tax_amount' => 0.00,
    'currency' => 'EUR',
    'tax_category_id' => 'K',
    'tax_percent' => 0.0,
    'tax_scheme_id' => 'VAT',
    'tax_exemption_reason_code' => 'VATEX-EU-IC',                  // optional for K, AE, G and O
    'tax_exemption_reason' => 'Intracommunautaire levering',       // optional, any language
]]);
```

What the builders do with it:

- **`K`, `AE`, `G` or `O` without a reason** get the one code that belongs to the category (`VATEX-EU-IC`, `VATEX-EU-AE`, `VATEX-EU-G`, `VATEX-EU-O`). That is enough for BR-IC-10, BR-AE-10, BR-G-10 and BR-O-10.
- **`E` without a reason** is written as it is, and `validate()` reports `[BR-E-10]`. The code names the article of the VAT directive, such as `VATEX-EU-132-1C` for medical care, and only the seller knows which one applies.
- **A reason on `S`, `Z`, `L` or `M`** throws an `InvalidArgumentException` that starts with the rule, for example `[BR-S-10]`.
- **A code outside the VATEX list** throws `[BR-CL-22]`. **A code of another category**, such as `VATEX-EU-IC` on an `AE` breakdown, throws as well: PEPPOL-EN16931-P0104 to P0111 tie those codes to one category.

The text is free. The rules accept the standard text in any language, so a Dutch invoice can say "Btw verlegd".

## What validate() checks

`validate()` of both builders reads the finished document, the way a receiver does, and checks what the categories demand. Every message starts with the rule code and says what to pass to which method.

| Check | Rules |
| --- | --- |
| Every category of a line, discount or charge is in the VAT breakdown; once, except `S` | BR-S-01, BR-IC-01, BR-AE-01, ... |
| The rate fits: above 0 for `S`, 0 for `Z`, `E`, `AE`, `K` and `G` | BR-S-05 to 07, BR-IC-05 to 07, ... |
| No VAT is charged in the breakdown of a category without VAT | BR-IC-09, BR-AE-09, ... |
| The VAT numbers are there: the seller's for every category, the buyer's for `K`, the buyer's or their registration for `AE` | BR-S-02, BR-IC-02, BR-AE-02, ... |
| An exemption reason for `E` | BR-E-10 |
| The delivery date and country for `K` | BR-IC-11, BR-IC-12 |
| `O` stands alone and carries no VAT numbers | BR-O-02, BR-O-11 |

For `K` call `addDelivery()` with the date and the country. The Dutch builder writes a country passed without an address too: `addDelivery('2026-01-14', countryCode: 'BE')`.

## What the builders do for you

- Write the exemption reason code of `K`, `AE`, `G` and `O` when you pass none.
- Leave out the VAT rate of category `O` on lines, discounts, charges and the breakdown (BR-O-05 to BR-O-07).
- Refuse a reason or a code that does not fit the category the moment you call `addTaxTotal()`.

A known limit: both builders always write the seller's VAT number, and category `O` forbids it (BR-O-02). A seller who is not a VAT payer cannot build a document with this package yet; `validate()` says so.

## The knowledge base in code

`Darvis\UblPeppol\Vat\VatCategory` holds everything on this page, so a host app can show it next to a select list or check a choice before it builds a document.

```php
use Darvis\UblPeppol\Vat\VatCategory;
use Darvis\UblPeppol\Vat\VatExemptionReason;

$category = VatCategory::fromCode('K');          // VatCategory::IntraCommunitySupply, null for an unknown code

$category->label();                               // 'VAT exempt for EEA intra-community supply of goods and services'
$category->description();                         // when to use it, in plain words
$category->requiresExemptionReason();             // true
$category->defaultExemptionReasonCode();          // 'VATEX-EU-IC'
$category->exemptionReasonText('nl');             // 'Intracommunautaire levering' ('en', 'nl' or 'fr')
$category->requiresZeroRate();                    // true
$category->rules();                               // ['BR-IC-02' => '...', 'BR-IC-11' => '...', ...]

VatCategory::guide('nl');                         // all of the above for every category, as arrays

VatExemptionReason::name('VATEX-EU-132-1C');      // 'Exempt based on article 132, section 1 (c) of Council Directive 2006/112/EC'
VatExemptionReason::categoryOf('VATEX-EU-IC');    // VatCategory::IntraCommunitySupply
VatExemptionReason::isKnown('VATEX-EU-XYZ');      // false
VatExemptionReason::codes();                      // every code of the list
```

The names, the rules and the codes come from the official [UNCL5305](https://docs.peppol.eu/poacc/billing/3.0/codelist/UNCL5305/) and [VATEX](https://docs.peppol.eu/poacc/billing/3.0/codelist/vatex/) code lists and the EN 16931 Schematron. The line and the breakdown carry the same category: pass the same `tax_category_id` to `addInvoiceLine()` and `addTaxTotal()`.
