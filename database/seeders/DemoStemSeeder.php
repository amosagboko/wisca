<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\School;
use App\Models\StemProjectCompletion;
use App\Models\StemProjectType;
use App\Models\Term;
use App\Models\User;
use App\Services\StemProjectCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoStemSeeder extends Seeder
{
    /**
     * Seeds DI-02 demo data:
     *  - 3 STEM project types
     *  - 1 STEM coordinator user
     *  - ~87% completion rate against enrolled learners
     */
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

        Role::firstOrCreate(['name' => 'stem_coordinator', 'guard_name' => 'web']);

        $coordinator = User::firstOrCreate(
            ['email' => 'stem@wisca.test'],
            [
                'name' => 'Engr. Favour Ameh',
                'school_id' => $school->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $coordinator->syncRoles(['stem_coordinator']);

        $types = collect([
            StemProjectType::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'CODING'],
                ['name' => 'Coding project', 'description' => 'Approved coding challenge submission.', 'display_order' => 1, 'is_active' => true]
            ),
            StemProjectType::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'ROBOT'],
                ['name' => 'Robotics challenge', 'description' => 'Practical robotics build and demonstration.', 'display_order' => 2, 'is_active' => true]
            ),
            StemProjectType::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'DESIGN'],
                ['name' => 'Design challenge', 'description' => 'STEM design thinking project.', 'display_order' => 3, 'is_active' => true]
            ),
        ]);

        $learners = Learner::where('school_id', $school->id)
            ->where('status', 'enrolled')
            ->with('schoolClass')
            ->orderBy('id')
            ->get();

        if ($learners->isEmpty()) {
            return;
        }

        foreach ($learners as $index => $learner) {
            $completed = $index % 8 !== 0;
            $type = $types[$index % $types->count()];

            StemProjectCompletion::updateOrCreate(
                [
                    'learner_id' => $learner->id,
                    'stem_project_type_id' => $type->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                ],
                [
                    'school_id' => $school->id,
                    'school_class_id' => $learner->school_class_id,
                    'status' => $completed ? 'completed' : ($index % 3 === 0 ? 'in_progress' : 'not_started'),
                    'completed_on' => $completed ? now()->subDays(rand(3, 28))->toDateString() : null,
                    'score' => $completed ? rand(65, 98) : null,
                    'notes' => $completed ? 'Approved practical submission.' : null,
                    'assessed_by' => $coordinator->id,
                ]
            );
        }

        app(StemProjectCalculationService::class)->recalculateForSession($session, $term);
    }
}
