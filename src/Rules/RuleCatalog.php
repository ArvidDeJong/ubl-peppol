<?php

declare(strict_types=1);

namespace Darvis\UblPeppol\Rules;

/**
 * Looks up validation rules by id: the official EN 16931 and PEPPOL rules of the OpenPEPPOL
 * release in resources/rules/peppol-rules.php, and the UBL-PEPPOL rules of this package.
 *
 * The official texts are generated from the Schematron by examples/validate/build_rule_catalog.php,
 * never written by hand: a rule quoted from memory is how this package once refused credit notes
 * under BR-55, a rule that says something else.
 */
final class RuleCatalog
{
    /**
     * The rules of this package. They are not part of the PEPPOL specification.
     *
     * @var array<string, array{flag: string, text: string, advice: string}>
     */
    private const PACKAGE_RULES = [
        'UBL-PEPPOL-CN-01' => [
            'flag' => 'fatal',
            'text' => 'The LineExtensionAmount of a credit note line shall not be negative: type code 381 says the document is a credit, not a minus sign.',
            'advice' => 'Thrown by the Belgian generateXml(). addCreditNoteLine() already makes the line amount positive, so pass abs() when you write a line yourself.',
        ],
        'UBL-PEPPOL-CN-02' => [
            'flag' => 'fatal',
            'text' => 'The CreditedQuantity of a credit note line shall not be negative.',
            'advice' => 'Thrown by the Belgian generateXml(). addCreditNoteLine() already makes the quantity positive.',
        ],
        'UBL-PEPPOL-CN-03' => [
            'flag' => 'fatal',
            'text' => 'The LineExtensionAmount in the monetary total of a credit note shall not be negative.',
            'advice' => 'Thrown by the Belgian generateXml(). Pass the totals to addLegalMonetaryTotal() as positive numbers; it writes what you pass.',
        ],
        'UBL-PEPPOL-CN-04' => [
            'flag' => 'fatal',
            'text' => 'The PayableAmount of a credit note shall not be negative.',
            'advice' => 'Thrown by the Belgian generateXml(). Pass the amount to be credited to addLegalMonetaryTotal() as a positive number.',
        ],
        'UBL-PEPPOL-CN-05' => [
            'flag' => 'warning',
            'text' => 'A credit note should reference the invoice it credits (BG-3), so the receiver can match the credit to an invoice. PEPPOL only requires it from a supplier in the Netherlands (NL-R-001).',
            'advice' => 'A warning from validate() for a supplier outside the Netherlands. Pass the number and issue date of the credited invoice to addBillingReference(), taken from a link stored on the credit note, not parsed from a line description.',
        ],
    ];

    /**
     * What this package adds to an official rule: where it is checked and how to satisfy it.
     *
     * @var array<string, string>
     */
    private const ADVICE = [
        'BR-55' => 'This rule does not require a billing reference; it only demands that one, once present, holds the invoice number. addBillingReference() throws on an empty number. The requirement to reference the credited invoice is NL-R-001, for a supplier in the Netherlands.',
        'NL-R-001' => 'Depends on the supplier country passed to addAccountingSupplierParty(), not on the builder: both builders throw it from generateXml() for a supplier in the Netherlands. Pass the number and issue date of the credited invoice to addBillingReference(), taken from a link stored on the credit note, not parsed from a line description.',
        'BR-27' => 'Thrown by the Belgian generateXml() for a negative price on a credit note line. addCreditNoteLine() already makes the price positive.',
    ];

    /**
     * @var array{release: string, rules: array<string, array{flag: string, text: string, test: string, context: string|null, source: string}>}|null
     */
    private static ?array $official = null;

    /**
     * The OpenPEPPOL release the official rules come from, such as 2026.5.
     */
    public static function release(): string
    {
        return self::official()['release'];
    }

    /**
     * Find a rule by id. Brackets and case do not matter: "[br-55]" finds BR-55.
     */
    public static function find(string $id): ?Rule
    {
        $id = self::normalise($id);

        if (isset(self::PACKAGE_RULES[$id])) {
            $rule = self::PACKAGE_RULES[$id];

            return new Rule($id, $rule['flag'], $rule['text'], null, null, Rule::SOURCE_PACKAGE, $rule['advice']);
        }

        $rule = self::official()['rules'][$id] ?? null;
        if ($rule === null) {
            return null;
        }

        return new Rule($id, $rule['flag'], $rule['text'], $rule['test'], $rule['context'], $rule['source'], self::ADVICE[$id] ?? null);
    }

    /**
     * Rules whose id starts with the query, then rules whose id or text holds every word of it.
     *
     * @return list<Rule>
     */
    public static function search(string $query, int $limit = 10): array
    {
        $words = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return [];
        }

        $normalised = self::normalise($query);
        $ids = array_merge(array_keys(self::PACKAGE_RULES), array_keys(self::official()['rules']));

        $byId = array_filter($ids, fn (string $id): bool => str_starts_with($id, $normalised));
        $byText = array_filter($ids, function (string $id) use ($words): bool {
            $rule = self::find($id);
            if ($rule === null) {
                return false;
            }

            foreach ($words as $word) {
                if (stripos($rule->id.' '.$rule->text, $word) === false) {
                    return false;
                }
            }

            return true;
        });

        $matches = array_slice(array_values(array_unique(array_merge($byId, $byText))), 0, max(1, $limit));

        return array_values(array_filter(array_map(fn (string $id): ?Rule => self::find($id), $matches)));
    }

    private static function normalise(string $id): string
    {
        return strtoupper(trim($id, " \t\n\r\0\x0B[]"));
    }

    /**
     * @return array{release: string, rules: array<string, array{flag: string, text: string, test: string, context: string|null, source: string}>}
     */
    private static function official(): array
    {
        return self::$official ??= require __DIR__.'/../../resources/rules/peppol-rules.php';
    }
}
