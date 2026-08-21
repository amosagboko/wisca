<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnershipSignature extends Model
{
    protected $fillable = [
        'school_id',
        'parent_id',
        'partnership_charter_id',
        'academic_session_id',
        'term_id',
        'status',
        'signature_method',
        'signed_at',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'parent_id');
    }

    public function charter(): BelongsTo
    {
        return $this->belongsTo(PartnershipCharter::class, 'partnership_charter_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function countsForKpi(): bool
    {
        return $this->status === 'signed';
    }
}
