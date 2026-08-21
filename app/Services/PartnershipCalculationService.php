<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Guardian;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\PartnershipCharter;
use App\Models\PartnershipSignature;
use App\Models\Term;
use Illuminate\Support\Collection;

class PartnershipCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * CE-07: signed parent partnership commitments ÷ total parent body.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'CE-07')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $term);
        if ($summary['total_parents'] === 0) {
            return null;
        }

        $achievement = $this->evaluator->achievementRate($summary['rate'], (float) $kpi->default_target);

        return KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'school_class_id' => null,
                'subject_id' => null,
            ],
            [
                'target_value' => $kpi->default_target,
                'actual_value' => $summary['rate'],
                'achievement_rate' => $achievement,
                'status' => $this->evaluator->kpiStatus($achievement ?? 0),
                'period_start' => $term->start_date->toDateString(),
                'period_end' => $term->end_date->toDateString(),
                'metadata' => [
                    'total_parents' => $summary['total_parents'],
                    'signed' => $summary['signed'],
                    'pending' => $summary['pending'],
                    'declined' => $summary['declined'],
                ],
            ]
        );
    }

    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $totalParents = Guardian::query()
            ->where('school_id', $session->school_id)
            ->where('status', 'active')
            ->count();

        if (! $term) {
            return [
                'total_parents' => $totalParents,
                'signed' => 0,
                'pending' => 0,
                'declined' => 0,
                'rate' => 0.0,
            ];
        }

        $signatures = PartnershipSignature::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->get();

        $signed = $signatures->filter(fn (PartnershipSignature $row) => $row->countsForKpi())->count();
        $declined = $signatures->where('status', 'declined')->count();
        $pending = max(0, $totalParents - $signatures->whereIn('status', ['signed', 'declined'])->count());

        return [
            'total_parents' => $totalParents,
            'signed' => $signed,
            'pending' => $pending,
            'declined' => $declined,
            'rate' => $totalParents > 0 ? round($signed / $totalParents, 4) : 0.0,
        ];
    }

    public function guardianRows(AcademicSession $session, ?Term $term, ?string $statusFilter = null): Collection
    {
        $charter = $this->activeCharterForSchool($session->school_id);

        return Guardian::query()
            ->where('school_id', $session->school_id)
            ->where('status', 'active')
            ->with(['learners.schoolClass'])
            ->orderBy('name')
            ->get()
            ->map(function (Guardian $guardian) use ($session, $term, $charter) {
                $signature = $term
                    ? PartnershipSignature::query()
                        ->where('parent_id', $guardian->id)
                        ->where('academic_session_id', $session->id)
                        ->where('term_id', $term->id)
                        ->first()
                    : null;

                return [
                    'guardian' => $guardian,
                    'signature' => $signature,
                    'charter' => $charter,
                    'status' => $signature?->status ?? 'pending',
                ];
            })
            ->when($statusFilter, fn (Collection $rows) => $rows->filter(
                fn (array $row) => $row['status'] === $statusFilter
            ))
            ->values();
    }

    public function activeCharterForSchool(int $schoolId): ?PartnershipCharter
    {
        return PartnershipCharter::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderByDesc('updated_at')
            ->first();
    }
}
