<?php

declare(strict_types=1);

namespace Darvis\UblPeppol\Vat;

/**
 * The VAT exemption reason codes (BT-121) of the CEF VATEX code list, which BR-CL-22 demands.
 *
 * A code goes into the VAT breakdown of a category that needs a reason; see
 * VatCategory::requiresExemptionReason(). The names are the ones of the official list:
 * https://docs.peppol.eu/poacc/billing/3.0/codelist/vatex/
 */
final class VatExemptionReason
{
    public const REVERSE_CHARGE = 'VATEX-EU-AE';

    public const INTRA_COMMUNITY_SUPPLY = 'VATEX-EU-IC';

    public const EXPORT_OUTSIDE_EU = 'VATEX-EU-G';

    public const NOT_SUBJECT_TO_VAT = 'VATEX-EU-O';

    /**
     * The codes that belong to exactly one category. PEPPOL-EN16931-P0104 to P0111 reject a
     * document that uses one of them with any other category.
     *
     * @var array<string, string>
     */
    private const CATEGORY_OF_CODE = [
        'VATEX-EU-G' => 'G',
        'VATEX-EU-O' => 'O',
        'VATEX-EU-IC' => 'K',
        'VATEX-EU-AE' => 'AE',
        'VATEX-EU-D' => 'E',
        'VATEX-EU-F' => 'E',
        'VATEX-EU-I' => 'E',
        'VATEX-EU-J' => 'E',
    ];

    /**
     * The European codes and their names in the code list.
     *
     * @var array<string, string>
     */
    private const NAMES = [
        'VATEX-EU-79-C' => 'Exempt based on article 79, point c of Council Directive 2006/112/EC',
        'VATEX-EU-132' => 'Exempt based on article 132 of Council Directive 2006/112/EC',
        'VATEX-EU-132-1A' => 'Exempt based on article 132, section 1 (a) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1B' => 'Exempt based on article 132, section 1 (b) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1C' => 'Exempt based on article 132, section 1 (c) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1D' => 'Exempt based on article 132, section 1 (d) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1E' => 'Exempt based on article 132, section 1 (e) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1F' => 'Exempt based on article 132, section 1 (f) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1G' => 'Exempt based on article 132, section 1 (g) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1H' => 'Exempt based on article 132, section 1 (h) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1I' => 'Exempt based on article 132, section 1 (i) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1J' => 'Exempt based on article 132, section 1 (j) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1K' => 'Exempt based on article 132, section 1 (k) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1L' => 'Exempt based on article 132, section 1 (l) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1M' => 'Exempt based on article 132, section 1 (m) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1N' => 'Exempt based on article 132, section 1 (n) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1O' => 'Exempt based on article 132, section 1 (o) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1P' => 'Exempt based on article 132, section 1 (p) of Council Directive 2006/112/EC',
        'VATEX-EU-132-1Q' => 'Exempt based on article 132, section 1 (q) of Council Directive 2006/112/EC',
        'VATEX-EU-135-1' => 'Exempt based on article 135, section 1 of Council Directive 2006/112/EC',
        'VATEX-EU-143' => 'Exempt based on article 143 of Council Directive 2006/112/EC',
        'VATEX-EU-143-1A' => 'Exempt based on article 143, section 1 (a) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1B' => 'Exempt based on article 143, section 1 (b) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1C' => 'Exempt based on article 143, section 1 (c) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1D' => 'Exempt based on article 143, section 1 (d) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1E' => 'Exempt based on article 143, section 1 (e) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1F' => 'Exempt based on article 143, section 1 (f) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1FA' => 'Exempt based on article 143, section 1 (fa) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1G' => 'Exempt based on article 143, section 1 (g) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1H' => 'Exempt based on article 143, section 1 (h) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1I' => 'Exempt based on article 143, section 1 (i) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1J' => 'Exempt based on article 143, section 1 (j) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1K' => 'Exempt based on article 143, section 1 (k) of Council Directive 2006/112/EC',
        'VATEX-EU-143-1L' => 'Exempt based on article 143, section 1 (l) of Council Directive 2006/112/EC',
        'VATEX-EU-144' => 'Exempt based on article 144 of Council Directive 2006/112/EC',
        'VATEX-EU-146-1E' => 'Exempt based on article 146 section 1 (e) of Council Directive 2006/112/EC',
        'VATEX-EU-148' => 'Exempt based on article 148 of Council Directive 2006/112/EC',
        'VATEX-EU-148-A' => 'Exempt based on article 148, section (a) of Council Directive 2006/112/EC',
        'VATEX-EU-148-B' => 'Exempt based on article 148, section (b) of Council Directive 2006/112/EC',
        'VATEX-EU-148-C' => 'Exempt based on article 148, section (c) of Council Directive 2006/112/EC',
        'VATEX-EU-148-D' => 'Exempt based on article 148, section (d) of Council Directive 2006/112/EC',
        'VATEX-EU-148-E' => 'Exempt based on article 148, section (e) of Council Directive 2006/112/EC',
        'VATEX-EU-148-F' => 'Exempt based on article 148, section (f) of Council Directive 2006/112/EC',
        'VATEX-EU-148-G' => 'Exempt based on article 148, section (g) of Council Directive 2006/112/EC',
        'VATEX-EU-151' => 'Exempt based on article 151 of Council Directive 2006/112/EC',
        'VATEX-EU-151-1A' => 'Exempt based on article 151, section 1 (a) of Council Directive 2006/112/EC',
        'VATEX-EU-151-1AA' => 'Exempt based on article 151, section 1 (aa) of Council Directive 2006/112/EC',
        'VATEX-EU-151-1B' => 'Exempt based on article 151, section 1 (b) of Council Directive 2006/112/EC',
        'VATEX-EU-151-1C' => 'Exempt based on article 151, section 1 (c) of Council Directive 2006/112/EC',
        'VATEX-EU-151-1D' => 'Exempt based on article 151, section 1 (d) of Council Directive 2006/112/EC',
        'VATEX-EU-151-1E' => 'Exempt based on article 151, section 1 (e) of Council Directive 2006/112/EC',
        'VATEX-EU-153' => 'Exempt based on article 153 of Council Directive 2006/112/EC',
        'VATEX-EU-159' => 'Exempt based on article 159 of Council Directive 2006/112/EC',
        'VATEX-EU-309' => 'Exempt based on article 309 of Council Directive 2006/112/EC',
        'VATEX-EU-AE' => 'Reverse charge',
        'VATEX-EU-D' => 'Intra-Community acquisition from second hand means of transport',
        'VATEX-EU-F' => 'Intra-Community acquisition of second hand goods',
        'VATEX-EU-G' => 'Export outside the EU',
        'VATEX-EU-I' => 'Intra-Community acquisition of works of art',
        'VATEX-EU-IC' => 'Intra-Community supply',
        'VATEX-EU-J' => 'Intra-Community acquisition of collectors items and antiques',
        'VATEX-EU-O' => 'Not subject to VAT',
    ];

    /**
     * The French codes of the list, for a French seller. The package names them without a description.
     *
     * @var list<string>
     */
    private const FRENCH_CODES = [
        'VATEX-FR-298SEXDECIESA', 'VATEX-FR-AE', 'VATEX-FR-CGI261-1', 'VATEX-FR-CGI261-2', 'VATEX-FR-CGI261-3',
        'VATEX-FR-CGI261-4', 'VATEX-FR-CGI261-5', 'VATEX-FR-CGI261-7', 'VATEX-FR-CGI261-8', 'VATEX-FR-CGI261A',
        'VATEX-FR-CGI261B', 'VATEX-FR-CGI261C-1', 'VATEX-FR-CGI261C-2', 'VATEX-FR-CGI261C-3', 'VATEX-FR-CGI261D-1',
        'VATEX-FR-CGI261D-1BIS', 'VATEX-FR-CGI261D-2', 'VATEX-FR-CGI261D-3', 'VATEX-FR-CGI261D-4', 'VATEX-FR-CGI261E-1',
        'VATEX-FR-CGI261E-2', 'VATEX-FR-CGI275', 'VATEX-FR-CGI277A', 'VATEX-FR-CGI295', 'VATEX-FR-CNWVAT', 'VATEX-FR-FRANCHISE',
    ];

    /**
     * Whether the code is in the VATEX code list (BR-CL-22). Case insensitive.
     */
    public static function isKnown(string $code): bool
    {
        $code = strtoupper(trim($code));

        return isset(self::NAMES[$code]) || in_array($code, self::FRENCH_CODES, true);
    }

    /**
     * The name of a European code in the code list, or null for a French or unknown code.
     */
    public static function name(string $code): ?string
    {
        return self::NAMES[strtoupper(trim($code))] ?? null;
    }

    /**
     * The only category the code may be used with, or null when PEPPOL ties it to none.
     */
    public static function categoryOf(string $code): ?VatCategory
    {
        $category = self::CATEGORY_OF_CODE[strtoupper(trim($code))] ?? null;

        return $category === null ? null : VatCategory::from($category);
    }

    /**
     * Every code of the list.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_merge(array_keys(self::NAMES), self::FRENCH_CODES);
    }
}
