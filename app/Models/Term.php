<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends Model
{
    protected $fillable = [
        'academic_session_id', 'sequence', 'name', 'start_date', 'end_date', 'status', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public static function currentForSession(int $sessionId): ?self
    {
        return static::where('academic_session_id', $sessionId)
            ->where('is_current', true)
            ->first();
    }

    public function schemeWeekNumber(int $maxWeek = 1): int
    {
        $maxWeek = max(1, $maxWeek);
        $today = now()->startOfDay();
        $start = $this->start_date->copy()->startOfDay();

        if ($today->lt($start)) {
            return 1;
        }

        $week = (int) floor($start->diffInDays($today) / 7) + 1;

        return max(1, min($maxWeek, $week));
    }

    public function instructionalWeekStart(int $weekNumber): Carbon
    {
        return $this->start_date->copy()->startOfDay()->addWeeks(max(0, $weekNumber - 1));
    }

    public function instructionalWeekEnd(int $weekNumber): Carbon
    {
        return $this->instructionalWeekStart($weekNumber)->copy()->addDays(6)->endOfDay();
    }

    public function lessonPlanDueAt(int $weekNumber): Carbon
    {
        return $this->instructionalWeekStart($weekNumber)->startOfWeek(Carbon::MONDAY);
    }
}
