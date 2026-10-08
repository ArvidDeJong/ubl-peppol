<?php

declare(strict_types=1);

namespace Darvis\UblPeppol\Rules;

/**
 * One validation rule: an official EN 16931 (CEN) or PEPPOL rule, or a rule of this package.
 */
final readonly class Rule
{
    public const SOURCE_CEN = 'CEN';

    public const SOURCE_PEPPOL = 'PEPPOL';

    public const SOURCE_PACKAGE = 'package';

    /**
     * @param  string  $id  The rule id, such as BR-55, NL-R-001 or UBL-PEPPOL-CN-04
     * @param  string  $flag  fatal (the receiver rejects the document) or warning
     * @param  string  $text  The rule text, as the specification words it
     * @param  string|null  $test  The XPath test of the Schematron; null for a rule of this package
     * @param  string|null  $context  The element the rule is tested on, when known
     * @param  string  $source  One of the SOURCE_ constants
     * @param  string|null  $advice  How to satisfy the rule with this package, when the package has something to say
     */
    public function __construct(
        public string $id,
        public string $flag,
        public string $text,
        public ?string $test,
        public ?string $context,
        public string $source,
        public ?string $advice = null,
    ) {}

    public function isFatal(): bool
    {
        return $this->flag === 'fatal';
    }

    /**
     * Whether the rule comes from the specification rather than from this package.
     */
    public function isOfficial(): bool
    {
        return $this->source !== self::SOURCE_PACKAGE;
    }

    /**
     * The page that explains the rule: docs.peppol.eu for an official rule, the package documentation otherwise.
     */
    public function url(): string
    {
        return match ($this->source) {
            self::SOURCE_CEN => 'https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/'.$this->id.'/',
            self::SOURCE_PEPPOL => 'https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-peppol/'.$this->id.'/',
            default => 'https://arviddejong.github.io/ubl-peppol/credit-notes.html',
        };
    }
}
