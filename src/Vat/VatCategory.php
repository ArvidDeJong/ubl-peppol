<?php

declare(strict_types=1);

namespace Darvis\UblPeppol\Vat;

/**
 * The VAT categories of PEPPOL BIS Billing 3.0 (UNCL5305), with what each one means, when to use it
 * and what the rules demand of a document that uses it.
 *
 * The category goes into every `tax_category_id` of the builders: the lines (BT-151), the document
 * level allowances and charges (BT-95, BT-102) and the VAT breakdown (BT-118). The names and the
 * rules come from the official code list and the EN 16931 Schematron:
 * https://docs.peppol.eu/poacc/billing/3.0/codelist/UNCL5305/
 *
 * Choosing the category is a tax decision, not a technical one. This enum explains the options; the
 * bookkeeper of the seller decides which one applies.
 *
 * Code B (split payment) is left out on purpose: EN 16931 allows it for domestic Italian invoices only.
 */
enum VatCategory: string
{
    case StandardRate = 'S';
    case ZeroRated = 'Z';
    case Exempt = 'E';
    case ReverseCharge = 'AE';
    case IntraCommunitySupply = 'K';
    case ExportOutsideEu = 'G';
    case NotSubjectToVat = 'O';
    case CanaryIslands = 'L';
    case CeutaMelilla = 'M';

    /**
     * Find the category for a code such as `'K'`, in any case, or null when PEPPOL does not know it.
     */
    public static function fromCode(string $code): ?self
    {
        return self::tryFrom(strtoupper(trim($code)));
    }

    /**
     * The code of a numbered EN 16931 rule of this category: ruleId(10) is 'BR-IC-10' for K.
     * The rules of each category are numbered alike, 01 to 10.
     */
    public function ruleId(int $number): string
    {
        $prefix = match ($this) {
            self::IntraCommunitySupply => 'IC',
            self::CanaryIslands => 'AF',
            self::CeutaMelilla => 'AG',
            default => $this->value,
        };

        return sprintf('BR-%s-%02d', $prefix, $number);
    }

    /**
     * The name in the UNCL5305 code list.
     */
    public function label(): string
    {
        return match ($this) {
            self::StandardRate => 'Standard rate',
            self::ZeroRated => 'Zero rated goods',
            self::Exempt => 'Exempt from tax',
            self::ReverseCharge => 'VAT reverse charge',
            self::IntraCommunitySupply => 'VAT exempt for EEA intra-community supply of goods and services',
            self::ExportOutsideEu => 'Free export item, VAT not charged',
            self::NotSubjectToVat => 'Services outside scope of tax',
            self::CanaryIslands => 'Canary Islands general indirect tax (IGIC)',
            self::CeutaMelilla => 'Tax for production, services and importation in Ceuta and Melilla (IPSI)',
        };
    }

    /**
     * When the category applies, in plain words.
     */
    public function description(): string
    {
        return match ($this) {
            self::StandardRate => 'The normal case: the seller charges VAT at a rate above zero, such as 21% or 9% in the Netherlands and 21%, 12% or 6% in Belgium.',
            self::ZeroRated => 'The supply is taxed, but at a rate of 0% by law. Rare in the Netherlands and Belgium; it is not the same as exempt or reverse charge.',
            self::Exempt => 'The supply is exempt from VAT by law, for example medical care, education or financial services. Always state the legal ground: an exemption reason code such as VATEX-EU-132-1C or a text.',
            self::ReverseCharge => 'The buyer, not the seller, accounts for the VAT. Used for a service to a business in another EU country and for domestic reverse charge schemes such as subcontracting in construction. Invoice text: "Reverse charge" (Dutch: "Btw verlegd").',
            self::IntraCommunitySupply => 'A supply to a VAT registered business in another EU or EEA country, VAT exempt in the country of the seller. The buyer declares the acquisition in their own country. Needs the VAT numbers of both parties, the delivery date and the country the goods go to.',
            self::ExportOutsideEu => 'Goods leave the EU, for example to the United Kingdom, Switzerland or Norway. VAT is not charged; keep the export proof.',
            self::NotSubjectToVat => 'The transaction falls outside the scope of VAT altogether, for example a service whose place of supply lies outside the EU, or a seller who is not a VAT payer. A document with O may hold no other category.',
            self::CanaryIslands => 'Spanish indirect tax of the Canary Islands, in place of VAT. Only for supplies taxed there.',
            self::CeutaMelilla => 'Spanish indirect tax of Ceuta and Melilla, in place of VAT. Only for supplies taxed there.',
        };
    }

    /**
     * Whether the VAT breakdown of this category must carry an exemption reason code or text
     * (BR-E-10, BR-AE-10, BR-IC-10, BR-G-10, BR-O-10).
     */
    public function requiresExemptionReason(): bool
    {
        return match ($this) {
            self::Exempt, self::ReverseCharge, self::IntraCommunitySupply, self::ExportOutsideEu, self::NotSubjectToVat => true,
            default => false,
        };
    }

    /**
     * Whether the VAT breakdown of this category may carry no exemption reason at all
     * (BR-S-10, BR-Z-10, BR-AF-10, BR-AG-10).
     */
    public function forbidsExemptionReason(): bool
    {
        return ! $this->requiresExemptionReason();
    }

    /**
     * The exemption reason code (BT-121) that belongs to this category, or null when there is no
     * single one. PEPPOL-EN16931-P0104 to P0107 tie these codes to exactly this category.
     *
     * Exempt (E) has none: the code names the article of the VAT directive, and only the seller
     * knows which one applies.
     */
    public function defaultExemptionReasonCode(): ?string
    {
        return match ($this) {
            self::ReverseCharge => VatExemptionReason::REVERSE_CHARGE,
            self::IntraCommunitySupply => VatExemptionReason::INTRA_COMMUNITY_SUPPLY,
            self::ExportOutsideEu => VatExemptionReason::EXPORT_OUTSIDE_EU,
            self::NotSubjectToVat => VatExemptionReason::NOT_SUBJECT_TO_VAT,
            default => null,
        };
    }

    /**
     * The standard text for the exemption reason (BT-120) that the rules name, for the languages of
     * the countries this package builds for. The rules accept "the equivalent standard text in
     * another language". Null when the category has no standard text.
     *
     * @param  string  $language  'en', 'nl' or 'fr'; any other language gives the English text
     */
    public function exemptionReasonText(string $language = 'en'): ?string
    {
        $texts = match ($this) {
            self::ReverseCharge => ['en' => 'Reverse charge', 'nl' => 'Btw verlegd', 'fr' => 'Autoliquidation'],
            self::IntraCommunitySupply => ['en' => 'Intra-community supply', 'nl' => 'Intracommunautaire levering', 'fr' => 'Livraison intracommunautaire'],
            self::ExportOutsideEu => ['en' => 'Export outside the EU', 'nl' => 'Uitvoer buiten de EU', 'fr' => 'Exportation hors UE'],
            self::NotSubjectToVat => ['en' => 'Not subject to VAT', 'nl' => 'Niet onderworpen aan btw', 'fr' => 'Non soumis à la TVA'],
            default => null,
        };

        if ($texts === null) {
            return null;
        }

        return $texts[strtolower($language)] ?? $texts['en'];
    }

    /**
     * Whether the VAT rate of a line and of the breakdown must be 0 (BR-Z-05, BR-E-05, BR-AE-05,
     * BR-IC-05, BR-G-05). Category O carries no rate at all (BR-O-05).
     */
    public function requiresZeroRate(): bool
    {
        return match ($this) {
            self::ZeroRated, self::Exempt, self::ReverseCharge, self::IntraCommunitySupply, self::ExportOutsideEu => true,
            default => false,
        };
    }

    /**
     * The rules of EN 16931 that a document with this category must meet on top of the rules for
     * every document, by rule code. The receiver's rejection names these codes.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::StandardRate => [
                'BR-S-02' => 'The seller VAT identifier (BT-31) or tax registration identifier (BT-32) is present.',
                'BR-S-05' => 'The VAT rate of a line is greater than zero.',
                'BR-S-10' => 'The VAT breakdown has no exemption reason.',
            ],
            self::ZeroRated => [
                'BR-Z-02' => 'The seller VAT identifier (BT-31) or tax registration identifier (BT-32) is present.',
                'BR-Z-05' => 'The VAT rate is 0.',
                'BR-Z-10' => 'The VAT breakdown has no exemption reason.',
            ],
            self::Exempt => [
                'BR-E-02' => 'The seller VAT identifier (BT-31) or tax registration identifier (BT-32) is present.',
                'BR-E-05' => 'The VAT rate is 0.',
                'BR-E-10' => 'The VAT breakdown has an exemption reason code (BT-121) or text (BT-120).',
            ],
            self::ReverseCharge => [
                'BR-AE-02' => 'The seller VAT identifier (BT-31) and the buyer VAT identifier (BT-48) or legal registration (BT-47) are present.',
                'BR-AE-05' => 'The VAT rate is 0.',
                'BR-AE-10' => 'The VAT breakdown has exemption reason code VATEX-EU-AE or the text "Reverse charge".',
            ],
            self::IntraCommunitySupply => [
                'BR-IC-02' => 'The seller VAT identifier (BT-31) and the buyer VAT identifier (BT-48) are present.',
                'BR-IC-05' => 'The VAT rate is 0.',
                'BR-IC-10' => 'The VAT breakdown has exemption reason code VATEX-EU-IC or the text "Intra-community supply".',
                'BR-IC-11' => 'The actual delivery date (BT-72) or the invoicing period (BG-14) is present.',
                'BR-IC-12' => 'The deliver to country code (BT-80) is present.',
            ],
            self::ExportOutsideEu => [
                'BR-G-02' => 'The seller VAT identifier (BT-31) is present.',
                'BR-G-05' => 'The VAT rate is 0.',
                'BR-G-10' => 'The VAT breakdown has exemption reason code VATEX-EU-G or the text "Export outside the EU".',
            ],
            self::NotSubjectToVat => [
                'BR-O-02' => 'Neither the seller VAT identifier (BT-31) nor the buyer VAT identifier (BT-48) is present.',
                'BR-O-05' => 'A line carries no VAT rate.',
                'BR-O-10' => 'The VAT breakdown has exemption reason code VATEX-EU-O or the text "Not subject to VAT".',
                'BR-O-11' => 'The document has no other VAT category.',
            ],
            self::CanaryIslands => [
                'BR-AF-05' => 'The VAT rate is 0 or greater.',
                'BR-AF-10' => 'The VAT breakdown has no exemption reason.',
            ],
            self::CeutaMelilla => [
                'BR-AG-05' => 'The VAT rate is 0 or greater.',
                'BR-AG-10' => 'The VAT breakdown has no exemption reason.',
            ],
        };
    }

    /**
     * Everything above for every category, for a select list or a help page in the host app.
     *
     * @return list<array{code: string, label: string, description: string, requires_exemption_reason: bool, default_exemption_reason_code: ?string, exemption_reason_text: ?string, requires_zero_rate: bool, rules: array<string, string>}>
     */
    public static function guide(string $language = 'en'): array
    {
        return array_map(fn (self $category) => [
            'code' => $category->value,
            'label' => $category->label(),
            'description' => $category->description(),
            'requires_exemption_reason' => $category->requiresExemptionReason(),
            'default_exemption_reason_code' => $category->defaultExemptionReasonCode(),
            'exemption_reason_text' => $category->exemptionReasonText($language),
            'requires_zero_rate' => $category->requiresZeroRate(),
            'rules' => $category->rules(),
        ], self::cases());
    }
}
