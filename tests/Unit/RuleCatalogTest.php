<?php

use Darvis\UblPeppol\Rules\Rule;
use Darvis\UblPeppol\Rules\RuleCatalog;

it('holds the official rules of the OpenPEPPOL release the validator runs', function () {
    $release = json_decode((string) file_get_contents(__DIR__.'/../../docs/assets/validator/release.json'), true)['release'];

    expect(RuleCatalog::release())->toBe($release);
});

it('quotes BR-55 as the specification words it: about the number, not about the reference itself', function () {
    $rule = RuleCatalog::find('BR-55');

    expect($rule)->toBeInstanceOf(Rule::class)
        ->and($rule->text)->toBe('Each Preceding Invoice reference (BG-3) shall contain a Preceding Invoice reference (BT-25).')
        ->and($rule->isFatal())->toBeTrue()
        ->and($rule->source)->toBe(Rule::SOURCE_CEN)
        ->and($rule->context)->toBe('BillingReference')
        ->and($rule->advice)->toContain('NL-R-001')
        ->and($rule->url())->toBe('https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/BR-55/');
});

it('knows NL-R-001 as a PEPPOL rule tested on the credit note type code', function () {
    $rule = RuleCatalog::find('NL-R-001');

    expect($rule->source)->toBe(Rule::SOURCE_PEPPOL)
        ->and($rule->text)->toStartWith('For suppliers in the Netherlands, if the document is a creditnote')
        ->and($rule->context)->toBe('CreditNoteTypeCode')
        ->and($rule->url())->toBe('https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-peppol/NL-R-001/');
});

it('finds a rule regardless of brackets and case, as it appears in an error message', function () {
    expect(RuleCatalog::find('[br-co-15]')?->id)->toBe('BR-CO-15')
        ->and(RuleCatalog::find('BR-DOES-NOT-EXIST'))->toBeNull();
});

it('marks the rules of this package as not official', function (string $id) {
    $rule = RuleCatalog::find($id);

    expect($rule)->not->toBeNull()
        ->and($rule->isOfficial())->toBeFalse()
        ->and($rule->advice)->not->toBeEmpty();
})->with(['UBL-PEPPOL-CN-01', 'UBL-PEPPOL-CN-02', 'UBL-PEPPOL-CN-03', 'UBL-PEPPOL-CN-04', 'UBL-PEPPOL-CN-05']);

it('knows every rule id the builders put in a message', function () {
    $source = file_get_contents(__DIR__.'/../../src/UblBeBis3Service.php')
        .file_get_contents(__DIR__.'/../../src/UblNlBis3Service.php')
        .file_get_contents(__DIR__.'/../../src/Validation/UblValidator.php');

    preg_match_all("/'rule' => '([A-Z0-9-]+)'/", $source, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach (array_unique($matches[1]) as $id) {
        expect(RuleCatalog::find($id))->not->toBeNull("{$id} is used in a message but missing from the catalog");
    }
});

it('searches by id prefix and by the words of a rule text', function () {
    expect(array_map(fn (Rule $rule) => $rule->id, RuleCatalog::search('NL-R-00', 3)))->toBe(['NL-R-001', 'NL-R-002', 'NL-R-003'])
        ->and(array_map(fn (Rule $rule) => $rule->id, RuleCatalog::search('billing reference')))->toContain('NL-R-001')
        ->and(RuleCatalog::search('   '))->toBe([]);
});
