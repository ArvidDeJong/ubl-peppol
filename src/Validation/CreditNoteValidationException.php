<?php

namespace Darvis\UblPeppol\Validation;

/**
 * Thrown by generateXml() when a credit note breaks a rule that is checked on every credit note.
 *
 * The first line of the message names every failing rule, so a log or an error tracker that only
 * shows that line still says what went wrong. getErrors() gives the same errors as data, so a
 * host app does not have to parse the message.
 *
 * Rule ids starting with UBL-PEPPOL- are rules of this package, not of the PEPPOL specification.
 *
 * Extends \InvalidArgumentException, so code that catches that keeps working.
 */
class CreditNoteValidationException extends \InvalidArgumentException
{
    public const DOCUMENTATION_URL = 'https://docs.peppol.eu/poacc/billing/3.0/bis/#creditnote';

    /**
     * @param  list<array{rule: string, message: string}>  $errors  The failing rules, in the order they were checked
     */
    public function __construct(private readonly array $errors)
    {
        $details = array_map(
            fn (array $error): string => "[{$error['rule']}] {$error['message']}",
            $errors
        );

        parent::__construct(
            'Credit note validation failed: '.implode(', ', $this->getRuleIds()).
            "\n\n".implode("\n\n", $details).
            "\n\nDocumentation: ".self::DOCUMENTATION_URL
        );
    }

    /**
     * The failing rules with their explanation, in the order they were checked.
     *
     * @return list<array{rule: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * The ids of the failing rules, each once.
     *
     * @return list<string>
     */
    public function getRuleIds(): array
    {
        return array_values(array_unique(array_column($this->errors, 'rule')));
    }
}
