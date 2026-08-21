<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\InterventionPlan;
use App\Models\Learner;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\AtRiskCalculationService;
use App\Support\AtRiskCriteria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoAtRiskSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'wca-makurdi')->first();
        if (! $school) {
            return;
        }

        Role::firstOrCreate(['name' => 'learning_support_coordinator', 'guard_name' => 'web']);

        $coordinator = User::firstOrCreate(
            ['email' => 'support@wisca.test'],
            [
                'school_id' => $school->id,
                'name' => 'Learning Support Coordinator',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );
        if (! $coordinator->hasRole('learning_support_coordinator')) {
            $coordinator->assignRole('learning_support_coordinator');
        }

        $session = AcademicSession::currentForSchool($school->id);
        $term = $session ? Term::currentForSession($session->id) : null;
        $learner = Learner::where('school_id', $school->id)
            ->where('admission_no', 'WCA/2025/006')
            ->first();

        if (! $session || ! $term || ! $learner) {
            return;
        }

        $record = AtRiskLearner::firstOrCreate(
            [
                'learner_id' => $learner->id,
                'academic_session_id' => $session->id,
            ],
            [
                'school_class_id' => $learner->school_class_id,
                'term_id' => $term->id,
                'identified_by' => $coordinator->id,
                'identification_date' => $term->start_date->copy()->addWeeks(8)->toDateString(),
                'risk_factors' => [AtRiskCriteria::BELOW_PASS_MARK],
                'risk_level' => 'medium',
                'status' => 'active',
            ]
        );

        InterventionPlan::firstOrCreate(
            [
                'at_risk_learner_id' => $record->id,
                'plan_type' => AtRiskCriteria::TIER_2,
            ],
            [
                'coordinator_id' => $coordinator->id,
                'objectives' => 'Raise Mathematics term performance to at least 50% through targeted small-group practice on number operations.',
                'strategies' => "Three 20-minute support sessions each week with the class teacher.\nParent check-in on homework completion every Friday.\nRe-assess with a short diagnostic before the next review date.",
                'start_date' => $record->identification_date,
                'review_date' => $term->end_date->toDateString(),
                'status' => 'active',
            ]
        );

        app(AtRiskCalculationService::class)->recalculateForSession($session, $term);
    }
}
