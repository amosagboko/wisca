<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Learner;
use App\Models\School;
use App\Models\ScriptureAssessment;
use App\Models\ScripturePassage;
use App\Models\Term;
use App\Models\User;
use App\Services\ScriptureCalculationService;
use Illuminate\Database\Seeder;

class DemoScriptureSeeder extends Seeder
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

        $passages = collect([
            ScripturePassage::firstOrCreate(
                ['school_id' => $school->id, 'reference' => 'Romans 12:2'],
                ['verse_text' => 'Do not conform to the pattern of this world...', 'theme' => 'Transformation', 'display_order' => 1, 'is_active' => true]
            ),
            ScripturePassage::firstOrCreate(
                ['school_id' => $school->id, 'reference' => 'Philippians 4:13'],
                ['verse_text' => 'I can do all things through Christ...', 'theme' => 'Strength in Christ', 'display_order' => 2, 'is_active' => true]
            ),
        ]);

        $assessor = User::where('school_id', $school->id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['chaplain', 'teacher', 'head_of_school']))->first()
            ?? User::where('school_id', $school->id)->first();

        $learners = Learner::where('school_id', $school->id)->where('status', 'enrolled')->with('schoolClass')->limit(10)->get();
        if (! $assessor || $learners->isEmpty()) {
            return;
        }

        foreach ($learners as $index => $learner) {
            foreach ($passages as $passage) {
                $recites = $index % 5 !== 0;
                $explains = $index % 4 !== 0;

                ScriptureAssessment::updateOrCreate(
                    [
                        'learner_id' => $learner->id,
                        'scripture_passage_id' => $passage->id,
                        'academic_session_id' => $session->id,
                        'term_id' => $term->id,
                    ],
                    [
                        'school_id' => $school->id,
                        'school_class_id' => $learner->school_class_id,
                        'assessed_on' => now()->subDays(rand(3, 25))->toDateString(),
                        'recites_correctly' => $recites,
                        'explains_contextually' => $explains,
                        'notes' => ($recites && $explains) ? 'Clear recitation and application.' : 'Needs more coaching on application.',
                        'assessed_by' => $assessor->id,
                    ]
                );
            }
        }

        app(ScriptureCalculationService::class)->recalculateForSession($session, $term);
    }
}
