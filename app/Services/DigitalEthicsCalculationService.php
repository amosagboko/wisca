<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\DigitalEthicsAudit;
use App\Models\DigitalEthicsAuditType;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Term;
use Illuminate\Support\Collection;

class DigitalEthicsCalculationService
{
    public function __construct(
        protected KpiStatusEvaluator $evaluator,
    ) {}

    /**
     * DI-03: audited assignments free of AI/tech violations ÷ total audited.
     */
    public function recalculateForSession(AcademicSession $session, ?Term $term = null): ?KpiPeriodicData
    {
        $kpi = Kpi::where('code', 'DI-03')->first();
        if (! $kpi) {
            return null;
        }

        $term ??= Term::currentForSession($session->id);
        if (! $term) {
            return null;
        }

        $summary = $this->sessionSummary($session, $term);
        if ($summary['audited'] === 0) {
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
                    'audited' => $summary['audited'],
                    'compliant' => $summary['compliant'],
                    'violations' => $summary['violations'],
                ],
            ]
        );
    }

    public function sessionSummary(AcademicSession $session, ?Term $term = null): array
    {
        $rows = DigitalEthicsAudit::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->get();

        $audited = $rows->count();
        $compliant = $rows->filter(fn (DigitalEthicsAudit $row) => $row->countsForKpi())->count();

        return [
            'audited' => $audited,
            'compliant' => $compliant,
            'violations' => max(0, $audited - $compliant),
            'rate' => $audited > 0 ? round($compliant / $audited, 4) : 0.0,
        ];
    }

    public function auditRows(
        AcademicSession $session,
        ?Term $term = null,
        ?int $typeId = null,
        string $compliance = '',
    ): Collection {
        return DigitalEthicsAudit::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when($typeId, fn ($q) => $q->where('digital_ethics_audit_type_id', $typeId))
            ->when($compliance === 'compliant', fn ($q) => $q->where('free_of_violations', true))
            ->when($compliance === 'violation', fn ($q) => $q->where('free_of_violations', false))
            ->with(['auditType', 'learner.schoolClass', 'auditor'])
            ->orderByDesc('audited_on')
            ->orderByDesc('created_at')
            ->get();
    }

    public function activeTypesForSchool(int $schoolId): Collection
    {
        return DigitalEthicsAuditType::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }
}
