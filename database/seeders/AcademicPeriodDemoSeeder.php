<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\Term;
use Illuminate\Database\Seeder;

class AcademicPeriodDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (School::all() as $school) {
            $this->ensureUpcomingTerms($school);
            $this->ensurePriorClosedSession($school);
        }
    }

    protected function ensureUpcomingTerms(School $school): void
    {
        $session = AcademicSession::currentForSchool($school->id)
            ?? AcademicSession::where('school_id', $school->id)->where('name', '2025/2026')->first();

        if (! $session) {
            return;
        }

        $defaults = [
            2 => ['name' => 'Second Term', 'start_date' => '2026-01-07', 'end_date' => '2026-04-10'],
            3 => ['name' => 'Third Term', 'start_date' => '2026-04-20', 'end_date' => '2026-07-31'],
        ];

        foreach ($defaults as $sequence => $row) {
            $exists = Term::where('academic_session_id', $session->id)->where('sequence', $sequence)->exists();
            if ($exists) {
                continue;
            }

            Term::create([
                'academic_session_id' => $session->id,
                'name' => $row['name'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'sequence' => $sequence,
                'status' => 'upcoming',
                'is_current' => false,
            ]);
        }
    }

    protected function ensurePriorClosedSession(School $school): void
    {
        $prior = AcademicSession::where('school_id', $school->id)->where('name', '2024/2025')->first();

        if (! $prior) {
            $prior = AcademicSession::create([
                'school_id' => $school->id,
                'name' => '2024/2025',
                'start_date' => '2024-09-01',
                'end_date' => '2025-07-31',
                'is_current' => false,
                'status' => 'closed',
            ]);
        }

        foreach ([
            [1, 'First Term', '2024-09-01', '2024-12-13'],
            [2, 'Second Term', '2025-01-08', '2025-04-11'],
            [3, 'Third Term', '2025-04-21', '2025-07-31'],
        ] as [$sequence, $name, $start, $end]) {
            if (Term::where('academic_session_id', $prior->id)->where('sequence', $sequence)->exists()) {
                continue;
            }

            Term::create([
                'academic_session_id' => $prior->id,
                'name' => $name,
                'start_date' => $start,
                'end_date' => $end,
                'sequence' => $sequence,
                'status' => 'closed',
                'is_current' => false,
            ]);
        }
    }
}
