<?php

declare(strict_types=1);

use Darvis\UblPeppol\UblBeBis3Service;
use Darvis\UblPeppol\UblNlBis3Service;

/**
 * DateTime::createFromFormat('Y-m-d') fills the time with the current time, so a date of today
 * compared later than "today 00:00" and was refused as a date in the future. Most documents are
 * dated today. The tests use the real clock on purpose: a fixed date can never catch this.
 */
it('accepts a document dated today', function (Closure $build) {
    expect($build(date('Y-m-d'), date('Y-m-d', strtotime('+14 days')))->generateXml())
        ->toContain('<cbc:IssueDate>'.date('Y-m-d').'</cbc:IssueDate>');
})->with([
    'Dutch invoice' => [fn (string $today, string $due) => (new UblNlBis3Service)->createDocument()->addInvoiceHeader('X-1', $today, $due)],
    'Belgian invoice' => [fn (string $today, string $due) => (new UblBeBis3Service)->createDocument()->addInvoiceHeader('X-1', $today, $due)],
    'Dutch credit note' => [fn (string $today) => (new UblNlBis3Service)->createCreditNoteDocument()->addCreditNoteHeader('X-1', $today)->addBillingReference('X-0')],
    'Dutch credit note, DateTime of this moment' => [fn () => (new UblNlBis3Service)->createCreditNoteDocument()->addCreditNoteHeader('X-1', new DateTime)->addBillingReference('X-0')],
    'Belgian credit note' => [fn (string $today) => (new UblBeBis3Service)->createCreditNoteDocument()->addCreditNoteHeader('X-1', $today)->addBillingReference('X-0')],
]);

it('still refuses a document dated tomorrow', function (Closure $build) {
    expect(fn () => $build(date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+14 days'))))
        ->toThrow(InvalidArgumentException::class, 'future');
})->with([
    'Dutch invoice' => [fn (string $date, string $due) => (new UblNlBis3Service)->createDocument()->addInvoiceHeader('X-1', $date, $due)],
    'Belgian invoice' => [fn (string $date, string $due) => (new UblBeBis3Service)->createDocument()->addInvoiceHeader('X-1', $date, $due)],
    'Dutch credit note' => [fn (string $date) => (new UblNlBis3Service)->createCreditNoteDocument()->addCreditNoteHeader('X-1', $date)],
]);
