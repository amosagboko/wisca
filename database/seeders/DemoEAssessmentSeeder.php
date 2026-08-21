<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\Subject;
use App\Models\SubjectDigitalAssessment;
use App\Models\Term;
use App\Models\User;
use App\Services\EAssessmentCalculationService;
use Illuminate\Database\Seeder;

class DemoEAssessmentSeeder extends Seeder
{
    /**
     * Seeds DI-05 demo data at ~83% subject utilization.
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

        $verifier = User::where('email', 'hos@wisca.test')->first()
            ?? User::where('school_id', $school->id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['head_of_school', 'admin', 'it_consultant']))->first()
            ?? User::where('school_id', $school->id)->first();

        if (! $verifier) {
            return;
        }

        $subjects = Subject::where('school_id', $school->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($subjects->isEmpty()) {
            return;
        }

        $tools = ['Portal quizzes', 'E-portfolio journal', 'Google Classroom', 'LMS assignment bank'];

        foreach ($subjects as $index => $subject) {
            // Aim for ~83% utilization even with small subject counts.
            $utilizing = $subjects->count() === 1
                ? true
                : $index < (int) ceil($subjects->count() * 0.83);

            SubjectDigitalAssessment::updateOrCreate(
                [
                    'subject_id' => $subject->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                ],
                [
                    'school_id' => $school->id,
                    'uses_e_assessment' => $utilizing,
                    'uses_e_portfolio' => $utilizing && $index % 2 === 0,
                    'primary_tool' => $utilizing ? $tools[$index % count($tools)] : null,
                    'evidence_notes' => $utilizing ? 'Verified continuous assessment / portfolio activity on portal.' : null,
                    'verified_on' => $utilizing ? now()->subDays(rand(3, 20))->toDateString() : null,
                    'verified_by' => $utilizing ? $verifier->id : null,
                ]
            );
        }

        app(EAssessmentCalculationService::class)->recalculateForSession($session, $term);
    }
}
