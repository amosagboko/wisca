<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\LmsUsageLog;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\LmsAdoptionCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoLmsSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'wca-makurdi')->first();
        if (! $school) {
            return;
        }

        $session = AcademicSession::currentForSchool($school->id);
        $term = $session ? Term::currentForSession($session->id) : null;
        if (! $session) {
            return;
        }

        Role::firstOrCreate(['name' => 'it_consultant', 'guard_name' => 'web']);

        $it = User::firstOrCreate(
            ['email' => 'it@wisca.test'],
            [
                'name' => 'Mr. Daniel Ojo',
                'school_id' => $school->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $it->syncRoles(['it_consultant']);

        $weekStart = now()->startOfWeek()->toDateString();

        $staff = User::where('school_id', $school->id)->where('status', 'active')->orderBy('id')->get();
        foreach ($staff as $index => $member) {
            $active = $index % 8 !== 0;
            LmsUsageLog::updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'week_start_date' => $weekStart,
                    'user_id' => $member->id,
                ],
                [
                    'school_id' => $school->id,
                    'term_id' => $term?->id,
                    'actor_type' => 'staff',
                    'learner_id' => null,
                    'login_count' => $active ? rand(2, 8) : 0,
                    'activity_count' => $active ? rand(3, 12) : 0,
                    'is_active_weekly' => $active,
                    'recorded_by' => $it->id,
                ]
            );
        }

        $learners = Learner::where('school_id', $school->id)->where('status', 'enrolled')->orderBy('id')->get();
        foreach ($learners as $index => $learner) {
            $active = $index % 11 !== 0;
            LmsUsageLog::updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'week_start_date' => $weekStart,
                    'learner_id' => $learner->id,
                ],
                [
                    'school_id' => $school->id,
                    'term_id' => $term?->id,
                    'actor_type' => 'learner',
                    'user_id' => null,
                    'login_count' => $active ? rand(1, 6) : 0,
                    'activity_count' => $active ? rand(2, 10) : 0,
                    'is_active_weekly' => $active,
                    'recorded_by' => $it->id,
                ]
            );
        }

        app(LmsAdoptionCalculationService::class)->recalculateForWeek($session, $weekStart, $term);
    }
}
