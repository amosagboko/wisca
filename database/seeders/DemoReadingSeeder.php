<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\ReadingAssessment;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\ReadingCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoReadingSeeder extends Seeder
{
    /**
     * Seed demo reading assessments for AE-08.
     *
     * Baseline: start of session. Follow-up: current/end of term.
     * 8 of 10 learners reach ≥1.0 grade-level growth → AE-08 = 80% (below 85% target).
     */
    public function run(): void
    {
        $school = School::where('slug', 'wca-makurdi')->first();
        if (! $school) {
            return;
        }

        Role::firstOrCreate(['name' => 'literacy_coordinator', 'guard_name' => 'web']);

        $coordinator = User::firstOrCreate(
            ['email' => 'literacy@wisca.test'],
            [
                'school_id' => $school->id,
                'name' => 'Literacy Coordinator',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );
        if (! $coordinator->hasRole('literacy_coordinator')) {
            $coordinator->assignRole('literacy_coordinator');
        }

        $session = AcademicSession::currentForSchool($school->id);
        $term = $session ? Term::currentForSession($session->id) : null;

        if (! $session || ! $term) {
            return;
        }

        // Admission numbers match DemoExamResultsSeeder
        $assessmentData = [
            'WCA/2025/001' => ['baseline' => 3.2, 'followup' => 4.5],  // +1.3 ✓
            'WCA/2025/002' => ['baseline' => 2.8, 'followup' => 4.0],  // +1.2 ✓
            'WCA/2025/003' => ['baseline' => 4.0, 'followup' => 5.1],  // +1.1 ✓
            'WCA/2025/004' => ['baseline' => 3.5, 'followup' => 4.6],  // +1.1 ✓
            'WCA/2025/005' => ['baseline' => 2.5, 'followup' => 3.6],  // +1.1 ✓
            'WCA/2025/006' => ['baseline' => 2.0, 'followup' => 2.8],  // +0.8 ✗ (already at-risk)
            'WCA/2025/007' => ['baseline' => 3.8, 'followup' => 5.0],  // +1.2 ✓
            'WCA/2025/008' => ['baseline' => 4.1, 'followup' => 5.2],  // +1.1 ✓
            'WCA/2025/009' => ['baseline' => 2.2, 'followup' => 3.0],  // +0.8 ✗
            'WCA/2025/010' => ['baseline' => 3.0, 'followup' => 4.2],  // +1.2 ✓
        ];

        $baselineDate = $term->start_date->toDateString();
        $followupDate = $term->end_date->copy()->subWeeks(2)->toDateString();

        foreach ($assessmentData as $admissionNo => $data) {
            $learner = Learner::where('school_id', $school->id)
                ->where('admission_no', $admissionNo)
                ->first();

            if (! $learner) {
                continue;
            }

            ReadingAssessment::updateOrCreate(
                [
                    'learner_id'          => $learner->id,
                    'academic_session_id' => $session->id,
                ],
                [
                    'school_class_id'     => $learner->school_class_id,
                    'term_id'             => $term->id,
                    'recorded_by'         => $coordinator->id,
                    'baseline_level'      => $data['baseline'],
                    'followup_level'      => $data['followup'],
                    'baseline_checkpoint' => 'start_of_session',
                    'followup_checkpoint' => 'end_of_session',
                    'baseline_date'       => $baselineDate,
                    'followup_date'       => $followupDate,
                ]
            );
        }

        app(ReadingCalculationService::class)->recalculateForSession($session, $term);
    }
}
