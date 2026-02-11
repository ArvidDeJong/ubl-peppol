<?php

namespace Darvis\UblPeppol\Validation;

/**
 * Trait for tracking codes and managing strict codelist validation
 *
 * This trait consolidates common code-tracking functionality used by both
 * UblBeBis3Service and UblNlBis3Service to eliminate duplication.
 *
 * @package Darvis\UblPeppol\Validation
 */
trait ValidationTrackingTrait
{
    /**
     * @var array Tracks all used currency codes in the document
     */
    protected array $usedCurrencyCodes = [];

    /**
     * @var array Tracks all used scheme IDs (mainly for receiver identification)
     */
    protected array $usedSchemeIds = [];

    /**
     * @var array Tracks all used endpoint scheme IDs
     */
    protected array $usedEndpointSchemeIds = [];

    /**
     * @var array Tracks all used party scheme IDs (supplier/customer identifiers)
     */
    protected array $usedPartySchemeIds = [];

    /**
     * @var array Tracks all used registration scheme IDs
     */
    protected array $usedRegistrationSchemeIds = [];

    /**
     * @var array Tracks all used payment means codes
     */
    protected array $usedPaymentMeansCodes = [];

    /**
     * @var array Tracks all used unit codes
     */
    protected array $usedUnitCodes = [];

    /**
     * @var array Tracks all used tax category IDs
     */
    protected array $usedTaxCategoryIds = [];

    /**
     * @var bool Enable strict validation against official codelists
     */
    protected bool $strictCodelistValidation = false;

    /**
     * @var CodelistRegistry|null Registry of official codelists when strict validation is enabled
     */
    protected ?CodelistRegistry $codelistRegistry = null;

    /**
     * Enable strict validation against official PEPPOL codelists
     *
     * When enabled, the validate() method will check all used codes against
     * the official codelist registry (e.g., ISO 4217 for currencies, EAS for identifiers).
     *
     * @param  string|null  $jsonPath  Path to JSON codelists file or directory
     * @param  CodelistRegistry|null  $registry  Pre-loaded registry instance
     * @return self
     *
     * @example
     * // Load from JSON file
     * $service->enableStrictCodelistValidation('/path/to/codelists.json');
     *
     * // Use pre-loaded registry
     * $registry = CodelistRegistry::fromJsonFile('codelists.json');
     * $service->enableStrictCodelistValidation(null, $registry);
     */
    public function enableStrictCodelistValidation(?string $jsonPath = null, ?CodelistRegistry $registry = null): self
    {
        if ($registry) {
            $this->codelistRegistry = $registry;
        } elseif ($jsonPath) {
            $this->codelistRegistry = CodelistRegistry::fromJsonFile($jsonPath);
        }

        $this->strictCodelistValidation = true;

        return $this;
    }
}
