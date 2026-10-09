<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ExamResult;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ExamSittingReview
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public function __construct(
        protected ExamCalculationService $exams,
        protected AtRiskCalculationService $atRisk,
    ) {}

    public function verify(User $hod, AcademicSession $session, Term $term, int $classId, int $subjectId): void
    {
        $this->assertHodCanReviewSitting($hod, $classId, $subjectId);
        $summary = $this->exams->sittingSummary($session, $term, $classId, $subjectId);
        $this->assertSubmitted($summary);

        $this->query($session, $term, $classId, $subjectId)->update([
            'status' => self::STATUS_VERIFIED,
            'verified_by' => $hod->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->atRisk->flagBelowPassFromSitting($hod, $session, $term, $classId, $subjectId);
    }

    public function reject(User $hod, AcademicSession $session, Term $term, int $classId, int $subjectId, string $reason): void
    {
        $this->assertHodCanReviewSitting($hod, $classId, $subjectId);
        $summary = $this->exams->sittingSummary($session, $term, $classId, $subjectId);
        $this->assertSubmitted($summary);

        $this->query($session, $term, $classId, $subjectId)->update([
            'status' => self::STATUS_REJECTED,
            'verified_by' => $hod->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function markSittingSubmitted(AcademicSession $session, Term $term, int $classId, int $subjectId): void
    {
        $this->query($session, $term, $classId, $subjectId)->update([
            'status' => self::STATUS_SUBMITTED,
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
        ]);
    }

    protected function query(AcademicSession $session, Term $term, int $classId, int $subjectId)
    {
        return ExamResult::query()
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('assessment_key', ExamCalculationService::ASSESSMENT_KEY);
    }

    protected function assertHodCanReviewSitting(User $hod, int $classId, int $subjectId): void
    {
        abort_unless($hod->isHoD(), 403, 'Only a Head of Department can review this marksheet.');
        $class = SchoolClass::find($classId);
        abort_unless($class && (int) $class->school_id === (int) $hod->school_id, 403);
        abort_unless(app(HodScope::class)->canReviewExamSitting($hod, $classId, $subjectId), 403);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function assertSubmitted(array $summary): void
    {
        if (($summary['review_status'] ?? null) === self::STATUS_SUBMITTED) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Only a complete submitted marksheet can be reviewed.',
        ]);
    }
}
