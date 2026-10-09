<?php

namespace App\Models;

use App\Services\KpiStatusEvaluator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiPeriodicData extends Model
{
    protected $table = 'kpi_periodic_data';

    protected $fillable = [
        'kpi_id', 'measure_key', 'academic_session_id', 'term_id', 'school_class_id',
        'subject_id', 'teacher_id', 'target_value', 'actual_value',
        'achievement_rate', 'status', 'period_start', 'period_end', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'achievement_rate' => 'decimal:4',
            'period_start' => 'date',
            'period_end' => 'date',
            'metadata' => 'array',
        ];
    }

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->target_value && $model->actual_value !== null) {
                $evaluator = app(KpiStatusEvaluator::class);
                $model->achievement_rate = $evaluator->achievementRate(
                    (float) $model->actual_value,
                    (float) $model->target_value
                );
                $model->status = $evaluator->kpiStatus($model->achievement_rate ?? 0);
            }
        });
    }
}
