<?php

declare(strict_types=1);

namespace Darvis\UblPeppol;

/**
 * The one place that reads the package config. Everything else asks this class, so a key is
 * named once and a caller cannot quietly disagree with the config file about a default.
 *
 * Part of the Laravel layer: it uses the config() helper. The invoice builders never read
 * configuration at all, they take everything through their method parameters.
 */
final class UblPeppolConfig
{
    /**
     * Days a log row is kept before peppol:cleanup deletes it.
     */
    public static function logRetentionDays(): int
    {
        return (int) config('ubl-peppol.log_retention_days', 60);
    }

    /**
     * Endpoint of the PEPPOL access point provider. Empty when it is not configured.
     */
    public static function url(): string
    {
        return (string) config('ubl-peppol.url');
    }

    public static function username(): string
    {
        return (string) config('ubl-peppol.username');
    }

    public static function password(): string
    {
        return (string) config('ubl-peppol.password');
    }
}
