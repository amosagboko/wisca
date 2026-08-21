<?php

namespace App\Console\Commands;

use App\Services\ExcelParityService;
use Illuminate\Console\Command;

class ExcelParityCommand extends Command
{
    protected $signature = 'wisca:excel-parity
                            {--apply : Write Excel sample Actuals as school-wide KPI rows}
                            {--school= : School ID (defaults to active session school)}
                            {--session= : Academic session ID}';

    protected $description = 'Apply and/or verify Board Excel sample parity (§11)';

    public function handle(ExcelParityService $parity): int
    {
        $resolved = $parity->resolveSchoolSession(
            $this->option('school') ? (int) $this->option('school') : null,
            $this->option('session') ? (int) $this->option('session') : null,
        );

        $schoolId = $resolved['school_id'];
        $sessionId = $resolved['session_id'];

        $this->info("School #{$schoolId} · Session {$resolved['session']->name} (#{$sessionId})");

        if ($this->option('apply')) {
            $written = $parity->applySamples($schoolId, $sessionId);
            $this->info("Applied {$written->count()} Excel sample KPI rows.");
        }

        $result = $parity->verify($schoolId, $sessionId);

        if ($result['ok']) {
            $this->info('Board parity PASSED — KPI statuses, pillar headlines, and executive totals match Excel samples.');

            return self::SUCCESS;
        }

        $this->error('Board parity FAILED:');
        foreach ($result['failures'] as $failure) {
            $this->line('  · '.$failure);
        }
        $this->newLine();
        $this->comment('Tip: re-run with --apply to load Excel sample Actuals, then verify again.');

        return self::FAILURE;
    }
}
