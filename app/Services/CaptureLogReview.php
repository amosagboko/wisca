<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\HomeworkLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CaptureLogReview
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public function verify(User $hod, HomeworkLog|AttendanceLog $log): void
    {
        $this->assertHodCanReview($hod, $log);
        $this->assertSubmitted($log);

        $log->update([
            'status' => self::STATUS_VERIFIED,
            'verified_by' => $hod->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function reject(User $hod, HomeworkLog|AttendanceLog $log, string $reason): void
    {
        $this->assertHodCanReview($hod, $log);
        $this->assertSubmitted($log);

        $log->update([
            'status' => self::STATUS_REJECTED,
            'verified_by' => $hod->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function markSubmitted(HomeworkLog|AttendanceLog $log): void
    {
        $log->status = self::STATUS_SUBMITTED;
        $log->verified_by = null;
        $log->verified_at = null;
        $log->rejection_reason = null;
    }

    protected function assertHodCanReview(User $hod, HomeworkLog|AttendanceLog $log): void
    {
        abort_unless($hod->isHoD(), 403, 'Only a Head of Department can review this log.');
        $scope = app(HodScope::class);
        if ($log instanceof HomeworkLog) {
            abort_unless($scope->canReviewHomework($hod, $log), 403);
            return;
        }

        abort_unless($scope->canReviewAttendance($hod, $log), 403);
    }

    protected function assertSubmitted(HomeworkLog|AttendanceLog $log): void
    {
        if ($log->status === self::STATUS_SUBMITTED) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Only a submitted log can be reviewed.',
        ]);
    }
}
