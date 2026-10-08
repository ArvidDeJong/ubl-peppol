<?php

use Darvis\UblPeppol\Validation\CreditNoteValidationException;

it('names each failing rule once on the first line, even when it fails on several lines', function () {
    $exception = new CreditNoteValidationException([
        ['rule' => 'BR-27', 'message' => 'Credit note line 1 has a negative PriceAmount (-10).'],
        ['rule' => 'BR-27', 'message' => 'Credit note line 2 has a negative PriceAmount (-20).'],
        ['rule' => 'UBL-PEPPOL-CN-04', 'message' => 'The PayableAmount is negative.'],
    ]);

    expect(strtok($exception->getMessage(), "\n"))->toBe('Credit note validation failed: BR-27, UBL-PEPPOL-CN-04')
        ->and($exception->getRuleIds())->toBe(['BR-27', 'UBL-PEPPOL-CN-04'])
        ->and($exception->getErrors())->toHaveCount(3)
        ->and($exception->getMessage())->toContain('[BR-27] Credit note line 2 has a negative PriceAmount (-20).')
        ->and($exception->getMessage())->toEndWith('Documentation: '.CreditNoteValidationException::DOCUMENTATION_URL);
});

it('is an InvalidArgumentException', function () {
    expect(new CreditNoteValidationException([['rule' => 'NL-R-001', 'message' => 'x']]))
        ->toBeInstanceOf(InvalidArgumentException::class);
});
