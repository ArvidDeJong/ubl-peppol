<?php

declare(strict_types=1);

use Darvis\UblPeppol\UblNlBis3Service;

/**
 * The Dutch builder builds a credit note (type 381) with the same calls the Belgian builder has,
 * so a host app that picks a builder per country does not end in "Call to undefined method" on a
 * Dutch credit note. <CreditNote> has its own element order in the UBL 2.1 schema.
 */

/** @var array<string, Closure(UblNlBis3Service): mixed> */
function nlCreditNoteSteps(): array
{
    return [
        'header' => fn (UblNlBis3Service $ubl) => $ubl->addCreditNoteHeader('NL-CN-2026-001', '2026-01-20'),
        'buyerReference' => fn (UblNlBis3Service $ubl) => $ubl->addBuyerReference('CLIENT-001'),
        'orderReference' => fn (UblNlBis3Service $ubl) => $ubl->addOrderReference('ORDER-2026-001'),
        'billingReference' => fn (UblNlBis3Service $ubl) => $ubl->addBillingReference('NL-INV-2026-001', '2026-01-15'),
        'documentReference' => fn (UblNlBis3Service $ubl) => $ubl->addAdditionalDocumentReference('DOC-1', 'Timesheet'),
        'supplier' => fn (UblNlBis3Service $ubl) => $ubl->addAccountingSupplierParty(
            '12345678', '0106', '12345678', 'My Dutch Company BV', 'Damrak 1', '1012 JS', 'Amsterdam', 'NL', 'NL123456789B01'
        ),
        'customer' => fn (UblNlBis3Service $ubl) => $ubl->addAccountingCustomerParty(
            '87654321', '0106', '87654321', 'Customer Company BV', 'Nieuwezijds Voorburgwal 123', '1012 RJ', 'Amsterdam', 'NL',
            null, '87654321', null, null, null, 'NL987654321B01'
        ),
        'paymentMeans' => fn (UblNlBis3Service $ubl) => $ubl->addPaymentMeans('30', 'Credit transfer', 'NL-CN-2026-001', 'NL91ABNA0417164300'),
        'allowance' => fn (UblNlBis3Service $ubl) => $ubl->addAllowanceCharge(false, 25.00, 'Discount', 'S', 21.0),
        'taxTotal' => fn (UblNlBis3Service $ubl) => $ubl->addTaxTotal([[
            'taxable_amount' => '400.00', 'tax_amount' => '84.00', 'currency' => 'EUR',
            'tax_category_id' => 'S', 'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]]),
        'monetaryTotal' => fn (UblNlBis3Service $ubl) => $ubl->addLegalMonetaryTotal([
            'line_extension_amount' => 425.00, 'tax_exclusive_amount' => 400.00, 'tax_inclusive_amount' => 484.00,
            'charge_total_amount' => 0.00, 'allowance_total_amount' => 25.00, 'prepaid_amount' => 0.00, 'payable_amount' => 484.00,
        ], 'EUR'),
        'line1' => fn (UblNlBis3Service $ubl) => $ubl->addCreditNoteLine([
            'id' => '1', 'quantity' => 5, 'unit_code' => 'HUR', 'price_amount' => 85.00, 'currency' => 'EUR',
            'name' => 'Software development', 'description' => 'Frontend development', 'tax_category_id' => 'S',
            'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]),
        'line2' => fn (UblNlBis3Service $ubl) => $ubl->addCreditNoteLine([
            'id' => '2', 'quantity' => 1, 'unit_code' => 'C62', 'price_amount' => 0.00, 'currency' => 'EUR',
            'name' => 'Second line', 'description' => 'Second line', 'tax_category_id' => 'S',
            'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]),
    ];
}

/**
 * @param  array<int, string>  $order
 */
function nlCreditNote(array $order): UblNlBis3Service
{
    $ubl = (new UblNlBis3Service)->createCreditNoteDocument();
    $steps = nlCreditNoteSteps();

    foreach ($order as $step) {
        $steps[$step]($ubl);
    }

    return $ubl;
}

/**
 * @return array<int, string>
 */
function creditNoteElements(string $xml): array
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

function creditNoteXPath(string $xml): DOMXPath
{
    $dom = new DOMDocument;
    $dom->loadXML($xml);

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('cn', 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2');
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

    return $xpath;
}

const NL_CREDIT_NOTE_SCHEMA_ORDER = [
    'header', 'buyerReference', 'orderReference', 'billingReference', 'documentReference', 'supplier', 'customer',
    'paymentMeans', 'allowance', 'taxTotal', 'monetaryTotal', 'line1', 'line2',
];

it('builds a CreditNote document of type 381 without a due date', function () {
    $xml = nlCreditNote(NL_CREDIT_NOTE_SCHEMA_ORDER)->generateXml();
    $xpath = creditNoteXPath($xml);

    expect($xpath->evaluate('string(/cn:CreditNote/cbc:CreditNoteTypeCode)'))->toBe('381')
        ->and($xpath->evaluate('string(/cn:CreditNote/cbc:ID)'))->toBe('NL-CN-2026-001')
        ->and($xpath->evaluate('string(/cn:CreditNote/cbc:IssueDate)'))->toBe('2026-01-20')
        ->and($xpath->evaluate('string(/cn:CreditNote/cbc:CustomizationID)'))->toBe('urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0')
        ->and($xpath->evaluate('string(/cn:CreditNote/cbc:ProfileID)'))->toBe('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0')
        // The CreditNote schema has no cbc:DueDate and no cbc:InvoiceTypeCode under the root.
        ->and($xpath->evaluate('count(/cn:CreditNote/cbc:DueDate)'))->toBe(0.0)
        ->and($xpath->evaluate('count(//cbc:InvoiceTypeCode)'))->toBe(0.0)
        ->and($xpath->evaluate('count(//cac:InvoiceLine)'))->toBe(0.0);
});

it('writes the elements of a credit note in the order of the CreditNote schema', function () {
    expect(creditNoteElements(nlCreditNote(NL_CREDIT_NOTE_SCHEMA_ORDER)->generateXml()))->toBe([
        'CustomizationID', 'ProfileID', 'ID', 'IssueDate', 'CreditNoteTypeCode', 'DocumentCurrencyCode',
        'BuyerReference', 'OrderReference', 'BillingReference', 'AdditionalDocumentReference',
        'AccountingSupplierParty', 'AccountingCustomerParty', 'PaymentMeans', 'AllowanceCharge',
        'TaxTotal', 'LegalMonetaryTotal', 'CreditNoteLine', 'CreditNoteLine',
    ]);
});

it('gives the same credit note whatever the order of the calls', function (array $order) {
    expect(nlCreditNote($order)->generateXml())->toBe(nlCreditNote(NL_CREDIT_NOTE_SCHEMA_ORDER)->generateXml());
})->with([
    'the order a host app uses for the Belgian builder' => [[
        'header', 'orderReference', 'billingReference', 'buyerReference', 'documentReference', 'supplier', 'customer',
        'paymentMeans', 'allowance', 'taxTotal', 'monetaryTotal', 'line1', 'line2',
    ]],
    'lines first, header last' => [[
        'line1', 'line2', 'monetaryTotal', 'taxTotal', 'allowance', 'paymentMeans', 'customer', 'supplier',
        'documentReference', 'billingReference', 'orderReference', 'buyerReference', 'header',
    ]],
]);

it('writes the billing reference to the credited invoice (BG-3, BR-55, NL-R-001)', function () {
    $xpath = creditNoteXPath(nlCreditNote(NL_CREDIT_NOTE_SCHEMA_ORDER)->generateXml());

    expect($xpath->evaluate('string(/cn:CreditNote/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID)'))->toBe('NL-INV-2026-001')
        ->and($xpath->evaluate('string(/cn:CreditNote/cac:BillingReference/cac:InvoiceDocumentReference/cbc:IssueDate)'))->toBe('2026-01-15');
});

it('leaves the issue date of the credited invoice out when it is not passed', function () {
    $ubl = nlCreditNote(['header', 'supplier', 'customer', 'taxTotal', 'monetaryTotal', 'line1']);
    $ubl->addBillingReference('NL-INV-2026-001');

    $xpath = creditNoteXPath($ubl->generateXml());

    expect($xpath->evaluate('count(//cac:InvoiceDocumentReference/cbc:IssueDate)'))->toBe(0.0)
        ->and($xpath->evaluate('string(//cac:InvoiceDocumentReference/cbc:ID)'))->toBe('NL-INV-2026-001');
});

it('refuses to generate a credit note without a billing reference', function () {
    $ubl = nlCreditNote(['header', 'supplier', 'customer', 'taxTotal', 'monetaryTotal', 'line1']);

    expect(fn () => $ubl->generateXml())->toThrow(InvalidArgumentException::class, 'NL-R-001');
});

it('reports the missing billing reference from validate() too', function () {
    $result = nlCreditNote(['header', 'supplier', 'customer', 'paymentMeans', 'taxTotal', 'monetaryTotal', 'line1'])->validate();

    expect($result->isValid())->toBeFalse()
        ->and(implode("\n", $result->errors))->toContain('BR-55');
});

it('rejects an empty billing reference', function () {
    $ubl = nlCreditNote(['header']);

    expect(fn () => $ubl->addBillingReference('  '))->toThrow(InvalidArgumentException::class);
});

it('writes a credit note line with a credited quantity', function () {
    $xpath = creditNoteXPath(nlCreditNote(NL_CREDIT_NOTE_SCHEMA_ORDER)->generateXml());

    expect($xpath->evaluate('string(//cac:CreditNoteLine[1]/cbc:ID)'))->toBe('1')
        ->and($xpath->evaluate('string(//cac:CreditNoteLine[1]/cbc:CreditedQuantity)'))->toBe('5.00')
        ->and($xpath->evaluate('string(//cac:CreditNoteLine[1]/cbc:CreditedQuantity/@unitCode)'))->toBe('HUR')
        ->and($xpath->evaluate('string(//cac:CreditNoteLine[1]/cbc:LineExtensionAmount)'))->toBe('425.00')
        ->and($xpath->evaluate('string(//cac:CreditNoteLine[1]/cac:Item/cbc:Name)'))->toBe('Software development')
        ->and($xpath->evaluate('string(//cac:CreditNoteLine[1]/cac:Item/cac:ClassifiedTaxCategory/cbc:Percent)'))->toBe('21.00')
        ->and($xpath->evaluate('string(//cac:CreditNoteLine[1]/cac:Price/cbc:PriceAmount)'))->toBe('85.00')
        ->and($xpath->evaluate('count(//cbc:InvoicedQuantity)'))->toBe(0.0);
});

it('writes the amounts of a credit note line as positive numbers, as the Belgian builder does', function () {
    // The type code 381 says it is a credit; BR-27 forbids a negative price. A host app usually
    // holds a credit note with negative amounts.
    $ubl = nlCreditNote(['header', 'billingReference', 'supplier', 'customer', 'taxTotal', 'monetaryTotal']);
    $ubl->addCreditNoteLine([
        'id' => '1', 'quantity' => 2, 'unit_code' => 'C62', 'price_amount' => -15.57, 'line_extension_amount' => -31.14,
        'currency' => 'EUR', 'name' => 'Correction', 'description' => 'Correction', 'tax_category_id' => 'S', 'tax_percent' => 21.0,
    ]);

    $xpath = creditNoteXPath($ubl->generateXml());

    expect($xpath->evaluate('string(//cac:CreditNoteLine/cbc:LineExtensionAmount)'))->toBe('31.14')
        ->and($xpath->evaluate('string(//cac:CreditNoteLine/cac:Price/cbc:PriceAmount)'))->toBe('15.57')
        ->and($xpath->evaluate('string(//cac:CreditNoteLine/cbc:CreditedQuantity)'))->toBe('2.00');
});

it('knows whether the document is a credit note', function () {
    expect((new UblNlBis3Service)->createCreditNoteDocument()->isCreditNote())->toBeTrue()
        ->and((new UblNlBis3Service)->createDocument()->isCreditNote())->toBeFalse();
});

it('refuses to mix invoice and credit note calls', function (Closure $call) {
    expect($call)->toThrow(RuntimeException::class);
})->with([
    'an invoice header on a credit note' => [fn () => (new UblNlBis3Service)->createCreditNoteDocument()->addInvoiceHeader('X-1', '2026-01-15', '2026-02-14')],
    'an invoice line on a credit note' => [fn () => (new UblNlBis3Service)->createCreditNoteDocument()->addInvoiceLine([
        'id' => '1', 'quantity' => 1, 'unit_code' => 'C62', 'price_amount' => 1.00, 'currency' => 'EUR', 'name' => 'A', 'description' => 'A',
    ])],
    'a credit note header on an invoice' => [fn () => (new UblNlBis3Service)->createDocument()->addCreditNoteHeader('X-1', '2026-01-15')],
    'a credit note line on an invoice' => [fn () => (new UblNlBis3Service)->createDocument()->addCreditNoteLine([
        'id' => '1', 'quantity' => 1, 'unit_code' => 'C62', 'price_amount' => 1.00, 'currency' => 'EUR', 'name' => 'A', 'description' => 'A',
    ])],
    'a billing reference before the document exists' => [fn () => (new UblNlBis3Service)->addBillingReference('X-1')],
    'a second document' => [fn () => (new UblNlBis3Service)->createDocument()->createCreditNoteDocument()],
]);

it('rejects a credit note header with a bad number or date', function (string $number, string $date) {
    $ubl = (new UblNlBis3Service)->createCreditNoteDocument();

    expect(fn () => $ubl->addCreditNoteHeader($number, $date))->toThrow(InvalidArgumentException::class);
})->with([
    'no number' => ['', '2026-01-15'],
    'a number of more than 35 characters' => [str_repeat('9', 36), '2026-01-15'],
    'a date in another format' => ['NL-CN-1', '15-01-2026'],
    'a date in the future' => ['NL-CN-1', '2999-01-01'],
]);
