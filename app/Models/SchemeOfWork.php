<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchemeOfWork extends Model
{
    use SoftDeletes;

    protected $table = 'schemes_of_work';

    protected $fillable = [
        'subject_id', 'school_class_id', 'academic_session_id', 'term_id',
        'uploaded_by', 'file_path', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('week_number')->orderBy('display_order');
    }

    public function coverageRate(): float
    {
        $total = $this->topics()->count();
        if ($total === 0) {
            return 0;
        }

        $covered = $this->topics()->where('status', 'covered')->count();

        return round($covered / $total, 4);
    }

    /** @return array{total: int, covered: int, rate: float} */
    public function coverageFromLoadedTopics(): array
    {
        $total = $this->topics->count();
        $covered = $this->topics->where('status', 'covered')->count();

        return [
            'total' => $total,
            'covered' => $covered,
            'rate' => $total === 0 ? 0.0 : round($covered / $total, 4),
        ];
    }
}
