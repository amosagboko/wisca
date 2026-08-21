<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\DigitalEthicsAudit;
use App\Models\DigitalEthicsAuditType;
use App\Models\Learner;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\DigitalEthicsCalculationService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoDigitalEthicsSeeder extends Seeder
{
    /**
     * Seeds DI-03 demo data:
     *  - 3 audit types
     *  - ~25 audited assignments with ~96% compliance
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

        Role::firstOrCreate(['name' => 'it_consultant', 'guard_name' => 'web']);

        $auditor = User::where('email', 'it@wisca.test')->first()
            ?? User::where('school_id', $school->id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['it_consultant', 'admin', 'head_of_school']))->first()
            ?? User::where('school_id', $school->id)->first();

        if (! $auditor) {
            return;
        }

        $types = collect([
            DigitalEthicsAuditType::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'AI'],
                ['name' => 'AI disclosure check', 'description' => 'Checks for disclosed vs uncredited AI use.', 'display_order' => 1, 'is_active' => true]
            ),
            DigitalEthicsAuditType::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'CYBER'],
                ['name' => 'Cybersecurity guideline check', 'description' => 'Checks for unsafe file sharing and credential misuse.', 'display_order' => 2, 'is_active' => true]
            ),
            DigitalEthicsAuditType::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'INTEGRITY'],
                ['name' => 'Academic integrity digital audit', 'description' => 'Spot checks of digital submissions against integrity policy.', 'display_order' => 3, 'is_active' => true]
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

        $titles = [
            'Digital citizenship essay',
            'Coding lab reflection',
            'STEM design write-up',
            'Online research summary',
            'E-portfolio artefact',
        ];

        foreach ($learners as $index => $learner) {
            // Create 2–3 audits per learner for a healthier audited denominator
            foreach ([0, 1] as $offset) {
                $compliant = ! (($index + $offset) % 25 === 0);
                $type = $types[($index + $offset) % $types->count()];

                DigitalEthicsAudit::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'learner_id' => $learner->id,
                        'digital_ethics_audit_type_id' => $type->id,
                        'academic_session_id' => $session->id,
                        'term_id' => $term->id,
                        'assignment_title' => $titles[($index + $offset) % count($titles)].' #'.($offset + 1),
                    ],
                    [
                        'school_class_id' => $learner->school_class_id,
                        'audited_on' => now()->subDays(rand(2, 35))->toDateString(),
                        'free_of_violations' => $compliant,
                        'violation_category' => $compliant ? null : 'Undisclosed AI use',
                        'detector_tool' => $compliant ? 'Manual review' : 'AI detector + teacher review',
                        'findings' => $compliant
                            ? 'No integrity or tech policy breach found.'
                            : 'Submission showed uncredited generative AI content.',
                        'notes' => $compliant ? null : 'Learner counselled; resubmission requested.',
                        'audited_by' => $auditor->id,
                    ]
                );
            }
        }

        app(DigitalEthicsCalculationService::class)->recalculateForSession($session, $term);
    }
}
