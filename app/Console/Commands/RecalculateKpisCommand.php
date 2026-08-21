<?php

namespace App\Console\Commands;

use App\Services\KpiRecalculationService;
use Illuminate\Console\Command;

class RecalculateKpisCommand extends Command
{
    protected $signature = 'wisca:recalculate-kpis
                            {frequency=all : daily|weekly|fortnightly|monthly|termly|all|or a KPI code}
                            {--school= : Limit to one school ID}';

    protected $description = 'Recalculate KPI periodic data on the Excel frequency cadence';

    public function handle(KpiRecalculationService $recalc): int
    {
        $frequency = strtolower((string) $this->argument('frequency'));
        $schoolId = $this->option('school') ? (int) $this->option('school') : null;
        $sessions = $recalc->activeSessions($schoolId);

        if ($sessions->isEmpty()) {
            $this->warn('No active academic sessions found.');

            return self::SUCCESS;
        }

        foreach ($sessions as $session) {
            $done = $recalc->recalculate($session, $frequency);
            $this->info("Session {$session->name} (#{$session->id}): ".($done ? implode(', ', $done) : 'nothing matched'));
        }

        return self::SUCCESS;
    }
}
