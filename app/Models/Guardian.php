<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    protected $table = 'parents';

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'phone',
        'relationship',
        'status',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(Learner::class, 'learner_parent', 'parent_id', 'learner_id')
            ->withTimestamps();
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(PartnershipSignature::class, 'parent_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
