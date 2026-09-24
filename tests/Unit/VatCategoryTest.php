<?php

declare(strict_types=1);

use Darvis\UblPeppol\UblBeBis3Service;
use Darvis\UblPeppol\UblNlBis3Service;
use Darvis\UblPeppol\Validation\UblValidator;
use Darvis\UblPeppol\Vat\VatCategory;
use Darvis\UblPeppol\Vat\VatExemptionReason;

/**
 * The VAT categories (UNCL5305) and exemption reasons (VATEX, BT-120 and BT-121). Each test names the
 * EN 16931 or PEPPOL rule it guards: https://docs.peppol.eu/poacc/billing/3.0/bis/
 */
function vatTax(string $category, float $percent = 0.0, array $extra = []): array
{
    return $extra + [
        'taxable_amount' => 200.00, 'tax_amount' => round(200 * $percent / 100, 2), 'currency' => 'EUR',
        'tax_category_id' => $category, 'tax_percent' => $percent, 'tax_scheme_id' => 'VAT',
    ];
}

function vatNl(): UblNlBis3Service
{
    return (new UblNlBis3Service)
        ->createDocument()
        ->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14');
}

function vatBe(string $category = 'K'): UblBeBis3Service
{
    return (new UblBeBis3Service)
        ->createDocument()
        ->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14')
        ->addInvoiceLine([
            'id' => '1', 'quantity' => 2, 'unit_code' => 'C62', 'price_amount' => 100.00, 'currency' => 'EUR',
            'name' => 'Powder coating', 'description' => 'Coating of 2 frames', 'tax_category_id' => $category,
            'tax_percent' => 0.0, 'tax_scheme_id' => 'VAT',
        ]);
}

function vatBeTotals(UblBeBis3Service $ubl): UblBeBis3Service
{
    return $ubl->addLegalMonetaryTotal([
        'line_extension_amount' => 200.00, 'tax_exclusive_amount' => 200.00, 'tax_inclusive_amount' => 200.00,
        'payable_amount' => 200.00,
    ], 'EUR');
}

function vatSubtotalCategory(string $xml): DOMElement
{
    $dom = new DOMDocument;
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

    $node = $xpath->query('//cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory')->item(0);
    assert($node instanceof DOMElement);

    return $node;
}

function vatChildNames(DOMElement $element): array
{
    $names = [];
    foreach ($element->childNodes as $node) {
        if ($node instanceof DOMElement) {
            $names[] = $node->nodeName;
        }
    }

    return $names;
}

// The knowledge base itself

it('knows the nine categories PEPPOL allows outside Italy, each with a label, a description and its rules (BR-CL-17)', function () {
    expect(array_column(VatCategory::cases(), 'value'))->toBe(['S', 'Z', 'E', 'AE', 'K', 'G', 'O', 'L', 'M']);

    foreach (VatCategory::cases() as $category) {
        expect($category->label())->not->toBe('')
            ->and($category->description())->not->toBe('')
            ->and($category->rules())->not->toBeEmpty();
    }

    expect(VatCategory::guide('nl'))->toHaveCount(9)
        ->and(VatCategory::guide('nl')[4])->toMatchArray([
            'code' => 'K',
            'requires_exemption_reason' => true,
            'default_exemption_reason_code' => 'VATEX-EU-IC',
            'exemption_reason_text' => 'Intracommunautaire levering',
            'requires_zero_rate' => true,
        ])
        ->and(VatCategory::guide()[4]['rules'])->toHaveKeys(['BR-IC-02', 'BR-IC-10', 'BR-IC-11', 'BR-IC-12']);
});

it('finds a category by code in any case, and not the Italian split payment (B)', function () {
    expect(VatCategory::fromCode(' k '))->toBe(VatCategory::IntraCommunitySupply)
        ->and(VatCategory::fromCode('ae'))->toBe(VatCategory::ReverseCharge)
        ->and(VatCategory::fromCode('B'))->toBeNull()
        ->and(UblValidator::isValidTaxCategory('L'))->toBeTrue()
        ->and(UblValidator::isValidTaxCategory('X'))->toBeFalse();
});

it('gives each category that needs a reason a default code that belongs to exactly that category (PEPPOL-EN16931-P0104 to P0107)', function () {
    foreach (VatCategory::cases() as $category) {
        $code = $category->defaultExemptionReasonCode();

        if ($code !== null) {
            expect($category->requiresExemptionReason())->toBeTrue()
                ->and(VatExemptionReason::categoryOf($code))->toBe($category)
                ->and(VatExemptionReason::isKnown($code))->toBeTrue();
        }
    }

    expect(VatCategory::Exempt->defaultExemptionReasonCode())->toBeNull()
        ->and(VatCategory::StandardRate->forbidsExemptionReason())->toBeTrue();
});

it('names the codes of the VATEX list and ties the margin scheme codes to category E (BR-CL-22, P0108 to P0111)', function () {
    expect(VatExemptionReason::name('vatex-eu-132-1c'))->toBe('Exempt based on article 132, section 1 (c) of Council Directive 2006/112/EC')
        ->and(VatExemptionReason::isKnown('VATEX-FR-FRANCHISE'))->toBeTrue()
        ->and(VatExemptionReason::name('VATEX-FR-FRANCHISE'))->toBeNull()
        ->and(VatExemptionReason::isKnown('VATEX-EU-XYZ'))->toBeFalse()
        ->and(VatExemptionReason::categoryOf('VATEX-EU-F'))->toBe(VatCategory::Exempt)
        ->and(VatExemptionReason::categoryOf('VATEX-EU-132-1C'))->toBeNull()
        ->and(VatExemptionReason::codes())->toContain('VATEX-EU-IC', 'VATEX-EU-AE', 'VATEX-FR-AE');
});

// BT-120 and BT-121 in the VAT breakdown, Dutch builder

it('writes the code of an intra-community supply when the caller gives none, between Percent and TaxScheme (BR-IC-10)', function () {
    $category = vatSubtotalCategory(vatNl()->addTaxTotal([vatTax('K')])->generateXml());

    expect(vatChildNames($category))->toBe(['cbc:ID', 'cbc:Percent', 'cbc:TaxExemptionReasonCode', 'cac:TaxScheme'])
        ->and($category->getElementsByTagName('TaxExemptionReasonCode')->item(0)?->textContent)->toBe('VATEX-EU-IC');
});

it('writes the code and the text a caller passes, the text in any language (BT-120, BT-121)', function () {
    $category = vatSubtotalCategory(vatNl()->addTaxTotal([vatTax('AE', 0.0, [
        'tax_exemption_reason_code' => 'vatex-eu-ae',
        'tax_exemption_reason' => VatCategory::ReverseCharge->exemptionReasonText('nl'),
    ])])->generateXml());

    expect(vatChildNames($category))->toBe(['cbc:ID', 'cbc:Percent', 'cbc:TaxExemptionReasonCode', 'cbc:TaxExemptionReason', 'cac:TaxScheme'])
        ->and($category->getElementsByTagName('TaxExemptionReasonCode')->item(0)?->textContent)->toBe('VATEX-EU-AE')
        ->and($category->getElementsByTagName('TaxExemptionReason')->item(0)?->textContent)->toBe('Btw verlegd');
});

it('writes a text alone without adding a code, which BR-IC-10 also accepts', function () {
    $category = vatSubtotalCategory(vatNl()->addTaxTotal([vatTax('K', 0.0, ['tax_exemption_reason' => 'Intra-community supply'])])->generateXml());

    expect(vatChildNames($category))->toBe(['cbc:ID', 'cbc:Percent', 'cbc:TaxExemptionReason', 'cac:TaxScheme']);
});

it('writes nothing new for the standard rate, so those documents stay the same (BR-S-10)', function () {
    $xml = vatNl()->addTaxTotal([vatTax('S', 21.0)])->generateXml();

    expect($xml)->not->toContain('TaxExemptionReason')
        ->and(vatChildNames(vatSubtotalCategory($xml)))->toBe(['cbc:ID', 'cbc:Percent', 'cac:TaxScheme']);
});

it('refuses a reason on a category that takes none', function (string $category, string $rule) {
    expect(fn () => vatNl()->addTaxTotal([vatTax($category, 21.0, ['tax_exemption_reason' => 'Anything'])]))
        ->toThrow(InvalidArgumentException::class, "[{$rule}]");
})->with([
    'standard rate' => ['S', 'BR-S-10'],
    'zero rated' => ['Z', 'BR-Z-10'],
    'Canary Islands' => ['L', 'BR-AF-10'],
]);

it('refuses a code outside the VATEX list and a code of another category (BR-CL-22, PEPPOL-EN16931-P0106)', function () {
    expect(fn () => vatNl()->addTaxTotal([vatTax('E', 0.0, ['tax_exemption_reason_code' => 'VATEX-EU-XYZ'])]))
        ->toThrow(InvalidArgumentException::class, '[BR-CL-22]')
        ->and(fn () => vatNl()->addTaxTotal([vatTax('AE', 0.0, ['tax_exemption_reason_code' => 'VATEX-EU-IC'])]))
        ->toThrow(InvalidArgumentException::class, 'may only be used with VAT category K, not with AE');
});

it('reports an exempt breakdown without a reason, because only the seller knows the article (BR-E-10)', function () {
    $ubl = vatNl()->addTaxTotal([vatTax('E')]);

    expect($ubl->generateXml())->not->toContain('TaxExemptionReason')
        ->and($ubl->validate()->errors)->toContain('[BR-E-10] The VAT breakdown of category E needs an exemption reason: pass tax_exemption_reason_code (for example VATEX-EU-132-1C) or tax_exemption_reason to addTaxTotal().')
        ->and(vatNl()->addTaxTotal([vatTax('E', 0.0, ['tax_exemption_reason_code' => 'VATEX-EU-132-1C'])])->validate()->isValid())->toBeTrue();
});

// BR-IC-11 and BR-IC-12: an intra-community supply states when and where the goods went

it('reports an intra-community supply without a delivery date and country (BR-IC-11, BR-IC-12)', function () {
    $errors = vatNl()->addTaxTotal([vatTax('K')])->validate()->errors;

    expect(implode("\n", $errors))->toContain('[BR-IC-11]')->toContain('[BR-IC-12]');
});

it('writes a deliver to country passed without an address, which BR-IC-12 asks for (BT-80)', function () {
    $ubl = vatNl()->addDelivery('2026-01-14', countryCode: 'be')->addTaxTotal([vatTax('K')]);
    $xml = $ubl->generateXml();

    $dom = new DOMDocument;
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

    expect($xpath->evaluate('string(//cac:Delivery/cac:DeliveryLocation/cac:Address/cac:Country/cbc:IdentificationCode)'))->toBe('BE')
        ->and($xpath->evaluate('count(//cac:Delivery/cac:DeliveryLocation/cac:Address/*)'))->toBe(1.0)
        ->and(implode("\n", $ubl->validate()->errors))->not->toContain('BR-IC-1');
});

it('refuses category O next to another category (BR-O-11)', function () {
    $errors = vatNl()->addTaxTotal([vatTax('O'), vatTax('S', 21.0)])->validate()->errors;

    expect(implode("\n", $errors))->toContain('[BR-O-11]');
});

// The Belgian builder, which a Dutch seller uses for a Belgian customer

it('writes the code of an intra-community supply in a Belgian document too (BR-IC-10)', function () {
    $category = vatSubtotalCategory(vatBe()->addTaxTotal([vatTax('K')])->generateXml());

    expect(vatChildNames($category))->toBe(['cbc:ID', 'cbc:Percent', 'cbc:TaxExemptionReasonCode', 'cac:TaxScheme'])
        ->and($category->getElementsByTagName('TaxExemptionReasonCode')->item(0)?->textContent)->toBe('VATEX-EU-IC');
});

it('accepts a Belgian intra-community supply with a delivery, and reports one without (BR-IC-11, BR-IC-12)', function () {
    $without = vatBeTotals(vatBe()->addTaxTotal([vatTax('K')]))->validate();
    $with = vatBeTotals(vatBe()
        ->addDelivery('2026-01-14', '5790000435975', '0088', 'Rue de la Loi 1', null, 'Brussels', '1000', 'BE')
        ->addTaxTotal([vatTax('K')]))->validate();

    expect(implode("\n", $without->errors))->toContain('[BR-IC-11]')->toContain('[BR-IC-12]')
        ->and($with->errors)->toBe([]);
});

it('forgets the old breakdown when the Belgian builder replaces it', function () {
    $ubl = vatBeTotals(vatBe('S')->addTaxTotal([vatTax('E')])->addTaxTotal([vatTax('S', 21.0)]));

    expect(implode("\n", $ubl->validate()->errors))->not->toContain('BR-E-10');
});

// validate() reads the finished document, as a receiver does

function vatNlParties(UblNlBis3Service $ubl, ?string $customerVat = 'BE0999000228', ?string $customerRegistration = null): UblNlBis3Service
{
    return $ubl
        ->addAccountingSupplierParty('12345678', '0106', '12345678', 'My Dutch Company BV', 'Damrak 1', '1012 JS', 'Amsterdam', 'NL', 'NL123456789B01')
        ->addAccountingCustomerParty(
            '0999000228', '0208', '0999000228', 'Customer NV', 'Kerkstraat 1', '2000', 'Antwerpen', 'BE',
            null, $customerRegistration, null, null, null, $customerVat
        );
}

function vatNlLine(string $category, float $percent = 0.0): array
{
    return [
        'id' => '1', 'quantity' => 2, 'unit_code' => 'C62', 'price_amount' => 100.00, 'currency' => 'EUR',
        'line_extension_amount' => 200.00, 'name' => 'Powder coating', 'description' => 'Coating of 2 frames',
        'tax_category_id' => $category, 'tax_percent' => $percent, 'tax_scheme_id' => 'VAT',
    ];
}

function vatErrors(UblNlBis3Service|UblBeBis3Service $ubl): string
{
    return implode("\n", $ubl->validate()->errors);
}

it('accepts a complete Dutch intra-community supply', function () {
    $ubl = vatNlParties(vatNl())
        ->addDelivery('2026-01-14', countryCode: 'BE')
        ->addTaxTotal([vatTax('K')])
        ->addInvoiceLine(vatNlLine('K'));

    expect($ubl->validate()->errors)->toBe([]);
});

it('reports a line whose category is missing from the VAT breakdown (BR-S-01, BR-IC-01)', function () {
    $errors = vatErrors(vatNlParties(vatNl())->addTaxTotal([vatTax('S', 21.0)])->addInvoiceLine(vatNlLine('K')));

    expect($errors)->toContain('[BR-IC-01] A line uses category K, but the VAT breakdown has no entry for it');
});

it('reports two breakdown entries for a category other than S (BR-AE-01)', function () {
    $errors = vatErrors(vatNlParties(vatNl())->addTaxTotal([vatTax('AE'), vatTax('AE')]));

    expect($errors)->toContain('[BR-AE-01] The VAT breakdown has 2 entries for category AE');
});

it('reports a rate that does not fit the category', function (string $category, float $percent, string $rule) {
    $errors = vatErrors(vatNlParties(vatNl())->addDelivery('2026-01-14', countryCode: 'BE')->addTaxTotal([vatTax($category, $percent === 0.0 ? 21.0 : 0.0)])->addInvoiceLine(vatNlLine($category, $percent)));

    expect($errors)->toContain("[{$rule}] A line in category {$category}");
})->with([
    'intra-community supply at 21%' => ['K', 21.0, 'BR-IC-05'],
    'reverse charge at 21%' => ['AE', 21.0, 'BR-AE-05'],
    'standard rate at 0%' => ['S', 0.0, 'BR-S-05'],
]);

it('reports a discount or charge whose rate does not fit (BR-IC-06, BR-IC-07)', function () {
    $errors = vatErrors(vatNlParties(vatNl())
        ->addAllowanceCharge(false, 10.00, 'Discount', 'K', 21.0, 'EUR')
        ->addAllowanceCharge(true, 10.00, 'Freight', 'K', 21.0, 'EUR')
        ->addTaxTotal([vatTax('K')]));

    expect($errors)->toContain('[BR-IC-06] A discount in category K')->toContain('[BR-IC-07] A charge in category K');
});

it('reports VAT charged in the breakdown of a category without VAT, the mistake of a 21% export (BR-IC-09)', function () {
    $errors = vatErrors(vatNlParties(vatNl())->addTaxTotal([vatTax('K', 0.0, ['tax_amount' => 42.00])]));

    expect($errors)->toContain('[BR-IC-09] The VAT breakdown of category K');
});

it('reports the VAT numbers a category needs', function () {
    $withoutBuyerVat = vatErrors(vatNlParties(vatNl(), customerVat: null)->addTaxTotal([vatTax('K')]));
    $reverseChargeWithRegistration = vatErrors(vatNlParties(vatNl(), customerVat: null, customerRegistration: '0999000228')->addTaxTotal([vatTax('AE')]));
    $reverseChargeWithNothing = vatErrors(vatNlParties(vatNl(), customerVat: null)->addTaxTotal([vatTax('AE')]));

    expect($withoutBuyerVat)->toContain("[BR-IC-02] An intra-community supply (K) needs the buyer's VAT number (BT-48): pass \$vatNumber to addAccountingCustomerParty().")
        ->and($reverseChargeWithRegistration)->not->toContain('BR-AE-02')
        ->and($reverseChargeWithNothing)->toContain('[BR-AE-02]');
});

it('reports category O with a VAT number, which the builders always write for the seller (BR-O-02)', function () {
    expect(vatErrors(vatNlParties(vatNl())->addTaxTotal([vatTax('O')])))->toContain('[BR-O-02]');
});

it('writes no VAT rate for category O, on the line, the breakdown, a discount or a charge (BR-O-05, BR-O-06, BR-O-07)', function () {
    $nl = vatNl()->addAllowanceCharge(false, 10.00, 'Discount', 'O', 0.0, 'EUR')->addTaxTotal([vatTax('O')])->addInvoiceLine(vatNlLine('O'))->generateXml();
    $be = vatBe('O')->addAllowanceCharge(true, 10.00, 'Freight', 'O', 0.0, 'EUR')->addTaxTotal([vatTax('O')])->generateXml();

    expect(vatChildNames(vatSubtotalCategory($nl)))->toBe(['cbc:ID', 'cbc:TaxExemptionReasonCode', 'cac:TaxScheme'])
        ->and(vatChildNames(vatSubtotalCategory($be)))->toBe(['cbc:ID', 'cbc:TaxExemptionReasonCode', 'cac:TaxScheme'])
        ->and($nl)->not->toContain('<cbc:Percent>')
        ->and($be)->not->toContain('<cbc:Percent>');
});

it('checks a Belgian credit note the same way (BR-IC-05)', function () {
    $ubl = (new UblBeBis3Service)
        ->createCreditNoteDocument()
        ->addCreditNoteHeader('CN-2026-001', '2026-01-21')
        ->addBillingReference('INV-2026-001', '2026-01-15')
        ->addCreditNoteLine(vatNlLine('K', 21.0))
        ->addTaxTotal([vatTax('K')]);

    expect(vatErrors(vatBeTotals($ubl)))->toContain('[BR-IC-05] A line in category K');
});

it('lets the Belgian builder state a delivery without a location ID, which an intra-community supply needs (BT-80, BR-IC-12)', function () {
    $countryOnly = vatBe()->addDelivery('2026-01-14', country: 'nl')->addTaxTotal([vatTax('K')]);
    $address = vatBe()->addDelivery('2026-01-14', street: 'Damrak 1', city: 'Amsterdam', postal_code: '1012 JS', country: 'NL')->addTaxTotal([vatTax('K')]);

    $dom = new DOMDocument;
    $dom->loadXML($address->generateXml());
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

    expect(vatErrors(vatBeTotals($countryOnly)))->not->toContain('BR-IC-1')
        ->and($countryOnly->generateXml())->toContain('<cbc:IdentificationCode>NL</cbc:IdentificationCode>')
        ->and($xpath->evaluate('count(//cac:Delivery/cac:DeliveryLocation/cbc:ID)'))->toBe(0.0)
        ->and(vatChildNames($xpath->query('//cac:Delivery/cac:DeliveryLocation/cac:Address')->item(0)))->toBe(['cbc:StreetName', 'cbc:CityName', 'cbc:PostalZone', 'cac:Country']);
});
