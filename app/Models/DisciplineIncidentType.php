<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineIncidentType extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'restorative_required',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'restorative_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(DisciplineIncident::class);
    }
}
