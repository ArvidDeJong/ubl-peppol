<?php

declare(strict_types=1);

use Darvis\UblPeppol\UblNlBis3Service;

/**
 * The UBL schema fixes the order of the elements under <Invoice>. A receiver rejects a document
 * with the right values in the wrong order, so the builder puts them in schema order itself and
 * the order of the add...() calls does not matter.
 */

/** @var array<string, Closure(UblNlBis3Service): mixed> */
function nlInvoiceSteps(): array
{
    return [
        'header' => fn (UblNlBis3Service $ubl) => $ubl->addInvoiceHeader('NL-INV-2026-001', '2026-01-15', '2026-02-14'),
        'buyerReference' => fn (UblNlBis3Service $ubl) => $ubl->addBuyerReference('CLIENT-001'),
        'orderReference' => fn (UblNlBis3Service $ubl) => $ubl->addOrderReference('ORDER-2026-001'),
        'supplier' => fn (UblNlBis3Service $ubl) => $ubl->addAccountingSupplierParty(
            'NL123456789B01', '0106', '12345678', 'My Dutch Company BV', 'Damrak 1', '1012 JS', 'Amsterdam', 'NL', 'NL123456789B01'
        ),
        'customer' => fn (UblNlBis3Service $ubl) => $ubl->addAccountingCustomerParty(
            '87654321', '0106', '87654321', 'Customer Company BV', 'Nieuwezijds Voorburgwal 123', '1012 RJ', 'Amsterdam', 'NL',
            null, '87654321', null, null, null, 'NL987654321B01'
        ),
        'paymentTerms' => fn (UblNlBis3Service $ubl) => $ubl->addPaymentTerms('Payment within 30 days'),
        'taxTotal' => fn (UblNlBis3Service $ubl) => $ubl->addTaxTotal([[
            'taxable_amount' => '425.00', 'tax_amount' => '89.25', 'currency' => 'EUR',
            'tax_category_id' => 'S', 'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]]),
        'monetaryTotal' => fn (UblNlBis3Service $ubl) => $ubl->addLegalMonetaryTotal([
            'line_extension_amount' => 425.00, 'tax_exclusive_amount' => 425.00, 'tax_inclusive_amount' => 514.25,
            'charge_total_amount' => 0.00, 'allowance_total_amount' => 0.00, 'prepaid_amount' => 0.00, 'payable_amount' => 514.25,
        ], 'EUR'),
        'line1' => fn (UblNlBis3Service $ubl) => $ubl->addInvoiceLine([
            'id' => '1', 'quantity' => 5, 'unit_code' => 'HUR', 'price_amount' => 85.00, 'currency' => 'EUR',
            'name' => 'Software development', 'description' => 'Frontend development', 'tax_category_id' => 'S',
            'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]),
        'line2' => fn (UblNlBis3Service $ubl) => $ubl->addInvoiceLine([
            'id' => '2', 'quantity' => 1, 'unit_code' => 'C62', 'price_amount' => 0.00, 'currency' => 'EUR',
            'name' => 'Second line', 'description' => 'Second line', 'tax_category_id' => 'S',
            'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]),
    ];
}

/**
 * @param  array<int, string>  $order
 */
function buildNlInvoice(array $order): string
{
    $ubl = (new UblNlBis3Service)->createDocument();
    $steps = nlInvoiceSteps();

    foreach ($order as $step) {
        $steps[$step]($ubl);
    }

    return $ubl->generateXml();
}

/**
 * @return array<int, string>
 */
function topLevelElements(string $xml): array
{
    $dom = new DOMDocument;
    $dom->loadXML($xml);

    $names = [];
    foreach ($dom->documentElement->childNodes as $node) {
        if ($node instanceof DOMElement) {
            $names[] = $node->localName;
        }
    }

    return $names;
}

const NL_SCHEMA_ORDER = ['header', 'buyerReference', 'orderReference', 'supplier', 'customer', 'paymentTerms', 'taxTotal', 'monetaryTotal', 'line1', 'line2'];

const NL_EXPECTED_ELEMENTS = [
    'CustomizationID', 'ProfileID', 'ID', 'IssueDate', 'DueDate', 'InvoiceTypeCode', 'DocumentCurrencyCode',
    'BuyerReference', 'OrderReference', 'AccountingSupplierParty', 'AccountingCustomerParty', 'PaymentTerms',
    'TaxTotal', 'LegalMonetaryTotal', 'InvoiceLine', 'InvoiceLine',
];

it('writes the elements in schema order when the calls follow that order', function () {
    $elements = topLevelElements(buildNlInvoice(NL_SCHEMA_ORDER));

    expect($elements)->toBe(NL_EXPECTED_ELEMENTS);
});

it('gives the same document whatever the order of the calls', function (array $order) {
    expect(buildNlInvoice($order))->toBe(buildNlInvoice(NL_SCHEMA_ORDER));
})->with([
    'lines before the totals, as the docs used to show' => [['header', 'buyerReference', 'orderReference', 'supplier', 'customer', 'paymentTerms', 'line1', 'line2', 'taxTotal', 'monetaryTotal']],
    'totals first' => [['monetaryTotal', 'taxTotal', 'header', 'supplier', 'customer', 'buyerReference', 'orderReference', 'paymentTerms', 'line1', 'line2']],
    'customer before supplier, header last' => [['customer', 'supplier', 'line1', 'taxTotal', 'line2', 'monetaryTotal', 'paymentTerms', 'orderReference', 'buyerReference', 'header']],
]);

it('keeps the invoice lines in the order they were added', function () {
    $xml = buildNlInvoice(['line1', 'header', 'line2', 'supplier', 'customer', 'taxTotal', 'monetaryTotal']);

    expect(strpos($xml, 'Software development'))->toBeLessThan(strpos($xml, 'Second line'));
});

it('sorts only once, so generating twice gives the same document', function () {
    $ubl = (new UblNlBis3Service)->createDocument();
    foreach (['line1', 'header', 'supplier', 'customer', 'taxTotal', 'monetaryTotal'] as $step) {
        nlInvoiceSteps()[$step]($ubl);
    }

    expect($ubl->generateXml())->toBe($ubl->generateXml());
});
