<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceLog extends Model
{
    protected $fillable = [
        'school_id',
        'service_activity_type_id',
        'academic_session_id',
        'term_id',
        'service_date',
        'title',
        'description',
        'participant_count',
        'verified_hours',
        'status',
        'verification_notes',
        'submitted_by',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'service_date' => 'date',
        'verified_at' => 'datetime',
        'verified_hours' => 'decimal:2',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ServiceActivityType::class, 'service_activity_type_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
