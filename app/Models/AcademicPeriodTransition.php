<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicPeriodTransition extends Model
{
    protected $fillable = [
        'school_id', 'action', 'performed_by', 'performed_at',
        'previous_session_id', 'previous_term_id',
        'new_session_id', 'new_term_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function previousSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'previous_session_id');
    }

    public function previousTerm(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'previous_term_id');
    }

    public function newSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'new_session_id');
    }

    public function newTerm(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'new_term_id');
    }
}
