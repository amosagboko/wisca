<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalEthicsAudit extends Model
{
    protected $fillable = [
        'school_id',
        'digital_ethics_audit_type_id',
        'learner_id',
        'school_class_id',
        'academic_session_id',
        'term_id',
        'assignment_title',
        'audited_on',
        'free_of_violations',
        'violation_category',
        'detector_tool',
        'findings',
        'notes',
        'audited_by',
    ];

    protected $casts = [
        'audited_on' => 'date',
        'free_of_violations' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(DigitalEthicsAuditType::class, 'digital_ethics_audit_type_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by');
    }

    public function countsForKpi(): bool
    {
        return (bool) $this->free_of_violations;
    }
}
