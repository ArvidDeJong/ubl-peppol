<?php

use Darvis\UblPeppol\UblBeBis3Service;
use Darvis\UblPeppol\Validation\CreditNoteValidationException;

describe('Credit Note Tests - PEPPOL BIS Billing 3.0 / EN 16931', function () {

    // Document Creation Tests
    it('creates a CreditNote document with correct root element', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $xml = $service->generateXml();

        expect($xml)->toContain('<CreditNote');
        expect($xml)->toContain('xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"');
    });

    it('sets isCreditNote flag to true for credit note documents', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();

        expect($service->isCreditNote())->toBeTrue();
    });

    it('sets isCreditNote flag to false for regular invoice documents', function () {
        $service = new UblBeBis3Service;
        $service->createDocument();

        expect($service->isCreditNote())->toBeFalse();
    });

    it('throws exception when trying to initialize document twice', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();

        expect(fn () => $service->createCreditNoteDocument())
            ->toThrow(RuntimeException::class);
    });

    // Credit Note Header Tests
    it('adds CreditNoteTypeCode 381', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $xml = $service->generateXml();

        expect($xml)->toContain('<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>');
    });

    it('adds credit note number as ID', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('CN-TEST-123', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $xml = $service->generateXml();

        expect($xml)->toContain('<cbc:ID>CN-TEST-123</cbc:ID>');
    });

    it('adds issue date in correct format', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $xml = $service->generateXml();

        expect($xml)->toContain('<cbc:IssueDate>2026-01-21</cbc:IssueDate>');
    });

    it('accepts DateTime object for issue date', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $date = new DateTime('2026-03-15');
        $service->addCreditNoteHeader('C2026-001', $date);
        $service->addBillingReference('F2026-050');
        $xml = $service->generateXml();

        expect($xml)->toContain('<cbc:IssueDate>2026-03-15</cbc:IssueDate>');
    });

    it('throws exception for invalid date format', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();

        expect(fn () => $service->addCreditNoteHeader('C2026-001', 'invalid-date'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws exception for empty credit note number', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();

        expect(fn () => $service->addCreditNoteHeader('', '2026-01-21'))
            ->toThrow(InvalidArgumentException::class);
    });

    // Billing Reference Tests (BG-3; BR-55 only demands the number once a reference is present)
    it('adds BillingReference with original invoice number', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050', '2026-01-15');

        $xml = $service->generateXml();

        expect($xml)->toContain('<cac:BillingReference>');
        expect($xml)->toContain('<cac:InvoiceDocumentReference>');
        expect($xml)->toContain('<cbc:ID>F2026-050</cbc:ID>');
    });

    it('adds original invoice issue date when provided', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050', '2026-01-15');

        $xml = $service->generateXml();

        expect($xml)->toMatch('/<cac:InvoiceDocumentReference>.*<cbc:IssueDate>2026-01-15<\/cbc:IssueDate>.*<\/cac:InvoiceDocumentReference>/s');
    });

    it('works without original invoice date', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050'); // No date

        $xml = $service->generateXml();

        expect($xml)->toContain('<cbc:ID>F2026-050</cbc:ID>');
    });

    it('generates a credit note without BillingReference, because PEPPOL does not require one from a Belgian supplier', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');

        expect($service->hasBillingReference())->toBeFalse();
        expect($service->generateXml())->not->toContain('<cac:BillingReference>');
    });

    it('rejects an empty billing reference (BR-55)', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');

        expect(fn () => $service->addBillingReference('  '))
            ->toThrow(InvalidArgumentException::class, '[BR-55]');
        expect($service->hasBillingReference())->toBeFalse();
    });

    // Credit Note Lines Tests
    it('adds CreditNoteLine element instead of InvoiceLine', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $service->addCreditNoteLine([
            'id' => '1',
            'quantity' => '5',
            'unit_code' => 'C62',
            'description' => 'Refund item',
            'name' => 'Product X',
            'price_amount' => '100.00',
            'currency' => 'EUR',
            'tax_category_id' => 'S',
            'tax_percent' => 21,
            'tax_scheme_id' => 'VAT',
        ]);

        $xml = $service->generateXml();

        expect($xml)->toContain('<cac:CreditNoteLine>');
        expect($xml)->not->toContain('<cac:InvoiceLine>');
    });

    it('uses CreditedQuantity instead of InvoicedQuantity', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $service->addCreditNoteLine([
            'id' => '1',
            'quantity' => '5',
            'unit_code' => 'C62',
            'description' => 'Refund item',
            'name' => 'Product X',
            'price_amount' => '100.00',
            'currency' => 'EUR',
            'tax_category_id' => 'S',
            'tax_percent' => 21,
            'tax_scheme_id' => 'VAT',
        ]);

        $xml = $service->generateXml();

        expect($xml)->toContain('<cbc:CreditedQuantity');
        expect($xml)->not->toContain('<cbc:InvoicedQuantity');
    });

    it('converts negative quantities to positive (auto-correction)', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $service->addCreditNoteLine([
            'id' => '1',
            'quantity' => '-5', // Negative input
            'unit_code' => 'C62',
            'description' => 'Refund item',
            'name' => 'Product X',
            'price_amount' => '100.00',
            'currency' => 'EUR',
            'tax_category_id' => 'S',
            'tax_percent' => 21,
            'tax_scheme_id' => 'VAT',
        ]);

        $xml = $service->generateXml();

        expect($xml)->toContain('>5.00</cbc:CreditedQuantity>');
        expect($xml)->not->toContain('>-5.00</cbc:CreditedQuantity>');
    });

    it('converts negative prices to positive (auto-correction)', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $service->addCreditNoteLine([
            'id' => '1',
            'quantity' => '1',
            'unit_code' => 'C62',
            'description' => 'Refund item',
            'name' => 'Product X',
            'price_amount' => '-99.99', // Negative input
            'currency' => 'EUR',
            'tax_category_id' => 'S',
            'tax_percent' => 21,
            'tax_scheme_id' => 'VAT',
        ]);

        $xml = $service->generateXml();

        expect($xml)->toContain('>99.99</cbc:PriceAmount>');
        expect($xml)->not->toContain('>-99.99</cbc:PriceAmount>');
    });

    it('calculates LineExtensionAmount correctly', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');
        $service->addCreditNoteLine([
            'id' => '1',
            'quantity' => '3',
            'unit_code' => 'C62',
            'description' => 'Refund item',
            'name' => 'Product X',
            'price_amount' => '50.00',
            'currency' => 'EUR',
            'tax_category_id' => 'S',
            'tax_percent' => 21,
            'tax_scheme_id' => 'VAT',
        ]);

        $xml = $service->generateXml();

        // 3 * 50 = 150
        expect($xml)->toContain('>150.00</cbc:LineExtensionAmount>');
    });

    // Complete Credit Note Generation Test
    it('generates a complete valid credit note XML', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050', '2026-01-15');

        $service->addAccountingSupplierParty(
            '0999000197', '0208', 'BE0999000197',
            'Test Company', 'Teststraat 1', '1000', 'Brussel', 'BE', 'BE0999000197'
        );

        $service->addAccountingCustomerParty(
            '0888000188', '0208', 'CUST-1',
            'Klant NV', 'Klantstraat 10', '2000', 'Antwerpen', 'BE'
        );

        $service->addCreditNoteLine([
            'id' => '1',
            'quantity' => '5',
            'unit_code' => 'C62',
            'description' => 'Terugbetaling product X',
            'name' => 'Product X',
            'price_amount' => '100.00',
            'currency' => 'EUR',
            'tax_category_id' => 'S',
            'tax_percent' => 21,
            'tax_scheme_id' => 'VAT',
        ]);

        $service->addTaxTotal([
            [
                'taxable_amount' => 500,
                'tax_amount' => 105,
                'currency' => 'EUR',
                'tax_category_id' => 'S',
                'tax_percent' => 21,
                'tax_scheme_id' => 'VAT',
            ],
        ]);

        $service->addLegalMonetaryTotal([
            'line_extension_amount' => 500,
            'tax_exclusive_amount' => 500,
            'tax_inclusive_amount' => 605,
            'charge_total_amount' => 0,
            'payable_amount' => 605,
        ], 'EUR');

        $xml = $service->generateXml();

        // Verify XML is valid
        $dom = new DOMDocument;
        $result = $dom->loadXML($xml);
        expect($result)->toBeTrue();

        // Verify key elements
        expect($xml)->toContain('<CreditNote');
        expect($xml)->toContain('<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>');
        expect($xml)->toContain('<cac:BillingReference>');
        expect($xml)->toContain('<cac:CreditNoteLine>');
        expect($xml)->toContain('<cbc:CreditedQuantity');
    });

    it('generates XML that can be parsed with SimpleXML', function () {
        $service = new UblBeBis3Service;
        $service->createCreditNoteDocument();
        $service->addCreditNoteHeader('C2026-001', '2026-01-21');
        $service->addBillingReference('F2026-050');

        $xml = $service->generateXml();

        $simpleXml = simplexml_load_string($xml);
        expect($simpleXml)->not->toBeFalse();
        expect($simpleXml->getName())->toBe('CreditNote');
    });

    // Validation Error Message Tests
    it('warns from validate() when the credit note has no BillingReference', function () {
        $service = beCreditNoteWithTotals(withBillingReference: false);

        $result = $service->validate();

        expect($result->isValid())->toBeTrue();
        expect($result->getWarningsAsString())
            ->toContain('[UBL-PEPPOL-CN-05]')
            ->toContain('addBillingReference(')
            ->toContain('do not parse the number from a line description');
    });

    it('does not warn once the BillingReference is there', function () {
        $service = beCreditNoteWithTotals(withBillingReference: true);

        expect($service->hasBillingReference())->toBeTrue();
        expect($service->validate()->getWarningsAsString())->not->toContain('UBL-PEPPOL-CN-05');
    });

    it('names every failing rule on the first line of the exception, with the details after it', function () {
        $service = beCreditNoteWithTotals(withBillingReference: true, lineExtensionAmount: -500, payableAmount: -605);

        try {
            $service->generateXml();
            throw new Exception('Expected exception was not thrown');
        } catch (CreditNoteValidationException $e) {
            expect(strtok($e->getMessage(), "\n"))->toBe('Credit note validation failed: UBL-PEPPOL-CN-03, UBL-PEPPOL-CN-04');
            expect($e->getRuleIds())->toBe(['UBL-PEPPOL-CN-03', 'UBL-PEPPOL-CN-04']);
            expect($e->getErrors()[0]['message'])->toContain('addLegalMonetaryTotal()');
            expect($e->getMessage())->toContain('docs.peppol.eu');
        }
    });

    it('still throws an InvalidArgumentException, so existing catch blocks keep working', function () {
        $service = beCreditNoteWithTotals(withBillingReference: true, payableAmount: -605);

        expect(fn () => $service->generateXml())->toThrow(InvalidArgumentException::class, 'UBL-PEPPOL-CN-04');
    });

});

/**
 * A complete Belgian credit note with one line, VAT and totals.
 */
function beCreditNoteWithTotals(bool $withBillingReference, float $lineExtensionAmount = 500, float $payableAmount = 605, string $supplierCountry = 'BE'): UblBeBis3Service
{
    $service = new UblBeBis3Service;
    $service->createCreditNoteDocument();
    $service->addCreditNoteHeader('C2026-001', '2026-01-21');

    if ($withBillingReference) {
        $service->addBillingReference('F2026-050', '2026-01-15');
    }

    $service->addAccountingSupplierParty(
        '0999000197', '0208', 'SUPP-1',
        'Test Company', 'Teststraat 1', '1000', 'Brussel', $supplierCountry, 'BE0999000197'
    );
    $service->addAccountingCustomerParty(
        '0888000188', '0208', 'CUST-1',
        'Klant NV', 'Klantstraat 10', '2000', 'Antwerpen', 'BE'
    );
    $service->addCreditNoteLine([
        'id' => '1',
        'quantity' => '5',
        'unit_code' => 'C62',
        'description' => 'Terugbetaling product X',
        'name' => 'Product X',
        'price_amount' => '100.00',
        'currency' => 'EUR',
        'tax_category_id' => 'S',
        'tax_percent' => 21,
        'tax_scheme_id' => 'VAT',
    ]);
    $service->addTaxTotal([[
        'taxable_amount' => 500,
        'tax_amount' => 105,
        'currency' => 'EUR',
        'tax_category_id' => 'S',
        'tax_percent' => 21,
        'tax_scheme_id' => 'VAT',
    ]]);
    $service->addLegalMonetaryTotal([
        'line_extension_amount' => $lineExtensionAmount,
        'tax_exclusive_amount' => 500,
        'tax_inclusive_amount' => 605,
        'charge_total_amount' => 0,
        'payable_amount' => $payableAmount,
    ], 'EUR');

    return $service;
}

describe('NL-R-001 follows the supplier, not the builder', function () {
    it('refuses a credit note from a Dutch supplier without BillingReference, also in the Belgian builder', function () {
        $service = beCreditNoteWithTotals(withBillingReference: false, supplierCountry: 'NL');

        try {
            $service->generateXml();
            throw new Exception('Expected exception was not thrown');
        } catch (CreditNoteValidationException $e) {
            expect($e->getRuleIds())->toBe(['NL-R-001']);
        }

        expect($service->validate()->getErrorsAsString())->toContain('[NL-R-001]');
    });

    it('only warns for a Belgian supplier', function () {
        $service = beCreditNoteWithTotals(withBillingReference: false, supplierCountry: 'BE');

        expect($service->generateXml())->toContain('<CreditNote');
        expect($service->validate()->getErrorsAsString())->not->toContain('NL-R-001');
        expect($service->validate()->getWarningsAsString())->toContain('[UBL-PEPPOL-CN-05]');
    });
});
