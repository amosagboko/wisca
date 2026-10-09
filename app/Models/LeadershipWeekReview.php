<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadershipWeekReview extends Model
{
    protected $fillable = [
        'school_id', 'academic_session_id', 'term_id', 'week_number',
        'reviewed_by', 'reviewed_at', 'notes', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
