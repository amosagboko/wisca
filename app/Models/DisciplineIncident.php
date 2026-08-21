<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineIncident extends Model
{
    protected $fillable = [
        'school_id',
        'discipline_incident_type_id',
        'academic_session_id',
        'term_id',
        'learner_id',
        'incident_date',
        'title',
        'description',
        'severity',
        'status',
        'restorative_status',
        'restorative_agreement',
        'restorative_actions',
        'restorative_completed_at',
        'reported_by',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'restorative_completed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DisciplineIncidentType::class, 'discipline_incident_type_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function restorativeCompleted(): bool
    {
        return $this->restorative_status === 'completed' && $this->restorative_completed_at !== null;
    }
}
