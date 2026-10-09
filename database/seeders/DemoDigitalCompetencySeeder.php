<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\DigitalCompetencyArea;
use App\Models\DigitalCompetencyRating;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\DigitalCompetencyCalculationService;
use Illuminate\Database\Seeder;

class DemoDigitalCompetencySeeder extends Seeder
{
    /**
     * Seeds DI-04 demo data:
     *  - 4 competency matrix areas
     *  - ratings for all active staff (~78% Level 3+ to match Excel sample direction)
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

        $assessor = User::where('email', 'hos@wisca.test')->first()
            ?? User::where('school_id', $school->id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['head_of_school', 'admin', 'ict_coordinator', 'it_consultant']))->first()
            ?? User::where('school_id', $school->id)->first();

        if (! $assessor) {
            return;
        }

        $areas = collect([
            DigitalCompetencyArea::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'LMS'],
                ['name' => 'LMS tool integration', 'description' => 'Using the portal/LMS for teaching workflows.', 'passing_level' => 3, 'display_order' => 1, 'is_active' => true]
            ),
            DigitalCompetencyArea::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'ASSESS'],
                ['name' => 'E-assessment & feedback', 'description' => 'Creating and marking digital assessments.', 'passing_level' => 3, 'display_order' => 2, 'is_active' => true]
            ),
            DigitalCompetencyArea::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'BOARD'],
                ['name' => 'Smart board / classroom tech', 'description' => 'Confident interactive board and AV use.', 'passing_level' => 3, 'display_order' => 3, 'is_active' => true]
            ),
            DigitalCompetencyArea::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'SAFE'],
                ['name' => 'Digital safety & ethics', 'description' => 'Safeguarding, password hygiene, and AI ethics awareness.', 'passing_level' => 3, 'display_order' => 4, 'is_active' => true]
            ),
        ]);

        $staff = User::where('school_id', $school->id)->where('status', 'active')->orderBy('id')->get();

        foreach ($staff as $index => $member) {
            $proficient = $index % 5 !== 0; // ~80%

            foreach ($areas as $areaIndex => $area) {
                $level = $proficient
                    ? ($areaIndex % 2 === 0 ? 3 : 4)
                    : ($areaIndex === 0 ? 2 : 3);

                DigitalCompetencyRating::updateOrCreate(
                    [
                        'user_id' => $member->id,
                        'digital_competency_area_id' => $area->id,
                        'academic_session_id' => $session->id,
                        'term_id' => $term->id,
                    ],
                    [
                        'school_id' => $school->id,
                        'level' => $level,
                        'assessed_on' => now()->subDays(rand(5, 30))->toDateString(),
                        'notes' => $proficient ? 'Meets Level 3+ expectation.' : 'Needs coaching on LMS workflows.',
                        'assessed_by' => $assessor->id,
                    ]
                );
            }
        }

        app(DigitalCompetencyCalculationService::class)->recalculateForSession($session, $term);
    }
}
