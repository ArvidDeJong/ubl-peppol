<?php

declare(strict_types=1);

use Darvis\UblPeppol\UblNlBis3Service;

/**
 * Values the Dutch builder writes into the document. Each test names the business term (BT) and the
 * rule of PEPPOL BIS Billing 3.0 it guards: https://docs.peppol.eu/poacc/billing/3.0/bis/
 */
function nlXpath(string $xml): DOMXPath
{
    $dom = new DOMDocument;
    $dom->loadXML($xml);

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

    return $xpath;
}

function nlBuilder(): UblNlBis3Service
{
    return (new UblNlBis3Service)
        ->createDocument()
        ->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14')
        ->addBuyerReference('CLIENT-001');
}

function nlSupplier(UblNlBis3Service $ubl, string $companyId = 'NL123456789B01'): UblNlBis3Service
{
    return $ubl->addAccountingSupplierParty(
        '12345678', '0106', '12345678', 'My Dutch Company BV', 'Damrak 1', '1012 JS', 'Amsterdam', 'NL', $companyId
    );
}

function nlCustomer(UblNlBis3Service $ubl, string $country = 'NL', ?string $companyId = '87654321'): UblNlBis3Service
{
    return $ubl->addAccountingCustomerParty(
        '87654321', '0106', '87654321', 'Customer Company BV', 'Nieuwezijds Voorburgwal 123', '1012 RJ', 'Amsterdam', $country,
        null, $companyId
    );
}

const NL_SUPPLIER_LEGAL = '/i:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity';
const NL_CUSTOMER_LEGAL = '/i:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity';

// BT-19, Buyer accounting reference, cardinality 0..1

it('does not write an accounting cost nobody asked for (BT-19)', function () {
    expect(nlBuilder()->generateXml())->not->toContain('AccountingCost');
});

it('writes the accounting cost a caller passes, between the currency and the buyer reference (BT-19)', function () {
    $xml = nlBuilder()->addAccountingCost('PROJECT-7')->generateXml();
    $xpath = nlXpath($xml);

    expect($xpath->evaluate('string(/i:Invoice/cbc:AccountingCost)'))->toBe('PROJECT-7')
        ->and($xpath->evaluate('name(/i:Invoice/cbc:AccountingCost/preceding-sibling::*[1])'))->toBe('cbc:DocumentCurrencyCode')
        ->and($xpath->evaluate('name(/i:Invoice/cbc:AccountingCost/following-sibling::*[1])'))->toBe('cbc:BuyerReference');
});

it('keeps one accounting cost when it is set twice, and refuses an empty one (BT-19)', function () {
    $xml = nlBuilder()->addAccountingCost('FIRST')->addAccountingCost('SECOND')->generateXml();

    expect(substr_count($xml, '<cbc:AccountingCost>'))->toBe(1)
        ->and($xml)->toContain('<cbc:AccountingCost>SECOND</cbc:AccountingCost>');

    expect(fn () => nlBuilder()->addAccountingCost(' '))->toThrow(InvalidArgumentException::class);
});

// BT-27, Seller name

it('writes the name of the supplier as its registration name (BT-27)', function () {
    $xpath = nlXpath(nlSupplier(nlBuilder())->generateXml());

    expect($xpath->evaluate('string('.NL_SUPPLIER_LEGAL.'/cbc:RegistrationName)'))->toBe('My Dutch Company BV');
});

// BT-30, Seller legal registration identifier: NL-R-003 wants a KvK or OIN number, scheme 0106 or 0190

it('does not write the VAT number of the supplier as a KvK number (BT-30, NL-R-003)', function () {
    $xml = nlSupplier(nlBuilder())->generateXml();
    $xpath = nlXpath($xml);

    expect($xpath->evaluate('count('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID)'))->toBe(0.0)
        // BT-31 stays where it was
        ->and($xpath->evaluate('string(/i:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID)'))->toBe('NL123456789B01');
});

it('writes the registration number of the supplier a caller passes, before or after the party (BT-30)', function () {
    $after = nlXpath(nlSupplier(nlBuilder())->addSupplierLegalRegistration('12345678')->generateXml());
    $before = nlXpath(nlSupplier(nlBuilder()->addSupplierLegalRegistration('00000001234567890123', '0190'))->generateXml());

    expect($after->evaluate('string('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID)'))->toBe('12345678')
        ->and($after->evaluate('string('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID/@schemeID)'))->toBe('0106')
        ->and($after->evaluate('name('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID/preceding-sibling::*[1])'))->toBe('cbc:RegistrationName')
        ->and($before->evaluate('string('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID)'))->toBe('00000001234567890123')
        ->and($before->evaluate('string('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID/@schemeID)'))->toBe('0190')
        ->and($before->evaluate('count('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID)'))->toBe(1.0);
});

it('still writes an eight digit company id of the supplier under scheme 0106, as before (BT-30)', function () {
    $xpath = nlXpath(nlSupplier(nlBuilder(), '12345678')->generateXml());

    expect($xpath->evaluate('string('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID)'))->toBe('12345678')
        ->and($xpath->evaluate('string('.NL_SUPPLIER_LEGAL.'/cbc:CompanyID/@schemeID)'))->toBe('0106');
});

it('refuses a registration without a number or with a scheme that is not an ICD code (BR-CL-11)', function (string $id, string $scheme) {
    expect(fn () => nlBuilder()->addSupplierLegalRegistration($id, $scheme))->toThrow(InvalidArgumentException::class)
        ->and(fn () => nlBuilder()->addCustomerLegalRegistration($id, $scheme))->toThrow(InvalidArgumentException::class);
})->with([
    'empty number' => ['', '0106'],
    'scheme with letters' => ['12345678', 'KVK'],
]);

// BT-47, Buyer legal registration identifier: NL-R-005 applies to a Dutch customer only

it('keeps the KvK number of a Dutch customer under scheme 0106, as before (BT-47, NL-R-005)', function () {
    $xpath = nlXpath(nlCustomer(nlBuilder())->generateXml());

    expect($xpath->evaluate('string('.NL_CUSTOMER_LEGAL.'/cbc:CompanyID)'))->toBe('87654321')
        ->and($xpath->evaluate('string('.NL_CUSTOMER_LEGAL.'/cbc:CompanyID/@schemeID)'))->toBe('0106');
});

it('does not call the registration number of a foreign customer a KvK number (BT-47)', function () {
    $xpath = nlXpath(nlCustomer(nlBuilder(), 'BE', '0999000228')->generateXml());

    expect($xpath->evaluate('string('.NL_CUSTOMER_LEGAL.'/cbc:CompanyID)'))->toBe('0999000228')
        ->and($xpath->evaluate('count('.NL_CUSTOMER_LEGAL.'/cbc:CompanyID/@schemeID)'))->toBe(0.0);
});

it('writes the scheme a caller passes for the customer (BT-47)', function () {
    $xpath = nlXpath(nlCustomer(nlBuilder(), 'BE', null)->addCustomerLegalRegistration('0999000228', '0208')->generateXml());

    expect($xpath->evaluate('string('.NL_CUSTOMER_LEGAL.'/cbc:CompanyID)'))->toBe('0999000228')
        ->and($xpath->evaluate('string('.NL_CUSTOMER_LEGAL.'/cbc:CompanyID/@schemeID)'))->toBe('0208');
});

it('does not write a VAT number of the customer as its registration number (BT-47)', function () {
    $xpath = nlXpath(nlCustomer(nlBuilder(), 'NL', 'NL987654321B01')->generateXml());

    expect($xpath->evaluate('count('.NL_CUSTOMER_LEGAL.'/cbc:CompanyID)'))->toBe(0.0);
});

// BT-107 and BT-113 in the monetary total: BR-CO-13 and BR-CO-16 cannot hold without them

it('writes the allowance total and the prepaid amount in schema order (BT-107, BT-113)', function () {
    $xml = nlBuilder()->addLegalMonetaryTotal([
        'line_extension_amount' => 425.00,
        'tax_exclusive_amount' => 400.00,
        'tax_inclusive_amount' => 484.00,
        'allowance_total_amount' => 25.00,
        'charge_total_amount' => 0.00,
        'prepaid_amount' => 100.00,
        'payable_amount' => 384.00,
    ], 'EUR')->generateXml();

    $xpath = nlXpath($xml);
    $names = [];
    foreach ($xpath->query('/i:Invoice/cac:LegalMonetaryTotal/*') as $node) {
        $names[] = $node->nodeName.'='.$node->textContent;
    }

    expect($names)->toBe([
        'cbc:LineExtensionAmount=425.00',
        'cbc:TaxExclusiveAmount=400.00',
        'cbc:TaxInclusiveAmount=484.00',
        'cbc:AllowanceTotalAmount=25.00',
        'cbc:ChargeTotalAmount=0.00',
        'cbc:PrepaidAmount=100.00',
        'cbc:PayableAmount=384.00',
    ])->and($xpath->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:PrepaidAmount/@currencyID)'))->toBe('EUR');
});

it('writes the same monetary total as before without an allowance or a prepayment', function (array $extra) {
    $xml = nlBuilder()->addLegalMonetaryTotal([
        'line_extension_amount' => 425.00,
        'tax_exclusive_amount' => 425.00,
        'tax_inclusive_amount' => 514.25,
        'charge_total_amount' => 0.00,
        'payable_amount' => 514.25,
    ] + $extra, 'EUR')->generateXml();

    expect($xml)->not->toContain('AllowanceTotalAmount')
        ->and($xml)->not->toContain('PrepaidAmount')
        ->and($xml)->toContain('<cbc:ChargeTotalAmount currencyID="EUR">0.00</cbc:ChargeTotalAmount>');
})->with([
    'keys left out' => [[]],
    'keys passed as zero' => [['allowance_total_amount' => 0.00, 'prepaid_amount' => 0.00]],
]);

// BT-95 and BT-102: BR-32 and BR-37 want a VAT category on every document level allowance and charge

it('writes the VAT category of a zero rated or exempt allowance (BT-95, BR-32)', function (string $category) {
    $xpath = nlXpath(nlBuilder()->addAllowanceCharge(false, 10.00, 'Discount', $category, 0.0, 'EUR')->generateXml());

    expect($xpath->evaluate('string(/i:Invoice/cac:AllowanceCharge/cac:TaxCategory/cbc:ID)'))->toBe($category)
        ->and($xpath->evaluate('string(/i:Invoice/cac:AllowanceCharge/cac:TaxCategory/cbc:Percent)'))->toBe('0.00')
        ->and($xpath->evaluate('string(/i:Invoice/cac:AllowanceCharge/cac:TaxCategory/cac:TaxScheme/cbc:ID)'))->toBe('VAT');
})->with(['Z', 'E', 'AE']);

it('writes no rate for a charge that is not subject to VAT (BT-103, BR-O-07)', function () {
    $xpath = nlXpath(nlBuilder()->addAllowanceCharge(true, 10.00, 'Freight', 'O', 0.0, 'EUR')->generateXml());

    expect($xpath->evaluate('string(/i:Invoice/cac:AllowanceCharge/cac:TaxCategory/cbc:ID)'))->toBe('O')
        ->and($xpath->evaluate('count(/i:Invoice/cac:AllowanceCharge/cac:TaxCategory/cbc:Percent)'))->toBe(0.0);
});

// The guard the docblock of generateXml() promises

it('throws a RuntimeException when the document was never created', function () {
    (new UblNlBis3Service)->generateXml();
})->throws(RuntimeException::class, 'Root element is not initialized. Call createDocument() before adding elements.');
