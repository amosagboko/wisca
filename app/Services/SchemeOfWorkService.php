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
    public function createDraft(User $teacher, array $attributes, array $topics, ?UploadedFile $file = null): SchemeOfWork
    {
        $this->assertTeacher($teacher);
        $this->assertTeacherCanTarget($teacher, (int) $attributes['academic_session_id'], (int) $attributes['term_id'], (int) $attributes['school_class_id'], (int) $attributes['subject_id']);

        return DB::transaction(function () use ($teacher, $attributes, $topics, $file) {
            $this->assertNoInFlight(
                (int) $attributes['academic_session_id'],
                (int) $attributes['term_id'],
                (int) $attributes['school_class_id'],
                (int) $attributes['subject_id'],
                lock: true,
            );

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
                'uploaded_by' => $teacher->id,
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
    public function updateDraft(User $teacher, SchemeOfWork $scheme, array $attributes, array $topics, ?UploadedFile $file = null): SchemeOfWork
    {
        $this->assertTeacher($teacher);
        $this->assertTeacherOwnsDraft($teacher, $scheme);
        abort_unless($scheme->isEditableDraft(), 422, 'Only unsubmitted drafts can be edited.');

        $this->assertTeacherCanTarget($teacher, (int) $attributes['academic_session_id'], (int) $attributes['term_id'], (int) $attributes['school_class_id'], (int) $attributes['subject_id']);

        return DB::transaction(function () use ($scheme, $attributes, $topics, $file) {
            $this->assertNoInFlight(
                (int) $attributes['academic_session_id'],
                (int) $attributes['term_id'],
                (int) $attributes['school_class_id'],
                (int) $attributes['subject_id'],
                $scheme->id,
                lock: true,
            );

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

    public function clone(User $teacher, SchemeOfWork $source): SchemeOfWork
    {
        $this->assertTeacher($teacher);
        $this->assertTeacherCanTarget($teacher, (int) $source->academic_session_id, (int) $source->term_id, (int) $source->school_class_id, (int) $source->subject_id);

        $source->load('topics');

        return DB::transaction(function () use ($teacher, $source) {
            $this->assertNoInFlight(
                (int) $source->academic_session_id,
                (int) $source->term_id,
                (int) $source->school_class_id,
                (int) $source->subject_id,
                lock: true,
            );

            return $this->createClonedDraft(
                $teacher,
                $source,
                (int) $source->academic_session_id,
                (int) $source->term_id,
            );
        });
    }

    public function cloneToSession(User $teacher, SchemeOfWork $source, int $destSessionId, int $destTermId): SchemeOfWork
    {
        $this->assertTeacher($teacher);
        $source->loadMissing(['term', 'topics']);

        if ((int) $source->academic_session_id === $destSessionId && (int) $source->term_id === $destTermId) {
            throw ValidationException::withMessages([
                'term_id' => 'Use Clone as draft to create a new version in the same session and term.',
            ]);
        }

        $destTerm = Term::find($destTermId);
        if (! $destTerm || (int) $destTerm->sequence !== (int) $source->term?->sequence) {
            throw ValidationException::withMessages([
                'term_id' => 'Clone First Term to First Term, Second Term to Second Term, or Third Term to Third Term only.',
            ]);
        }

        $this->assertTeacherCanTarget(
            $teacher,
            $destSessionId,
            $destTermId,
            (int) $source->school_class_id,
            (int) $source->subject_id,
        );

        return DB::transaction(function () use ($teacher, $source, $destSessionId, $destTermId) {
            $this->assertNoInFlight(
                $destSessionId,
                $destTermId,
                (int) $source->school_class_id,
                (int) $source->subject_id,
                lock: true,
            );

            return $this->createClonedDraft($teacher, $source, $destSessionId, $destTermId);
        });
    }

    public function submit(User $teacher, SchemeOfWork $scheme): SchemeOfWork
    {
        $this->assertTeacher($teacher);
        $this->assertTeacherOwnsDraft($teacher, $scheme);
        abort_unless($scheme->canBeSubmitted(), 422, 'Only unsubmitted drafts can be submitted.');

        $scheme->loadMissing(['academicSession', 'term', 'schoolClass.schoolSection']);
        app(AcademicPeriodService::class)->assertAcceptsNewActivity($scheme->academicSession, $scheme->term);
        $this->assertSectionHasHod($scheme->schoolClass);

        $this->assertStructuredEvidence($scheme);

        $scheme->update([
            'submitted_by' => $teacher->id,
            'submitted_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        return $scheme->fresh();
    }

    public function approve(User $actor, SchemeOfWork $scheme): SchemeOfWork
    {
        abort_unless($actor->isHoD(), 403, 'Only the section Head of Department may approve a scheme of work.');
        abort_unless($scheme->canHodApprove(), 422, 'Only a submitted scheme can be approved.');

        $scheme->loadMissing('schoolClass');
        app(HodScope::class)->assertCanReviewSchemeClass($actor, (int) $scheme->school_class_id);

        $activated = DB::transaction(function () use ($actor, $scheme) {
            $locked = SchemeOfWork::query()->whereKey($scheme->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->canHodApprove(), 422, 'Only a submitted scheme can be approved.');

            $now = now();
            $locked->update([
                'approved_by' => $actor->id,
                'approved_at' => $now,
                'status' => 'approved',
            ]);

            $this->archivePreviousActive($locked);
            $locked->update(['status' => 'active']);

            return $locked->fresh(['academicSession', 'term']);
        });

        if ($activated->academicSession && $activated->term) {
            app(CurriculumCoverageKpiService::class)->recalculateSoWCompleteness($activated->academicSession, $activated->term);
        }

        return $activated;
    }

    public function reject(User $actor, SchemeOfWork $scheme, string $reason): SchemeOfWork
    {
        abort_unless($actor->isHoD(), 403, 'Only the section Head of Department may return a scheme of work.');
        abort_unless($scheme->canReject(), 422, 'Only a submitted draft or an approved scheme that is not yet Active can be rejected.');

        $scheme->loadMissing('schoolClass');
        app(HodScope::class)->assertCanReviewSchemeClass($actor, (int) $scheme->school_class_id);

        $scheme->update([
            'status' => 'draft',
            'submitted_by' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $scheme->fresh();
    }

    public function deleteErroneousVersion(User $teacher, SchemeOfWork $scheme): void
    {
        $this->assertTeacher($teacher);
        $this->assertTeacherOwnsDraft($teacher, $scheme);
        abort_unless($scheme->canBePermanentlyDeleted(), 422, 'Only an unsubmitted draft can be deleted. Submitted, approved, active, and archived versions are kept as history.');

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

        if ((int) $scheme->uploaded_by === (int) $teacher->id) {
            return true;
        }

        $sessionId = (int) $scheme->academic_session_id;

        return TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $sessionId)
            ->where('school_class_id', $scheme->school_class_id)
            ->where('subject_id', $scheme->subject_id)
            ->where('status', 'active')
            ->exists();
    }

    public function teacherHasAssignment(User $teacher, int $sessionId, int $classId, int $subjectId): bool
    {
        return TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('academic_session_id', $sessionId)
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('status', 'active')
            ->exists();
    }

    public function canPlanLessons(User $user, SchemeOfWork $scheme): bool
    {
        return $user->isTeacher()
            && $scheme->isActive()
            && $this->teacherHasAssignment(
                $user,
                (int) $scheme->academic_session_id,
                (int) $scheme->school_class_id,
                (int) $scheme->subject_id,
            );
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
            return app(HodScope::class)->canReviewSchemeClass($user, (int) $scheme->school_class_id);
        }

        return $this->teacherCanView($user, $scheme);
    }

    public function canPrepare(User $user): bool
    {
        return $user->isTeacher();
    }

    public function canEditDraft(User $user, SchemeOfWork $scheme): bool
    {
        return $user->isTeacher()
            && $scheme->isEditableDraft()
            && (int) $scheme->uploaded_by === (int) $user->id;
    }

    public function canApproveScheme(User $user, SchemeOfWork $scheme): bool
    {
        return $user->isHoD()
            && $scheme->canHodApprove()
            && app(HodScope::class)->canReviewSchemeClass($user, (int) $scheme->school_class_id);
    }

    public function canRejectScheme(User $user, SchemeOfWork $scheme): bool
    {
        return $user->isHoD()
            && $scheme->canReject()
            && app(HodScope::class)->canReviewSchemeClass($user, (int) $scheme->school_class_id);
    }

    protected function assertTeacher(User $user): void
    {
        abort_unless($user->isTeacher(), 403, 'Only a teacher can prepare a scheme of work.');
    }

    protected function assertTeacherOwnsDraft(User $teacher, SchemeOfWork $scheme): void
    {
        abort_unless($scheme->isDraft(), 422, 'Only a draft can be changed by the teacher.');
        abort_unless((int) $scheme->uploaded_by === (int) $teacher->id, 403, 'You can only change a scheme of work that you uploaded.');
        $this->assertTeacherCanTarget(
            $teacher,
            (int) $scheme->academic_session_id,
            (int) $scheme->term_id,
            (int) $scheme->school_class_id,
            (int) $scheme->subject_id,
        );
    }

    protected function assertTeacherCanTarget(User $teacher, int $sessionId, int $termId, int $classId, int $subjectId): void
    {
        $session = AcademicSession::find($sessionId);
        $term = Term::find($termId);
        $class = SchoolClass::find($classId);
        $subject = Subject::find($subjectId);

        if (! $session || (int) $session->school_id !== (int) $teacher->school_id) {
            throw ValidationException::withMessages(['academic_session_id' => 'Choose a session that belongs to your school.']);
        }

        if (! $term || (int) $term->academic_session_id !== $sessionId) {
            throw ValidationException::withMessages(['term_id' => 'Choose a term that belongs to the selected session.']);
        }

        app(AcademicPeriodService::class)->assertAcceptsNewActivity($session, $term);

        if (! $class || (int) $class->school_id !== (int) $teacher->school_id) {
            throw ValidationException::withMessages(['school_class_id' => 'Choose a class you are assigned to teach.']);
        }

        if (! $subject || (int) $subject->school_id !== (int) $teacher->school_id) {
            throw ValidationException::withMessages(['subject_id' => 'Choose a subject you are assigned to teach.']);
        }

        if ($class->offeredSubjects()->exists() && ! $class->offeredSubjects()->whereKey($subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Choose a subject that is offered in the selected class.']);
        }

        if (! $this->teacherHasAssignment($teacher, $sessionId, $classId, $subjectId)) {
            throw ValidationException::withMessages(['subject_id' => 'You can only upload a scheme for a class and subject assigned to you in this session.']);
        }
    }

    protected function assertSectionHasHod(?SchoolClass $class): void
    {
        $class?->loadMissing('schoolSection');

        if (! $class?->school_section_id || ! $class->schoolSection) {
            throw ValidationException::withMessages([
                'school_class_id' => 'This class is not assigned to a school section. Ask an administrator to assign it before you submit.',
            ]);
        }

        if (! $class->schoolSection->hod_id) {
            throw ValidationException::withMessages([
                'school_class_id' => 'This school section has no Head of Department. Ask an administrator to assign a HOD before you submit.',
            ]);
        }
    }

    protected function createClonedDraft(User $teacher, SchemeOfWork $source, int $sessionId, int $termId): SchemeOfWork
    {
        $filePath = null;
        if ($source->file_path && Storage::disk('public')->exists($source->file_path)) {
            $extension = pathinfo($source->file_path, PATHINFO_EXTENSION);
            $filePath = 'schemes-of-work/'.uniqid('clone_', true).($extension ? '.'.$extension : '');
            Storage::disk('public')->copy($source->file_path, $filePath);
        }

        $scheme = SchemeOfWork::create([
            'subject_id' => $source->subject_id,
            'school_class_id' => $source->school_class_id,
            'academic_session_id' => $sessionId,
            'term_id' => $termId,
            'version' => $this->nextVersion($sessionId, $termId, (int) $source->school_class_id, (int) $source->subject_id),
            'replaces_id' => $source->id,
            'uploaded_by' => $teacher->id,
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
    }

    protected function archivePreviousActive(SchemeOfWork $scheme): void
    {
        SchemeOfWork::query()
            ->forKey(
                (int) $scheme->academic_session_id,
                (int) $scheme->term_id,
                (int) $scheme->school_class_id,
                (int) $scheme->subject_id,
            )
            ->where('status', 'active')
            ->where('id', '!=', $scheme->id)
            ->lockForUpdate()
            ->get()
            ->each(fn (SchemeOfWork $previous) => $previous->update(['status' => 'archived']));
    }

    protected function assertNoInFlight(int $sessionId, int $termId, int $classId, int $subjectId, ?int $exceptId = null, bool $lock = false): void
    {
        $query = SchemeOfWork::query()
            ->forKey($sessionId, $termId, $classId, $subjectId)
            ->whereIn('status', ['draft', 'approved'])
            ->when($exceptId, fn ($inner) => $inner->where('id', '!=', $exceptId));

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($query->exists()) {
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
