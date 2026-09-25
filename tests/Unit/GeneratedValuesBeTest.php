<?php

declare(strict_types=1);

use Darvis\UblPeppol\UblBeBis3Service;

/**
 * Values the Belgian builder writes and checks. Each test names the business term (BT) and the rule
 * of PEPPOL BIS Billing 3.0 it guards: https://docs.peppol.eu/poacc/billing/3.0/bis/
 */
function beChildNames(string $xml): array
{
    $dom = new DOMDocument;
    $dom->loadXML($xml);

    $names = [];
    foreach ($dom->documentElement->childNodes as $node) {
        if ($node instanceof DOMElement) {
            $names[] = $node->nodeName;
        }
    }

    return $names;
}

function beLine(array $overrides = []): array
{
    return $overrides + [
        'id' => '1', 'quantity' => 2, 'unit_code' => 'C62', 'price_amount' => 100.00, 'currency' => 'EUR',
        'name' => 'Consultancy', 'description' => 'IT consultancy, 2 days', 'tax_category_id' => 'S',
        'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
    ];
}

function beInvoice(): UblBeBis3Service
{
    return (new UblBeBis3Service)
        ->createDocument()
        ->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14');
}

// BT-19, Buyer accounting reference, cardinality 0..1

it('does not write an accounting cost nobody asked for (BT-19)', function () {
    expect(beInvoice()->generateXml())->not->toContain('AccountingCost');
});

it('puts the accounting cost behind the currency, also when the buyer reference is already there (BT-19)', function () {
    $names = beChildNames(beInvoice()->addBuyerReference('CLIENT-001')->addOrderReference('PO-1')->addAccountingCost('PROJECT-7')->generateXml());

    expect(array_slice($names, 6))->toBe(['cbc:DocumentCurrencyCode', 'cbc:AccountingCost', 'cbc:BuyerReference', 'cac:OrderReference']);
});

it('writes the accounting cost of a credit note in the same place (BT-19)', function () {
    $xml = (new UblBeBis3Service)
        ->createCreditNoteDocument()
        ->addCreditNoteHeader('CN-2026-001', '2026-01-21')
        ->addOrderReference('PO-1')
        ->addAccountingCost('PROJECT-7')
        ->addBillingReference('INV-2026-001', '2026-01-15')
        ->generateXml();

    expect(array_slice(beChildNames($xml), 5))->toBe(['cbc:DocumentCurrencyCode', 'cbc:AccountingCost', 'cac:OrderReference', 'cac:BillingReference'])
        ->and($xml)->toContain('<cbc:AccountingCost>PROJECT-7</cbc:AccountingCost>');
});

it('keeps one accounting cost, refuses an empty one and wants the header first (BT-19)', function () {
    $xml = beInvoice()->addAccountingCost('FIRST')->addAccountingCost('SECOND')->generateXml();

    expect(substr_count($xml, '<cbc:AccountingCost>'))->toBe(1)
        ->and($xml)->toContain('<cbc:AccountingCost>SECOND</cbc:AccountingCost>');

    expect(fn () => beInvoice()->addAccountingCost(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new UblBeBis3Service)->createDocument()->addAccountingCost('X'))->toThrow(RuntimeException::class);
});

// BR-CO-11, BR-CO-12 and BR-S-08: document level allowances and charges are part of the totals

it('accepts a correct invoice with a document level charge (BT-99, BR-CO-12, BR-S-08)', function () {
    $ubl = beInvoice()
        ->addAllowanceCharge(true, 10.00, 'Freight', 'S', 21.0, 'EUR')
        ->addInvoiceLine(beLine())
        ->addTaxTotal([[
            'taxable_amount' => 210.00, 'tax_amount' => 44.10, 'currency' => 'EUR',
            'tax_category_id' => 'S', 'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]])
        ->addLegalMonetaryTotal([
            'line_extension_amount' => 200.00, 'tax_exclusive_amount' => 210.00, 'tax_inclusive_amount' => 254.10,
            'charge_total_amount' => 10.00, 'payable_amount' => 254.10,
        ], 'EUR');

    $result = $ubl->validate();

    expect($result->errors)->toBe([])
        ->and($result->isValid())->toBeTrue();
});

it('accepts a correct invoice with a document level discount (BT-92, BR-CO-11, BR-S-08)', function () {
    $result = beInvoice()
        ->addAllowanceCharge(false, 20.00, 'Discount', 'S', 21.0, 'EUR')
        ->addInvoiceLine(beLine())
        ->addTaxTotal([[
            'taxable_amount' => 180.00, 'tax_amount' => 37.80, 'currency' => 'EUR',
            'tax_category_id' => 'S', 'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]])
        ->addLegalMonetaryTotal([
            'line_extension_amount' => 200.00, 'tax_exclusive_amount' => 180.00, 'tax_inclusive_amount' => 217.80,
            'allowance_total_amount' => 20.00, 'charge_total_amount' => 0.00, 'payable_amount' => 217.80,
        ], 'EUR')
        ->validate();

    expect($result->errors)->toBe([]);
});

it('still reports a charge total that does not match the charges, and keeps the charge in the suggestion (BR-CO-12)', function () {
    $result = beInvoice()
        ->addAllowanceCharge(true, 10.00, 'Freight', 'S', 21.0, 'EUR')
        ->addInvoiceLine(beLine())
        ->addTaxTotal([[
            'taxable_amount' => 210.00, 'tax_amount' => 44.10, 'currency' => 'EUR',
            'tax_category_id' => 'S', 'tax_percent' => 21.0, 'tax_scheme_id' => 'VAT',
        ]])
        ->addLegalMonetaryTotal([
            'line_extension_amount' => 200.00, 'tax_exclusive_amount' => 215.00, 'tax_inclusive_amount' => 259.10,
            'charge_total_amount' => 15.00, 'payable_amount' => 259.10,
        ], 'EUR')
        ->validate();

    expect($result->isValid())->toBeFalse()
        ->and($result->getErrorsAsString())->toContain('BR-CO-12: Sum of document charges (10.00) does not match ChargeTotalAmount (15.00)')
        ->and($result->getCorrection('charge_total_amount'))->toBe(10.0)
        ->and($result->getCorrection('tax_exclusive_amount'))->toBe(210.0);
});

// Smaller things

it('writes VAT as the tax scheme of a line that does not name one (BT-151)', function () {
    $line = beLine();
    unset($line['tax_scheme_id']);

    $xml = beInvoice()->addInvoiceLine($line)->generateXml();

    expect($xml)->toContain("<cac:TaxScheme>\n          <cbc:ID>VAT</cbc:ID>");
});

it('writes a charge total of zero when the key is left out, without a PHP warning (BT-108)', function () {
    $xml = beInvoice()->addLegalMonetaryTotal([
        'line_extension_amount' => 200.00, 'tax_exclusive_amount' => 200.00, 'tax_inclusive_amount' => 242.00, 'payable_amount' => 242.00,
    ], 'EUR')->generateXml();

    expect($xml)->toContain('<cbc:ChargeTotalAmount currencyID="EUR">0.00</cbc:ChargeTotalAmount>');
});

it('accepts a VAT number of the customer in lower case and writes it in upper case (BT-48, BR-CO-09)', function () {
    $xml = beInvoice()->addAccountingCustomerParty(
        '0999000228', '0208', '0999000228', 'Customer Company NV', 'Kerkstraat 123', '2000', 'Antwerpen', 'BE',
        null, '0999000228', null, null, null, 'be0999000228'
    )->generateXml();

    expect($xml)->toContain('<cbc:CompanyID>BE0999000228</cbc:CompanyID>');
});

it('throws the documented error when generateXml() runs before createDocument(), as the Dutch builder does', function () {
    expect(fn () => (new UblBeBis3Service)->generateXml())
        ->toThrow(RuntimeException::class, 'Root element is not initialized. Call createDocument() before adding elements.');
});

it('counts document level discounts and charges in calculateTotals(), so the calculated totals pass validate() (BR-CO-13, BR-S-08)', function () {
    $ubl = beInvoice()
        ->addAllowanceCharge(false, 20.00, 'Discount', 'S', 21.0, 'EUR')
        ->addAllowanceCharge(true, 10.00, 'Freight', 'S', 21.0, 'EUR')
        ->addInvoiceLine(beLine());

    $calculated = $ubl->calculateTotals();

    expect($calculated['totals'])->toMatchArray([
        'line_extension_amount' => 200.00,
        'allowance_total_amount' => 20.00,
        'charge_total_amount' => 10.00,
        'tax_exclusive_amount' => 190.00,
        'tax_inclusive_amount' => 229.90,
        'payable_amount' => 229.90,
    ])->and($calculated['tax_totals'][0]['taxable_amount'])->toBe(190.00);

    $ubl->addTaxTotal($calculated['tax_totals'])->addLegalMonetaryTotal($calculated['totals'], 'EUR');

    expect($ubl->validate()->errors)->toBe([]);
});

it('gives the registration of a customer outside the Netherlands and Belgium no scheme, instead of the Belgian 0208 (BT-47)', function (string $country, ?string $scheme) {
    $xml = beInvoice()->addAccountingCustomerParty(
        '0999000228', '0208', '0999000228', 'Customer', 'Street 1', '1000', 'City', $country, null, 'REG-123'
    )->generateXml();

    $dom = new DOMDocument;
    $dom->loadXML($xml);
    $companyId = $dom->getElementsByTagNameNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'CompanyID');
    $registration = null;
    foreach ($companyId as $node) {
        if ($node->parentNode?->localName === 'PartyLegalEntity') {
            $registration = $node;
        }
    }

    expect($registration?->textContent)->toBe('REG-123')
        ->and($registration?->hasAttribute('schemeID') ? $registration->getAttribute('schemeID') : null)->toBe($scheme);
})->with([
    'Belgium, as before' => ['BE', '0208'],
    'the Netherlands, as before' => ['NL', '0106'],
    'Germany' => ['DE', null],
]);
