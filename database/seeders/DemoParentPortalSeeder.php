<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Guardian;
use App\Models\ParentPortalEngagement;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\ParentPortalCalculationService;
use Illuminate\Database\Seeder;

class DemoParentPortalSeeder extends Seeder
{
    /**
     * Seeds DI-06 demo data at ~82% active monthly parent portal engagement.
     */
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

        $recorder = User::where('email', 'it@wisca.test')->first()
            ?? User::where('school_id', $school->id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['ict_coordinator', 'it_consultant', 'admin', 'head_of_school']))->first()
            ?? User::where('school_id', $school->id)->first();

        if (! $recorder) {
            return;
        }

        $monthStart = now()->startOfMonth()->toDateString();

        $families = Guardian::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->whereHas('learners', fn ($q) => $q->where('status', 'enrolled'))
            ->orderBy('id')
            ->get();

        if ($families->isEmpty()) {
            return;
        }

        foreach ($families as $index => $guardian) {
            $active = $families->count() === 1
                ? true
                : $index < (int) ceil($families->count() * 0.82);

            ParentPortalEngagement::updateOrCreate(
                [
                    'parent_id' => $guardian->id,
                    'month_start_date' => $monthStart,
                ],
                [
                    'school_id' => $school->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term?->id,
                    'login_count' => $active ? rand(1, 8) : 0,
                    'is_active_monthly' => $active,
                    'last_login_at' => $active ? now()->subDays(rand(0, 20)) : null,
                    'notes' => $active ? 'Portal activity verified from analytics export.' : null,
                    'recorded_by' => $recorder->id,
                ]
            );
        }

        app(ParentPortalCalculationService::class)->recalculateForMonth($session, $monthStart, $term);
    }
}
