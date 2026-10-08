<?php

declare(strict_types=1);

use Darvis\UblPeppol\Mcp\Tools\ExplainRule;
use Darvis\UblPeppol\Mcp\UblPeppolServer;
use Laravel\Mcp\Server\Registrar;

it('registers the local server under the configured handle', function () {
    expect(app(Registrar::class)->getLocalServer('ubl-peppol'))->not->toBeNull();
});

it('explains an official rule with its text, flag, test and advice', function () {
    UblPeppolServer::tool(ExplainRule::class, ['rule' => '[NL-R-001]'])
        ->assertOk()
        ->assertSee('NL-R-001 (PEPPOL rule, OpenPEPPOL release')
        ->assertSee('Flag: fatal: the receiver rejects the document')
        ->assertSee('For suppliers in the Netherlands, if the document is a creditnote')
        ->assertSee('Tested on: CreditNoteTypeCode')
        ->assertSee('With this package: Depends on the supplier country')
        ->assertSee('https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-peppol/NL-R-001/');
});

it('says a package rule is not part of the specification', function () {
    UblPeppolServer::tool(ExplainRule::class, ['rule' => 'UBL-PEPPOL-CN-05'])
        ->assertOk()
        ->assertSee('Rule of darvis/ubl-peppol, not part of the PEPPOL specification')
        ->assertSee('Flag: warning: the document is accepted');
});

it('lists matching rules when the input is not an id', function () {
    UblPeppolServer::tool(ExplainRule::class, ['rule' => 'billing reference'])
        ->assertOk()
        ->assertSee('No rule has the id "billing reference". Rules that match:')
        ->assertSee('- NL-R-001 (fatal)');
});

it('returns an error when nothing matches', function () {
    UblPeppolServer::tool(ExplainRule::class, ['rule' => 'XYZ-NOTHING-999'])
        ->assertHasErrors(['No rule matches "XYZ-NOTHING-999"']);
});
