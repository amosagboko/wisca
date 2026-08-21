<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kpi extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pillar_id', 'code', 'name', 'description', 'measurement_methodology',
        'calculation_formula', 'data_collection_instrument', 'default_target',
        'target_type', 'frequency', 'unit', 'owner_role', 'display_order', 'config', 'status',
    ];

    protected function casts(): array
    {
        return [
            'default_target' => 'decimal:4',
            'config' => 'array',
        ];
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }

    public function periodicData(): HasMany
    {
        return $this->hasMany(KpiPeriodicData::class);
    }
}
