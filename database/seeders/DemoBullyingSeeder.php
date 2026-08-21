<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\BullyingCase;
use App\Models\BullyingCaseType;
use App\Models\Learner;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\BullyingCaseCalculationService;
use Illuminate\Database\Seeder;

class DemoBullyingSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'wca-makurdi')->first();
        if (! $school) {
            return;
        }

        $session = AcademicSession::currentForSchool($school->id);
        $term = $session ? Term::currentForSession($session->id) : null;
        if (! $session || ! $term) {
            return;
        }

        $types = collect([
            BullyingCaseType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Verbal Bullying'],
                ['code' => 'VERBAL', 'description' => 'Repeated insults, intimidation, or threats.', 'display_order' => 1, 'is_active' => true]
            ),
            BullyingCaseType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Physical Bullying'],
                ['code' => 'PHYSICAL', 'description' => 'Physical aggression or intimidation.', 'display_order' => 2, 'is_active' => true]
            ),
            BullyingCaseType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Cyberbullying'],
                ['code' => 'CYBER', 'description' => 'Digital harassment through messages or social channels.', 'display_order' => 3, 'is_active' => true]
            ),
        ]);

        $staff = User::where('school_id', $school->id)->orderBy('id')->first();
        $learners = Learner::where('school_id', $school->id)->where('status', 'enrolled')->limit(6)->get();
        if (! $staff || $learners->count() < 2) {
            return;
        }

        $rows = [
            ['title' => 'Repeated mocking during break', 'type' => 'Verbal Bullying', 'days_ago' => 26, 'status' => 'closed', 'plan' => true, 'severity' => 'medium'],
            ['title' => 'Threatening messages in class group', 'type' => 'Cyberbullying', 'days_ago' => 19, 'status' => 'closed', 'plan' => true, 'severity' => 'high'],
            ['title' => 'Physical intimidation near field', 'type' => 'Physical Bullying', 'days_ago' => 12, 'status' => 'investigating', 'plan' => true, 'severity' => 'high'],
            ['title' => 'Name-calling on school bus', 'type' => 'Verbal Bullying', 'days_ago' => 7, 'status' => 'reported', 'plan' => false, 'severity' => 'medium'],
        ];

        foreach ($rows as $index => $row) {
            $type = $types->firstWhere('name', $row['type']);
            $closed = $row['status'] === 'closed';

            BullyingCase::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                    'title' => $row['title'],
                ],
                [
                    'bullying_case_type_id' => $type->id,
                    'target_learner_id' => $learners[$index % $learners->count()]->id,
                    'reported_by_learner_id' => $learners[($index + 1) % $learners->count()]->id,
                    'reported_on' => now()->subDays($row['days_ago'])->toDateString(),
                    'description' => $row['title'],
                    'severity' => $row['severity'],
                    'status' => $row['status'],
                    'safety_plan_created' => $row['plan'],
                    'safety_plan' => $row['plan'] ? 'Adult supervision, check-ins, seating changes, and parent contact.' : null,
                    'safety_plan_created_at' => $row['plan'] ? now()->subDays(max(1, $row['days_ago'] - 1)) : null,
                    'closed_at' => $closed ? now()->subDays(max(0, $row['days_ago'] - 2)) : null,
                    'reported_by' => $staff->id,
                    'closed_by' => $closed ? $staff->id : null,
                ]
            );
        }

        app(BullyingCaseCalculationService::class)->recalculateForSession($session, $term);
    }
}
