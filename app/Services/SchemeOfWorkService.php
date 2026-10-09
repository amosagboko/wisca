<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchemeOfWork;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SchemeOfWorkService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $topics
     */
    public function createDraft(User $hod, array $attributes, array $topics, ?UploadedFile $file = null): SchemeOfWork
    {
        $this->assertHod($hod);
        $this->assertHodCanTarget($hod, (int) $attributes['academic_session_id'], (int) $attributes['term_id'], (int) $attributes['school_class_id'], (int) $attributes['subject_id']);
        $this->assertNoInFlight((int) $attributes['academic_session_id'], (int) $attributes['term_id'], (int) $attributes['school_class_id'], (int) $attributes['subject_id']);

        return DB::transaction(function () use ($hod, $attributes, $topics, $file) {
            $active = $this->activeForKey(
                (int) $attributes['academic_session_id'],
                (int) $attributes['term_id'],
                (int) $attributes['school_class_id'],
                (int) $attributes['subject_id'],
            );

            $scheme = SchemeOfWork::create([
                'subject_id' => $attributes['subject_id'],
                'school_class_id' => $attributes['school_class_id'],
                'academic_session_id' => $attributes['academic_session_id'],
                'term_id' => $attributes['term_id'],
                'version' => $this->nextVersion(
                    (int) $attributes['academic_session_id'],
                    (int) $attributes['term_id'],
                    (int) $attributes['school_class_id'],
                    (int) $attributes['subject_id'],
                ),
                'replaces_id' => $active?->id,
                'uploaded_by' => $hod->id,
                'status' => 'draft',
                'file_path' => $file ? $file->store('schemes-of-work', 'public') : null,
            ]);

            $this->syncTopics($scheme, $topics);

            return $scheme->fresh(['topics']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $topics
     */
    public function updateDraft(User $hod, SchemeOfWork $scheme, array $attributes, array $topics, ?UploadedFile $file = null): SchemeOfWork
    {
        $this->assertHod($hod);
        $this->assertHodOwnsDraft($hod, $scheme);
        abort_unless($scheme->isEditableDraft(), 422, 'Only unsubmitted drafts can be edited.');

        $this->assertHodCanTarget($hod, (int) $attributes['academic_session_id'], (int) $attributes['term_id'], (int) $attributes['school_class_id'], (int) $attributes['subject_id']);
        $this->assertNoInFlight(
            (int) $attributes['academic_session_id'],
            (int) $attributes['term_id'],
            (int) $attributes['school_class_id'],
            (int) $attributes['subject_id'],
            $scheme->id,
        );

        return DB::transaction(function () use ($scheme, $attributes, $topics, $file) {
            if ($file && $scheme->file_path) {
                Storage::disk('public')->delete($scheme->file_path);
            }

            $active = $this->activeForKey(
                (int) $attributes['academic_session_id'],
                (int) $attributes['term_id'],
                (int) $attributes['school_class_id'],
                (int) $attributes['subject_id'],
            );

            $scheme->update([
                'subject_id' => $attributes['subject_id'],
                'school_class_id' => $attributes['school_class_id'],
                'academic_session_id' => $attributes['academic_session_id'],
                'term_id' => $attributes['term_id'],
                'replaces_id' => $active?->id,
                'file_path' => $file ? $file->store('schemes-of-work', 'public') : $scheme->file_path,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->syncTopics($scheme, $topics);

            return $scheme->fresh(['topics']);
        });
    }

    public function clone(User $hod, SchemeOfWork $source): SchemeOfWork
    {
        $this->assertHod($hod);
        $this->assertHodCanTarget($hod, (int) $source->academic_session_id, (int) $source->term_id, (int) $source->school_class_id, (int) $source->subject_id);
        $this->assertNoInFlight((int) $source->academic_session_id, (int) $source->term_id, (int) $source->school_class_id, (int) $source->subject_id);

        $source->load('topics');

        return DB::transaction(function () use ($hod, $source) {
            $filePath = null;
            if ($source->file_path && Storage::disk('public')->exists($source->file_path)) {
                $extension = pathinfo($source->file_path, PATHINFO_EXTENSION);
                $filePath = 'schemes-of-work/'.uniqid('clone_', true).($extension ? '.'.$extension : '');
                Storage::disk('public')->copy($source->file_path, $filePath);
            }

            $scheme = SchemeOfWork::create([
                'subject_id' => $source->subject_id,
                'school_class_id' => $source->school_class_id,
                'academic_session_id' => $source->academic_session_id,
                'term_id' => $source->term_id,
                'version' => $this->nextVersion(
                    (int) $source->academic_session_id,
                    (int) $source->term_id,
                    (int) $source->school_class_id,
                    (int) $source->subject_id,
                ),
                'replaces_id' => $source->id,
                'uploaded_by' => $hod->id,
                'file_path' => $filePath,
                'status' => 'draft',
            ]);

            foreach ($source->topics as $index => $topic) {
                Topic::create([
                    'scheme_of_work_id' => $scheme->id,
                    'week_number' => $topic->week_number,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'learning_objectives' => $topic->learning_objectives,
                    'expected_duration_minutes' => $topic->expected_duration_minutes,
                    'display_order' => $topic->display_order ?: ($index + 1),
                    'status' => 'planned',
                ]);
            }

            return $scheme->fresh(['topics']);
        });
    }

    public function submit(User $hod, SchemeOfWork $scheme): SchemeOfWork
    {
        $this->assertHod($hod);
        $this->assertHodOwnsDraft($hod, $scheme);
        abort_unless($scheme->canBeSubmitted(), 422, 'Only unsubmitted drafts can be submitted.');

        $scheme->loadMissing(['academicSession', 'term']);
        app(AcademicPeriodService::class)->assertAcceptsNewActivity($scheme->academicSession, $scheme->term);

        $this->assertStructuredEvidence($scheme);

        $scheme->update([
            'submitted_by' => $hod->id,
            'submitted_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        return $scheme->fresh();
    }

    public function approve(User $actor, SchemeOfWork $scheme): SchemeOfWork
    {
        if ($actor->isHoS()) {
            return $this->approveByHos($actor, $scheme);
        }

        if ($actor->isBoard()) {
            return $this->approveByBoard($actor, $scheme);
        }

        abort(403, 'Only the Head of School or Board may approve a scheme of work.');
    }

    public function approveByHos(User $hos, SchemeOfWork $scheme): SchemeOfWork
    {
        abort_unless($hos->isHoS(), 403);
        abort_unless($scheme->canHosApprove(), 422, 'HoS can approve only a submitted draft that has not yet received approval 1.');

        $scheme->update([
            'hos_approved_by' => $hos->id,
            'hos_approved_at' => now(),
        ]);

        return $scheme->fresh();
    }

    public function approveByBoard(User $board, SchemeOfWork $scheme): SchemeOfWork
    {
        abort_unless($board->isBoard(), 403);
        abort_unless($scheme->hasHosApproval(), 422, 'Board cannot approve until the Head of School has approved.');
        abort_unless($scheme->canBoardApprove(), 422, 'Board can approve only after HoS approval, before the scheme is already approved.');

        $now = now();
        $scheme->update([
            'board_approved_by' => $board->id,
            'board_approved_at' => $now,
            'approved_by' => $board->id,
            'approved_at' => $now,
            'status' => 'approved',
        ]);

        return $scheme->fresh();
    }

    public function reject(User $actor, SchemeOfWork $scheme, string $reason): SchemeOfWork
    {
        abort_unless($actor->isHoS() || $actor->isBoard(), 403);
        abort_unless($scheme->canReject(), 422, 'Only a submitted draft or an approved scheme that is not yet Active can be rejected.');

        $scheme->update([
            'status' => 'draft',
            'submitted_by' => null,
            'submitted_at' => null,
            'hos_approved_by' => null,
            'hos_approved_at' => null,
            'board_approved_by' => null,
            'board_approved_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $scheme->fresh();
    }

    public function deleteErroneousVersion(User $hod, SchemeOfWork $scheme): void
    {
        $this->assertHod($hod);
        abort_unless($this->canView($hod, $scheme), 403);
        abort_unless($scheme->canBePermanentlyDeleted(), 422, 'Only a draft scheme of work can be deleted. Active and archived versions are kept as history.');

        $scheme->loadMissing(['topics.lessonPlans', 'topics.coverageLogs']);

        $hasPlans = $scheme->topics->contains(fn (Topic $topic) => $topic->lessonPlans->isNotEmpty());
        $hasCoverage = $scheme->topics->contains(fn (Topic $topic) => $topic->coverageLogs->isNotEmpty());

        if ($hasPlans || $hasCoverage) {
            throw ValidationException::withMessages([
                'scheme' => 'This version already has lesson plans or coverage logs. It cannot be deleted. Clone a new draft instead.',
            ]);
        }

        DB::transaction(function () use ($scheme) {
            if ($scheme->file_path) {
                Storage::disk('public')->delete($scheme->file_path);
            }

            $scheme->topics()->delete();
            $scheme->forceDelete();
        });
    }

    public function activate(User $board, SchemeOfWork $scheme): SchemeOfWork
    {
        abort_unless($board->isBoard(), 403);
        abort_unless($scheme->canActivate(), 422, 'Only an approved scheme with both approval slots can be activated.');

        $activated = DB::transaction(function () use ($scheme) {
            $locked = SchemeOfWork::query()->whereKey($scheme->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->canActivate(), 422, 'Only an approved scheme with both approval slots can be activated.');

            SchemeOfWork::query()
                ->forKey(
                    (int) $locked->academic_session_id,
                    (int) $locked->term_id,
                    (int) $locked->school_class_id,
                    (int) $locked->subject_id,
                )
                ->where('status', 'active')
                ->where('id', '!=', $locked->id)
                ->lockForUpdate()
                ->get()
                ->each(fn (SchemeOfWork $previous) => $previous->update(['status' => 'archived']));

            $locked->update(['status' => 'active']);

            return $locked->fresh(['academicSession', 'term']);
        });

        if ($activated->academicSession && $activated->term) {
            app(CurriculumCoverageKpiService::class)->recalculateSoWCompleteness($activated->academicSession, $activated->term);
        }

        return $activated;
    }

    public function hodCanTargetSubject(User $hod, Subject $subject): bool
    {
        return $hod->isHoD()
            && (int) $subject->school_id === (int) $hod->school_id
            && $subject->status === 'active'
            && app(HodScope::class)->canReviewSubject($hod, (int) $subject->id);
    }

    public function teacherCanView(User $teacher, SchemeOfWork $scheme): bool
    {
        if (! $teacher->isTeacher()) {
            return false;
        }

        if (! in_array($scheme->status, ['approved', 'active'], true)) {
            return false;
        }

        $sessionId = (int) $scheme->academic_session_id;

        return TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $sessionId)
            ->where('school_class_id', $scheme->school_class_id)
            ->where('subject_id', $scheme->subject_id)
            ->where('status', 'active')
            ->exists();
    }

    public function canOversee(User $user): bool
    {
        return $user->isHoS() || $user->isBoard() || $user->isAdmin() || $user->isAssistantHead();
    }

    public function canView(User $user, SchemeOfWork $scheme): bool
    {
        $scheme->loadMissing(['schoolClass', 'academicSession', 'subject']);

        if ($this->canOversee($user)) {
            return (int) $scheme->schoolClass?->school_id === (int) $user->school_id
                || (int) $scheme->academicSession?->school_id === (int) $user->school_id;
        }

        if ($user->isHoD()) {
            return $this->hodCanTargetSubject($user, $scheme->subject ?? $scheme->subject()->firstOrFail());
        }

        return $this->teacherCanView($user, $scheme);
    }

    protected function assertHod(User $user): void
    {
        abort_unless($user->isHoD(), 403, 'Only a Head of Department can prepare a scheme of work.');
    }

    protected function assertHodOwnsDraft(User $hod, SchemeOfWork $scheme): void
    {
        abort_unless($scheme->isDraft(), 422, 'Only a draft can be changed by the Head of Department.');
        $this->assertHodCanTarget(
            $hod,
            (int) $scheme->academic_session_id,
            (int) $scheme->term_id,
            (int) $scheme->school_class_id,
            (int) $scheme->subject_id,
        );
    }

    protected function assertHodCanTarget(User $hod, int $sessionId, int $termId, int $classId, int $subjectId): void
    {
        $session = AcademicSession::find($sessionId);
        $term = Term::find($termId);
        $class = SchoolClass::find($classId);
        $subject = Subject::find($subjectId);

        if (! $session || (int) $session->school_id !== (int) $hod->school_id) {
            throw ValidationException::withMessages(['academic_session_id' => 'Choose a session that belongs to your school.']);
        }

        if (! $term || (int) $term->academic_session_id !== $sessionId) {
            throw ValidationException::withMessages(['term_id' => 'Choose a term that belongs to the selected session.']);
        }

        app(AcademicPeriodService::class)->assertAcceptsNewActivity($session, $term);

        if (! $class || (int) $class->school_id !== (int) $hod->school_id) {
            throw ValidationException::withMessages(['school_class_id' => 'Choose a class in your department.']);
        }

        if ($class->offeredSubjects()->exists() && ! $class->offeredSubjects()->whereKey($subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Choose a subject that is offered in the selected class.']);
        }

        if (! $subject || ! $this->hodCanTargetSubject($hod, $subject)) {
            throw ValidationException::withMessages(['subject_id' => 'You can only prepare schemes for subjects in your department.']);
        }
    }

    protected function assertNoInFlight(int $sessionId, int $termId, int $classId, int $subjectId, ?int $exceptId = null): void
    {
        $exists = SchemeOfWork::query()
            ->forKey($sessionId, $termId, $classId, $subjectId)
            ->whereIn('status', ['draft', 'approved'])
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'subject_id' => 'There is already a draft or approved scheme in flight for this session, term, class, and subject.',
            ]);
        }
    }

    protected function assertStructuredEvidence(SchemeOfWork $scheme): void
    {
        $scheme->loadMissing('topics');

        if ($scheme->topics->isEmpty()) {
            throw ValidationException::withMessages([
                'topics' => 'Submit at least one weekly topic with a title and learning objectives.',
            ]);
        }

        foreach ($scheme->topics as $index => $topic) {
            $objectives = array_values(array_filter(array_map(
                fn ($line) => trim((string) $line),
                is_array($topic->learning_objectives) ? $topic->learning_objectives : [],
            )));

            if ($topic->week_number < 1 || trim((string) $topic->title) === '' || $objectives === []) {
                throw ValidationException::withMessages([
                    'topics' => 'Each topic needs a week number, title, and learning objectives before submit.',
                    "topics.{$index}.learning_objectives" => 'Learning objectives are required on every topic.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $topics
     */
    protected function syncTopics(SchemeOfWork $scheme, array $topics): void
    {
        $scheme->topics()->delete();

        foreach (array_values($topics) as $index => $row) {
            $objectives = $this->normalizeObjectives($row['learning_objectives'] ?? '');

            Topic::create([
                'scheme_of_work_id' => $scheme->id,
                'week_number' => (int) ($row['week_number'] ?? ($index + 1)),
                'title' => trim((string) ($row['title'] ?? '')),
                'description' => isset($row['description']) ? trim((string) $row['description']) ?: null : null,
                'learning_objectives' => $objectives,
                'expected_duration_minutes' => (int) ($row['expected_duration_minutes'] ?? 240),
                'display_order' => $index + 1,
                'status' => 'planned',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function normalizeObjectives(mixed $value): array
    {
        if (is_array($value)) {
            $lines = $value;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        }

        return array_values(array_filter(array_map(fn ($line) => trim((string) $line), $lines), fn ($line) => $line !== ''));
    }

    protected function nextVersion(int $sessionId, int $termId, int $classId, int $subjectId): int
    {
        $max = (int) SchemeOfWork::withTrashed()
            ->forKey($sessionId, $termId, $classId, $subjectId)
            ->max('version');

        return max(1, $max + 1);
    }

    protected function activeForKey(int $sessionId, int $termId, int $classId, int $subjectId): ?SchemeOfWork
    {
        return SchemeOfWork::query()
            ->forKey($sessionId, $termId, $classId, $subjectId)
            ->where('status', 'active')
            ->first();
    }
}
