<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Guardian;
use App\Models\Learner;
use App\Models\PartnershipCharter;
use App\Models\PartnershipSignature;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\PartnershipCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoPartnershipSeeder extends Seeder
{
    /**
     * Seeds CE-07 demo data:
     *  - 1 active partnership charter
     *  - 1 parent relations lead user
     *  - ~1 parent per enrolled learner (deduped by learner)
     *  - ~89% signed rate → CE-07 ≈ 89%
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

        Role::firstOrCreate(['name' => 'parent_relations_lead', 'guard_name' => 'web']);

        $lead = User::firstOrCreate(
            ['email' => 'prl@wisca.test'],
            [
                'name' => 'Mrs. Grace Ude',
                'school_id' => $school->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $lead->syncRoles(['parent_relations_lead']);

        $charter = PartnershipCharter::firstOrCreate(
            ['school_id' => $school->id, 'title' => 'Parent-School Partnership Charter'],
            [
                'content' => 'We commit to partnering with WISCA in nurturing Christian character, academic excellence, and faithful discipleship in our children.',
                'version' => '2025/26',
                'display_order' => 1,
                'is_active' => true,
            ]
        );

        $learners = Learner::where('school_id', $school->id)
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        if ($learners->isEmpty()) {
            return;
        }

        $relationships = ['mother', 'father', 'guardian'];
        $methods = ['in_person', 'paper', 'digital'];

        foreach ($learners as $index => $learner) {
            $signed = $index % 9 !== 0;

            $guardian = Guardian::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'name' => 'Parent of '.$learner->name,
                ],
                [
                    'email' => 'parent.'.strtolower(str_replace(' ', '.', $learner->admission_no ?? (string) $learner->id)).'@example.test',
                    'phone' => '080'.str_pad((string) (1000000 + $learner->id), 7, '0', STR_PAD_LEFT),
                    'relationship' => $relationships[$index % count($relationships)],
                    'status' => 'active',
                ]
            );

            $guardian->learners()->syncWithoutDetaching([$learner->id]);

            PartnershipSignature::updateOrCreate(
                [
                    'parent_id' => $guardian->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                ],
                [
                    'school_id' => $school->id,
                    'partnership_charter_id' => $charter->id,
                    'status' => $signed ? 'signed' : 'pending',
                    'signature_method' => $signed ? $methods[$index % count($methods)] : null,
                    'signed_at' => $signed ? now()->subDays(rand(5, 40)) : null,
                    'notes' => $signed ? 'Charter received and signed.' : null,
                    'recorded_by' => $lead->id,
                ]
            );
        }

        app(PartnershipCalculationService::class)->recalculateForSession($session, $term);
    }
}
