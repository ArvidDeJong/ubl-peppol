<?php

namespace Darvis\UblPeppol\Validation;

/**
 * Result object for invoice validation.
 * 
 * Contains validation status, errors, warnings, and suggested corrections.
 */
class InvoiceValidationResult
{
    /**
     * @param bool $isValid Whether the invoice passed validation
     * @param array $errors List of validation errors
     * @param array $warnings List of validation warnings (non-fatal)
     * @param array $corrections Suggested corrections to fix the errors
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly array $corrections = []
    ) {}

    /**
     * Check if validation passed
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Check if there are any errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get all errors as a formatted string
     */
    public function getErrorsAsString(string $separator = "\n"): string
    {
        return implode($separator, $this->errors);
    }

    /**
     * Get all warnings as a formatted string
     */
    public function getWarningsAsString(string $separator = "\n"): string
    {
        return implode($separator, $this->warnings);
    }

    /**
     * Get the suggested corrections
     */
    public function getCorrections(): array
    {
        return $this->corrections;
    }

    /**
     * Get a specific correction value
     */
    public function getCorrection(string $key): mixed
    {
        return $this->corrections[$key] ?? null;
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'corrections' => $this->corrections,
        ];
    }

    /**
     * Create a validation result from an exception
     */
    public static function fromException(\Throwable $e): self
    {
        return new self(
            isValid: false,
            errors: [$e->getMessage()],
            warnings: [],
            corrections: []
        );
    }

    /**
     * Create a successful validation result
     */
    public static function success(): self
    {
        return new self(isValid: true);
    }
}
