<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterRating extends Model
{
    protected $fillable = [
        'learner_id',
        'character_domain_id',
        'academic_session_id',
        'term_id',
        'level',
        'notes',
        'rated_by',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(CharacterDomain::class, 'character_domain_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    public function isPassing(): bool
    {
        return $this->level >= ($this->domain?->passing_level ?? 3);
    }

    public function levelLabel(): string
    {
        return $this->domain?->labelForLevel($this->level) ?? (string) $this->level;
    }
}
