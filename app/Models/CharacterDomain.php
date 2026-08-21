<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharacterDomain extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'rubric',
        'passing_level',
        'display_order',
        'status',
    ];

    protected $casts = [
        'rubric'        => 'array',
        'passing_level' => 'integer',
    ];

    /** Default four-level rubric used when none is configured. */
    public const DEFAULT_RUBRIC = [
        ['level' => 1, 'label' => 'Beginning',   'description' => 'Rarely demonstrates this character quality.'],
        ['level' => 2, 'label' => 'Developing',  'description' => 'Sometimes demonstrates this character quality.'],
        ['level' => 3, 'label' => 'Secure',       'description' => 'Consistently demonstrates this character quality.'],
        ['level' => 4, 'label' => 'Exemplary',    'description' => 'Models this character quality and positively influences peers.'],
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(CharacterRating::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function effectiveRubric(): array
    {
        return $this->rubric ?? self::DEFAULT_RUBRIC;
    }

    public function labelForLevel(int $level): string
    {
        foreach ($this->effectiveRubric() as $row) {
            if ((int) $row['level'] === $level) {
                return $row['label'];
            }
        }

        return (string) $level;
    }

    public function levelsPassing(): array
    {
        return array_filter(
            array_column($this->effectiveRubric(), 'level'),
            fn ($l) => (int) $l >= $this->passing_level
        );
    }
}
