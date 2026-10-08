<?php

declare(strict_types=1);

namespace Darvis\UblPeppol\Mcp\Tools;

use Darvis\UblPeppol\Rules\Rule;
use Darvis\UblPeppol\Rules\RuleCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Explain a PEPPOL BIS Billing 3.0 or EN 16931 validation rule by its id (BR-55, BR-CO-15, NL-R-001, PEPPOL-EN16931-R010, UBL-CR-001) or a rule of darvis/ubl-peppol (UBL-PEPPOL-CN-01 to 05): the official text, fatal or warning, the XPath test, the element it is tested on, how to satisfy it with this package, and a link. Without an exact id, pass words from an error message instead and get the matching rules.')]
#[IsReadOnly]
#[IsIdempotent]
class ExplainRule extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'rule' => $schema->string()
                ->description('A rule id such as NL-R-001, with or without brackets, or words to search the rule texts for, such as "billing reference".')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'rule' => ['required', 'string', 'max:200'],
        ]);

        $rule = RuleCatalog::find($validated['rule']);
        if ($rule !== null) {
            return Response::text($this->describe($rule));
        }

        $matches = RuleCatalog::search($validated['rule']);
        if ($matches === []) {
            return Response::error("No rule matches \"{$validated['rule']}\" in OpenPEPPOL release ".RuleCatalog::release().' or in the rules of this package.');
        }

        $lines = array_map(fn (Rule $match): string => "- {$match->id} ({$match->flag}): {$match->text}", $matches);

        return Response::text("No rule has the id \"{$validated['rule']}\". Rules that match:\n".implode("\n", $lines)
            ."\n\nAsk again with one of these ids for the full explanation.");
    }

    private function describe(Rule $rule): string
    {
        $origin = $rule->isOfficial()
            ? "{$rule->source} rule, OpenPEPPOL release ".RuleCatalog::release()
            : 'Rule of darvis/ubl-peppol, not part of the PEPPOL specification';

        $severity = $rule->isFatal()
            ? 'fatal: the receiver rejects the document'
            : 'warning: the document is accepted';

        $parts = [
            "{$rule->id} ({$origin})",
            "Flag: {$severity}",
            "Rule: {$rule->text}",
        ];

        if ($rule->context !== null) {
            $parts[] = "Tested on: {$rule->context}";
        }
        if ($rule->test !== null) {
            $parts[] = "Test: {$rule->test}";
        }
        if ($rule->advice !== null) {
            $parts[] = "With this package: {$rule->advice}";
        }
        $parts[] = "More: {$rule->url()}";

        return implode("\n", $parts);
    }
}
