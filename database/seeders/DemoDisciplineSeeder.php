<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\DisciplineIncident;
use App\Models\DisciplineIncidentType;
use App\Models\Learner;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\RestorativeDisciplineCalculationService;
use Illuminate\Database\Seeder;

class DemoDisciplineSeeder extends Seeder
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
        if (! $term) {
            return;
        }

        $types = collect([
            DisciplineIncidentType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Disruptive Behavior'],
                ['code' => 'DISRUPT', 'description' => 'Class disruption and repeated misconduct.', 'restorative_required' => true, 'display_order' => 1, 'is_active' => true]
            ),
            DisciplineIncidentType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Conflict / Aggression'],
                ['code' => 'CONFLICT', 'description' => 'Learner-to-learner conflict incidents.', 'restorative_required' => true, 'display_order' => 2, 'is_active' => true]
            ),
            DisciplineIncidentType::firstOrCreate(
                ['school_id' => $school->id, 'name' => 'Policy Violation'],
                ['code' => 'POLICY', 'description' => 'General conduct policy violations.', 'restorative_required' => true, 'display_order' => 3, 'is_active' => true]
            ),
        ]);

        $reporter = User::where('school_id', $school->id)->orderBy('id')->first();
        $learners = Learner::where('school_id', $school->id)->where('status', 'enrolled')->limit(6)->get();
        if (! $reporter || $learners->isEmpty()) {
            return;
        }

        $rows = [
            ['title' => 'Repeated talking during instruction', 'type' => 'Disruptive Behavior', 'days_ago' => 27, 'severity' => 'low', 'status' => 'resolved', 'restorative_status' => 'completed'],
            ['title' => 'Classroom peer conflict', 'type' => 'Conflict / Aggression', 'days_ago' => 24, 'severity' => 'medium', 'status' => 'resolved', 'restorative_status' => 'completed'],
            ['title' => 'Unauthorized phone use', 'type' => 'Policy Violation', 'days_ago' => 20, 'severity' => 'low', 'status' => 'resolved', 'restorative_status' => 'completed'],
            ['title' => 'Verbal altercation in corridor', 'type' => 'Conflict / Aggression', 'days_ago' => 14, 'severity' => 'high', 'status' => 'in_review', 'restorative_status' => 'in_progress'],
            ['title' => 'Skipping supervised prep', 'type' => 'Policy Violation', 'days_ago' => 10, 'severity' => 'medium', 'status' => 'open', 'restorative_status' => 'pending'],
            ['title' => 'Repeated disruption after warning', 'type' => 'Disruptive Behavior', 'days_ago' => 6, 'severity' => 'medium', 'status' => 'resolved', 'restorative_status' => 'completed'],
        ];

        foreach ($rows as $idx => $row) {
            $type = $types->firstWhere('name', $row['type']);
            $completed = $row['restorative_status'] === 'completed';

            DisciplineIncident::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                    'title' => $row['title'],
                ],
                [
                    'discipline_incident_type_id' => $type->id,
                    'learner_id' => $learners[$idx % $learners->count()]->id,
                    'incident_date' => now()->subDays($row['days_ago'])->toDateString(),
                    'description' => $row['title'],
                    'severity' => $row['severity'],
                    'status' => $row['status'],
                    'restorative_status' => $row['restorative_status'],
                    'restorative_agreement' => $completed ? 'Learner agreed to reflective restitution and apology.' : null,
                    'restorative_actions' => $completed ? 'Mentor check-in and supervised restitution task completed.' : 'Pending restorative conference.',
                    'restorative_completed_at' => $completed ? now()->subDays(max(1, $row['days_ago'] - 1)) : null,
                    'reported_by' => $reporter->id,
                    'resolved_by' => $completed ? $reporter->id : null,
                ]
            );
        }

        app(RestorativeDisciplineCalculationService::class)->recalculateForSession($session, $term);
    }
}
