<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\CharacterDomain;
use App\Models\CharacterRating;
use App\Models\Learner;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\CharacterCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoCharacterSeeder extends Seeder
{
    /**
     * Seeds CE-02 demo data:
     *  - 7 character domains (Faith, Integrity, Excellence, Service, Respect, Responsibility, Compassion)
     *  - 1 student_life_coordinator user
     *  - Ratings for all enrolled learners → ~80% pass CE-02
     */
    public function run(): void
    {
        $school = School::where('slug', 'wca-makurdi')->first();
        if (! $school) {
            return;
        }

        $session = AcademicSession::currentForSchool($school->id);
        if (! $session) {
            return;
        }

        $term = Term::currentForSession($session->id);

        // Ensure role exists
        Role::firstOrCreate(['name' => 'student_life_coordinator', 'guard_name' => 'web']);

        // Create demo coordinator user
        $coordinator = User::firstOrCreate(
            ['email' => 'slc@wisca.test'],
            [
                'name'      => 'Mrs. Grace Benson',
                'school_id' => $school->id,
                'password'  => Hash::make('password'),
            ]
        );
        $coordinator->assignRole('student_life_coordinator');

        // Seed 7 character domains
        $domainDefs = [
            ['name' => 'Faith',          'code' => 'FAITH',    'order' => 1],
            ['name' => 'Integrity',      'code' => 'INTEGRITY','order' => 2],
            ['name' => 'Excellence',     'code' => 'EXCEL',    'order' => 3],
            ['name' => 'Service',        'code' => 'SERVICE',  'order' => 4],
            ['name' => 'Respect',        'code' => 'RESPECT',  'order' => 5],
            ['name' => 'Responsibility', 'code' => 'RESP',     'order' => 6],
            ['name' => 'Compassion',     'code' => 'COMP',     'order' => 7],
        ];

        $domains = collect();
        foreach ($domainDefs as $def) {
            $domains->push(CharacterDomain::firstOrCreate(
                ['school_id' => $school->id, 'name' => $def['name']],
                [
                    'code'          => $def['code'],
                    'description'   => "CE-02 domain: {$def['name']}",
                    'rubric'        => CharacterDomain::DEFAULT_RUBRIC,
                    'passing_level' => 3,
                    'display_order' => $def['order'],
                    'status'        => 'active',
                ]
            ));
        }

        $learners = Learner::where('school_id', $school->id)
            ->where('status', 'enrolled')
            ->get();

        if ($learners->isEmpty()) {
            return;
        }

        // Give ~80% of learners Secure (3) or Exemplary (4) on all domains, rest lower on at least one
        $passingCount = (int) ceil($learners->count() * 0.80);

        foreach ($learners as $i => $learner) {
            $shouldPass = $i < $passingCount;

            foreach ($domains as $domain) {
                if ($shouldPass) {
                    $level = fake()->randomElement([3, 3, 3, 4]); // bias toward Secure/Exemplary
                } else {
                    // Fail at least one domain by giving level 1 or 2; rest may still be passing
                    $level = ($domain->display_order === 1)
                        ? fake()->randomElement([1, 2])
                        : fake()->randomElement([2, 3, 4]);
                }

                CharacterRating::updateOrCreate(
                    [
                        'learner_id'          => $learner->id,
                        'character_domain_id' => $domain->id,
                        'academic_session_id' => $session->id,
                        'term_id'             => $term?->id,
                    ],
                    [
                        'level'    => $level,
                        'rated_by' => $coordinator->id,
                    ]
                );
            }
        }

        // Recalculate CE-02
        app(CharacterCalculationService::class)->recalculateForSession($session, $term);
    }
}
