<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigitalCompetencyArea extends Model
{
    public const LEVEL_LABELS = [
        1 => 'Emerging',
        2 => 'Developing',
        3 => 'Proficient',
        4 => 'Advanced',
    ];

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'passing_level',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'passing_level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(DigitalCompetencyRating::class);
    }

    public function labelForLevel(int $level): string
    {
        return self::LEVEL_LABELS[$level] ?? (string) $level;
    }
}
