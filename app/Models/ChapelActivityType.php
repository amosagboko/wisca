<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChapelActivityType extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'counting_levels',
        'school_wide',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'counting_levels' => 'array',
        'school_wide'     => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function chapelSessions(): HasMany
    {
        return $this->hasMany(ChapelSession::class);
    }

    /**
     * Participation levels that count toward the CE-01 numerator.
     * Defaults to active + leading if not configured.
     */
    public function countingLevels(): array
    {
        return $this->counting_levels ?? ['active', 'leading'];
    }

    /**
     * Does this participation status count toward CE-01?
     */
    public function countsAsParticipating(string $level): bool
    {
        return in_array($level, $this->countingLevels(), true);
    }
}
