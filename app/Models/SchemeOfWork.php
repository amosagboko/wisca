<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'version', 'replaces_id',
        'uploaded_by', 'file_path', 'status',
        'submitted_by', 'submitted_at',
        'hos_approved_by', 'hos_approved_at',
        'board_approved_by', 'board_approved_at',
        'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'hos_approved_at' => 'datetime',
            'board_approved_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function hosApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hos_approved_by');
    }

    public function boardApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'board_approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('week_number')->orderBy('display_order');
    }

    public function scopeForKey(Builder $query, int $sessionId, int $termId, int $classId, int $subjectId): Builder
    {
        return $query
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->isDraft() && $this->submitted_at !== null;
    }

    public function isEditableDraft(): bool
    {
        return $this->isDraft() && $this->submitted_at === null;
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function hasHosApproval(): bool
    {
        return $this->hos_approved_by !== null && $this->hos_approved_at !== null;
    }

    public function hasBoardApproval(): bool
    {
        return $this->board_approved_by !== null && $this->board_approved_at !== null;
    }

    public function canBeSubmitted(): bool
    {
        return $this->isEditableDraft();
    }

    public function canHosApprove(): bool
    {
        return $this->isSubmitted() && ! $this->hasHosApproval();
    }

    public function canBoardApprove(): bool
    {
        return $this->isSubmitted() && $this->hasHosApproval() && ! $this->hasBoardApproval();
    }

    public function canActivate(): bool
    {
        return $this->isApproved() && $this->hasHosApproval() && $this->hasBoardApproval();
    }

    public function canReject(): bool
    {
        return $this->isSubmitted() || $this->isApproved();
    }

    public function canBePermanentlyDeleted(): bool
    {
        return $this->isDraft() && ! $this->isActive() && ! $this->isArchived();
    }

    public function processLabel(): string
    {
        return match (true) {
            $this->isActive() => 'Active',
            $this->isApproved() => 'Approved',
            $this->isArchived() => 'Archived',
            $this->isSubmitted() => 'Submitted',
            default => 'Draft',
        };
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
