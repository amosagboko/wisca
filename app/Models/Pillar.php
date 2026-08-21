<?php

namespace App\Models;

use App\Services\KpiStatusEvaluator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pillar extends Model
{
    protected $fillable = [
        'school_id', 'name', 'code', 'description', 'display_order', 'config', 'status',
    ];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class)->orderBy('display_order');
    }
}
