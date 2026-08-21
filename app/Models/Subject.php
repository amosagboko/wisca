<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use SoftDeletes;

    protected $fillable = ['school_id', 'name', 'code', 'status'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
