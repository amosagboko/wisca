<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\ServiceActivityType;
use App\Models\ServiceLog;
use App\Models\Term;
use App\Models\User;
use App\Services\ServiceHoursCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoServiceSeeder extends Seeder
{
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

        Role::firstOrCreate(['name' => 'student_life_coordinator', 'guard_name' => 'web']);

        $coordinator = User::firstOrCreate(
            ['email' => 'slc@wisca.test'],
            [
                'name' => 'Mrs. Grace Benson',
                'school_id' => $school->id,
                'password' => Hash::make('password'),
            ]
        );
        $coordinator->assignRole('student_life_coordinator');

        $types = collect([
            ServiceActivityType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Community Outreach'],
                ['code' => 'OUTREACH', 'description' => 'Visits, donations, and outreach into the local community.', 'display_order' => 1, 'is_active' => true]
            ),
            ServiceActivityType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Peer Tutoring'],
                ['code' => 'TUTOR', 'description' => 'Learners supporting peers academically or socially.', 'display_order' => 2, 'is_active' => true]
            ),
            ServiceActivityType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Campus Service'],
                ['code' => 'CAMPUS', 'description' => 'Structured school clean-up and care activities.', 'display_order' => 3, 'is_active' => true]
            ),
        ]);

        $rows = [
            ['type' => 'Community Outreach', 'date' => now()->subDays(28)->toDateString(), 'title' => 'Hospital encouragement visit', 'participants' => 12, 'hours' => 24, 'status' => 'verified'],
            ['type' => 'Peer Tutoring', 'date' => now()->subDays(21)->toDateString(), 'title' => 'After-school literacy tutoring', 'participants' => 10, 'hours' => 18, 'status' => 'verified'],
            ['type' => 'Campus Service', 'date' => now()->subDays(14)->toDateString(), 'title' => 'Campus sanitation and gardening', 'participants' => 16, 'hours' => 30, 'status' => 'verified'],
            ['type' => 'Community Outreach', 'date' => now()->subDays(7)->toDateString(), 'title' => 'Food drive sorting and packaging', 'participants' => 9, 'hours' => 23, 'status' => 'verified'],
            ['type' => 'Peer Tutoring', 'date' => now()->subDays(4)->toDateString(), 'title' => 'Maths support circle', 'participants' => 8, 'hours' => 12, 'status' => 'submitted'],
            ['type' => 'Campus Service', 'date' => now()->subDays(2)->toDateString(), 'title' => 'Library shelf re-ordering', 'participants' => 6, 'hours' => 6, 'status' => 'rejected'],
        ];

        foreach ($rows as $row) {
            $type = $types->firstWhere('name', $row['type']);

            ServiceLog::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term?->id,
                    'title' => $row['title'],
                ],
                [
                    'service_activity_type_id' => $type->id,
                    'service_date' => $row['date'],
                    'description' => $row['title'],
                    'participant_count' => $row['participants'],
                    'verified_hours' => $row['hours'],
                    'status' => $row['status'],
                    'submitted_by' => $coordinator->id,
                    'verified_by' => in_array($row['status'], ['verified', 'rejected'], true) ? $coordinator->id : null,
                    'verified_at' => in_array($row['status'], ['verified', 'rejected'], true) ? now() : null,
                ]
            );
        }

        app(ServiceHoursCalculationService::class)->recalculateForSession($session, $term);
    }
}
