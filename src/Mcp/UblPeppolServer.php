<?php

declare(strict_types=1);

namespace Darvis\UblPeppol\Mcp;

use Darvis\UblPeppol\Mcp\Tools\ExplainRule;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('UBL PEPPOL')]
#[Instructions('Explains the validation rules of PEPPOL BIS Billing 3.0 and EN 16931 as darvis/ubl-peppol knows them: the official text, whether the receiver rejects the document (fatal) or only warns, the XPath test, and how to satisfy the rule with this package. Use explain-rule whenever a rejection, an exception or a validate() result names a rule id such as BR-CO-15, NL-R-001 or UBL-PEPPOL-CN-04, before you change code: quote the rule from here, never from memory. Rule ids starting with UBL-PEPPOL- are rules of this package, not of the specification.')]
class UblPeppolServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ExplainRule::class,
    ];
}
