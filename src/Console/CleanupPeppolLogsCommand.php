<?php

namespace Darvis\UblPeppol\Console;

use Darvis\UblPeppol\Models\PeppolLog;
use Illuminate\Console\Command;

class CleanupPeppolLogsCommand extends Command
{
    protected $signature = 'peppol:cleanup {--days=60 : Number of days to keep logs}';

    protected $description = 'Delete Peppol logs older than the specified number of days (default 60)';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Deleting Peppol logs older than {$days} days...");

        $count = PeppolLog::cleanupOldLogs($days);

        $this->info("✓ {$count} log(s) deleted.");

        return self::SUCCESS;
    }
}
