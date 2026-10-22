<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolSection extends Model
{
    protected $fillable = ['school_id', 'name', 'status', 'hod_id'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function hod(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
