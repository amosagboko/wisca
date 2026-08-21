<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnershipCharter extends Model
{
    protected $fillable = [
        'school_id',
        'title',
        'content',
        'version',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(PartnershipSignature::class);
    }
}
