<?php

declare(strict_types=1);

namespace Darvis\UblPeppol\Console;

use Darvis\UblPeppol\Models\PeppolLog;
use Darvis\UblPeppol\UblPeppolConfig;
use Illuminate\Console\Command;

class CleanupPeppolLogsCommand extends Command
{
    protected $signature = 'peppol:cleanup {--days= : Number of days to keep logs, defaults to the log_retention_days config}';

    protected $description = 'Delete Peppol logs older than the configured number of days';

    public function handle(): int
    {
        if (! PeppolLog::tableExists()) {
            $this->info('There is no peppol_logs table, so there is nothing to clean up. Publish it with: php artisan vendor:publish --tag=ubl-peppol-migrations');

            return self::SUCCESS;
        }

        $option = $this->option('days');

        // Without an explicit --days, follow the config. It used to be hard-coded to 60 here,
        // so raising log_retention_days had no effect on what this command actually deleted.
        $days = is_numeric($option) ? (int) $option : UblPeppolConfig::logRetentionDays();

        $this->info("Deleting Peppol logs older than {$days} days...");

        $count = PeppolLog::cleanupOldLogs($days);

        $this->info("✓ {$count} log(s) deleted.");

        return self::SUCCESS;
    }
}
