<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ChapelActivityType;
use App\Models\ChapelAttendance;
use App\Models\ChapelSession;
use App\Models\Learner;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\ChapelCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoChapelSeeder extends Seeder
{
    /**
     * Seeds CE-01 demo data:
     *  - 2 activity types (Chapel Service + Morning Assembly), configurable
     *  - 1 chaplain user
     *  - 6 held sessions across the current term
     *  - ~90% participation rate → CE-01 ≈ 90%
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

        // Ensure chaplain role exists
        Role::firstOrCreate(['name' => 'chaplain', 'guard_name' => 'web']);

        // Create demo chaplain user
        $chaplain = User::firstOrCreate(
            ['email' => 'chaplain@wisca.test'],
            [
                'name'       => 'Rev. Samuel Akaa',
                'school_id'  => $school->id,
                'password'   => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $chaplain->syncRoles(['chaplain']);

        // Activity type 1: Chapel Service — active + leading count
        $chapelType = ChapelActivityType::firstOrCreate(
            ['school_id' => $school->id, 'code' => 'CHAPEL'],
            [
                'name'            => 'Chapel Service',
                'description'     => 'Weekly whole-school chapel. Participation at active level or above counts for CE-01.',
                'counting_levels' => ['active', 'leading'],
                'school_wide'     => true,
                'display_order'   => 1,
                'is_active'       => true,
            ]
        );

        // Activity type 2: Morning Assembly — passive + active + leading count
        $assemblyType = ChapelActivityType::firstOrCreate(
            ['school_id' => $school->id, 'code' => 'ASSEMBLY'],
            [
                'name'            => 'Morning Assembly',
                'description'     => 'Daily morning devotion and announcements. Present counts as passive; singing/praying as active.',
                'counting_levels' => ['passive', 'active', 'leading'],
                'school_wide'     => true,
                'display_order'   => 2,
                'is_active'       => true,
            ]
        );

        // Get all enrolled learners for this school
        $learners = Learner::where('school_id', $school->id)
            ->where('status', 'enrolled')
            ->get();

        if ($learners->isEmpty()) {
            return;
        }

        $roll = $learners->count();

        // Seed 6 sessions — mix of chapel and assembly, all in current term
        $sessions = [
            [
                'type'      => $chapelType,
                'date'      => now()->subWeeks(5)->toDateString(),
                'theme'     => 'Walking in Integrity',
                'scripture' => 'Proverbs 11:3',
                'rate'      => 0.93,   // 93% participation
            ],
            [
                'type'      => $assemblyType,
                'date'      => now()->subWeeks(4)->toDateString(),
                'theme'     => 'Excellence in All Things',
                'scripture' => 'Colossians 3:23',
                'rate'      => 0.88,
            ],
            [
                'type'      => $chapelType,
                'date'      => now()->subWeeks(3)->toDateString(),
                'theme'     => 'Serving One Another',
                'scripture' => 'Mark 10:45',
                'rate'      => 0.95,
            ],
            [
                'type'      => $assemblyType,
                'date'      => now()->subWeeks(2)->toDateString(),
                'theme'     => 'Faith Over Fear',
                'scripture' => 'Joshua 1:9',
                'rate'      => 0.90,
            ],
            [
                'type'      => $chapelType,
                'date'      => now()->subWeek()->toDateString(),
                'theme'     => 'Light of the World',
                'scripture' => 'Matthew 5:14',
                'rate'      => 0.92,
            ],
            [
                'type'      => $assemblyType,
                'date'      => now()->subDays(2)->toDateString(),
                'theme'     => 'Gratitude and Praise',
                'scripture' => 'Psalm 100:1',
                'rate'      => 0.87,
            ],
        ];

        foreach ($sessions as $def) {
            $cs = ChapelSession::firstOrCreate(
                [
                    'school_id'               => $school->id,
                    'chapel_activity_type_id' => $def['type']->id,
                    'session_date'            => $def['date'],
                ],
                [
                    'academic_session_id' => $session->id,
                    'term_id'             => $term?->id,
                    'theme'               => $def['theme'],
                    'scripture_reference' => $def['scripture'],
                    'led_by'              => $chaplain->id,
                    'status'              => 'held',
                    'notes'               => null,
                ]
            );

            // Skip if roll already taken
            if ($cs->attendances()->exists()) {
                continue;
            }

            $countingLevels = $def['type']->countingLevels();
            $countingCount  = (int) round($roll * $def['rate']);

            foreach ($learners as $idx => $learner) {
                $countForKpi = $idx < $countingCount;

                ChapelAttendance::create([
                    'chapel_session_id'  => $cs->id,
                    'learner_id'         => $learner->id,
                    'status'             => $countForKpi ? 'present' : 'absent',
                    'participation_level'=> $countForKpi ? ($countingLevels[0] ?? 'active') : 'passive',
                    'recorded_by'        => $chaplain->id,
                ]);
            }
        }

        // Recalculate CE-01
        app(ChapelCalculationService::class)->recalculateForSession($session, $term);
    }
}
