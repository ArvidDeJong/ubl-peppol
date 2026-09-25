<?php

declare(strict_types=1);

use Darvis\UblPeppol\UblNlBis3Service;

/**
 * NL-R-002 to NL-R-005 of the PEPPOL Schematron: the addresses and the legal registrations of the
 * parties, for a supplier in the Netherlands. They test PartyLegalEntity/CompanyID, not the endpoint.
 */
function dutchRulesDocument(string $supplierEndpointScheme = '0106', string $customerCountry = 'NL', string $street = 'Damrak 1'): UblNlBis3Service
{
    return (new UblNlBis3Service)
        ->createDocument()
        ->addInvoiceHeader('INV-2026-001', '2026-01-15', '2026-02-14')
        ->addAccountingSupplierParty(
            $supplierEndpointScheme === '0088' ? '5790000435975' : '12345678', $supplierEndpointScheme, '12345678',
            'My Dutch Company BV', $street, '1012 JS', 'Amsterdam', 'NL', 'NL123456789B01'
        )
        ->addAccountingCustomerParty(
            '87654321', '0106', '87654321', 'Customer BV', 'Kalverstraat 1', '1012 NX', 'Amsterdam', $customerCountry, null, '87654321'
        );
}

function dutchRulesErrors(UblNlBis3Service $ubl): string
{
    return implode("\n", $ubl->validate()->errors);
}

it('accepts a Dutch supplier that receives under another endpoint scheme, such as a GLN (NL-R-003)', function () {
    $ubl = dutchRulesDocument(supplierEndpointScheme: '0088')->addSupplierLegalRegistration('12345678');

    expect(dutchRulesErrors($ubl))->not->toContain('NL-R-003');
});

it('refuses a Dutch supplier\'s legal registration under a scheme other than KvK or OIN (NL-R-003)', function () {
    $ubl = dutchRulesDocument()->addSupplierLegalRegistration('0999000228', '0208');

    expect(dutchRulesErrors($ubl))->toContain("[NL-R-003] The legal registration of a supplier in the Netherlands (BT-30) must be a KvK number (scheme 0106) or an OIN (scheme 0190), not scheme '0208'");
});

it('accepts an OIN, scheme 0190, for a Dutch government customer (NL-R-005)', function () {
    $ubl = dutchRulesDocument()->addSupplierLegalRegistration('12345678')->addCustomerLegalRegistration('00000001003214345000', '0190');

    expect(dutchRulesErrors($ubl))->not->toContain('NL-R-005');
});

it('refuses a Dutch customer\'s legal registration under another scheme, and leaves a foreign customer alone (NL-R-005)', function () {
    $dutch = dutchRulesDocument()->addCustomerLegalRegistration('ABC123', '9999');
    $german = dutchRulesDocument(customerCountry: 'DE')->addCustomerLegalRegistration('HRB12345', '9999');

    expect(dutchRulesErrors($dutch))->toContain('[NL-R-005]')
        ->and(dutchRulesErrors($german))->not->toContain('NL-R-005');
});

it('refuses a Dutch supplier address without a street while building, so NL-R-002 cannot fail', function () {
    expect(fn () => dutchRulesDocument(street: ''))->toThrow(InvalidArgumentException::class);
});
