<?php

namespace App\Services;

use App\Models\AcademicPeriodTransition;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AcademicPeriodService
{
    public function currentSession(int $schoolId): ?AcademicSession
    {
        return AcademicSession::currentForSchool($schoolId);
    }

    public function currentTerm(?int $sessionId = null, ?int $schoolId = null): ?Term
    {
        if ($sessionId) {
            return Term::currentForSession($sessionId);
        }

        if (! $schoolId) {
            return null;
        }

        $session = $this->currentSession($schoolId);

        return $session ? Term::currentForSession($session->id) : null;
    }

    public function acceptsNewActivity(?AcademicSession $session, ?Term $term): bool
    {
        if (! $session || ! $term) {
            return false;
        }

        if ((int) $term->academic_session_id !== (int) $session->id) {
            return false;
        }

        return $session->status !== 'closed' && $term->status !== 'closed';
    }

    public function assertAcceptsNewActivity(?AcademicSession $session, ?Term $term): void
    {
        if ($this->acceptsNewActivity($session, $term)) {
            return;
        }

        throw ValidationException::withMessages([
            'term_id' => 'New operational records cannot be created against a closed academic session or term.',
        ]);
    }

    public function nextTerm(Term $current): ?Term
    {
        return Term::query()
            ->where('academic_session_id', $current->academic_session_id)
            ->where('sequence', $current->sequence + 1)
            ->first();
    }

    public function startingTerm(AcademicSession $session): ?Term
    {
        return Term::query()
            ->where('academic_session_id', $session->id)
            ->where('sequence', 1)
            ->first();
    }

    public function lastTerm(AcademicSession $session): ?Term
    {
        return Term::query()
            ->where('academic_session_id', $session->id)
            ->orderByDesc('sequence')
            ->first();
    }

    /**
     * Open the first operational period when the school has no current session.
     */
    public function openInitialPeriod(User $actor, AcademicSession $session, Term $term, ?string $notes = null): AcademicPeriodTransition
    {
        $this->assertCanActivate($actor);
        $this->assertSameSchool($actor, $session);

        return $this->transaction($session->school_id, function () use ($actor, $session, $term, $notes) {
            if ($this->currentSession((int) $session->school_id)) {
                throw ValidationException::withMessages([
                    'session' => 'A current academic session already exists. Use term transition or session rollover.',
                ]);
            }

            $session = AcademicSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $term = Term::query()->whereKey($term->id)->lockForUpdate()->firstOrFail();

            if ((int) $term->academic_session_id !== (int) $session->id) {
                throw ValidationException::withMessages(['term_id' => 'The starting term must belong to the selected session.']);
            }

            $session->forceFill(['is_current' => true, 'status' => 'active'])->save();
            Term::query()->where('academic_session_id', $session->id)->update(['is_current' => false]);
            $term->forceFill(['is_current' => true, 'status' => 'active'])->save();

            return $this->record($actor, 'session_rollover', null, null, $session, $term, $notes);
        });
    }

    public function transitionToNextTerm(User $actor, ?string $notes = null): AcademicPeriodTransition
    {
        $this->assertCanManage($actor);

        $schoolId = (int) $actor->school_id;

        return $this->transaction($schoolId, function () use ($actor, $notes, $schoolId) {
            $session = $this->lockCurrentSession($schoolId);
            $current = $this->lockCurrentTerm($session);
            $next = $this->nextTerm($current);

            if (! $next) {
                throw ValidationException::withMessages([
                    'term' => 'There is no next term in this session. Configure the next term or use session rollover.',
                ]);
            }

            if ((int) $next->academic_session_id !== (int) $session->id) {
                throw ValidationException::withMessages(['term' => 'The next term must belong to the current session.']);
            }

            if ((int) $next->sequence !== (int) $current->sequence + 1) {
                throw ValidationException::withMessages(['term' => 'Term skipping is not allowed. Transition only to the next sequence.']);
            }

            if ($next->status === 'closed') {
                throw ValidationException::withMessages(['term' => 'The next term is closed and cannot become current.']);
            }

            if ($next->is_current && $session->is_current) {
                throw ValidationException::withMessages(['term' => 'This term is already current.']);
            }

            $next = Term::query()->whereKey($next->id)->lockForUpdate()->firstOrFail();

            $current->forceFill(['is_current' => false, 'status' => 'closed'])->save();
            $next->forceFill(['is_current' => true, 'status' => 'active'])->save();

            return $this->record($actor, 'term_transition', $session, $current, $session, $next->fresh(), $notes);
        });
    }

    public function rolloverToSession(User $actor, AcademicSession $targetSession, ?Term $startingTerm = null, ?string $notes = null): AcademicPeriodTransition
    {
        $this->assertCanActivate($actor);
        $this->assertSameSchool($actor, $targetSession);

        $schoolId = (int) $actor->school_id;

        return $this->transaction($schoolId, function () use ($actor, $targetSession, $startingTerm, $notes, $schoolId) {
            $currentSession = $this->lockCurrentSession($schoolId);
            $currentTerm = $this->lockCurrentTerm($currentSession);

            $targetSession = AcademicSession::query()->whereKey($targetSession->id)->lockForUpdate()->firstOrFail();

            if ((int) $targetSession->id === (int) $currentSession->id) {
                throw ValidationException::withMessages(['session' => 'Choose a different academic session for rollover.']);
            }

            if ($targetSession->status === 'closed') {
                throw ValidationException::withMessages(['session' => 'The target session is closed.']);
            }

            if ($targetSession->terms()->count() === 0) {
                throw ValidationException::withMessages(['session' => 'Configure terms on the target session before rollover.']);
            }

            $start = $startingTerm
                ? Term::query()->whereKey($startingTerm->id)->lockForUpdate()->firstOrFail()
                : $this->startingTerm($targetSession);

            if (! $start) {
                throw ValidationException::withMessages(['term_id' => 'The target session must have a starting term (sequence 1).']);
            }

            if ((int) $start->academic_session_id !== (int) $targetSession->id) {
                throw ValidationException::withMessages(['term_id' => 'The starting term must belong to the target session.']);
            }

            if ((int) $start->sequence !== 1) {
                throw ValidationException::withMessages(['term_id' => 'Session rollover must open the term with sequence 1.']);
            }

            $currentTerm->forceFill(['is_current' => false, 'status' => 'closed'])->save();
            $currentSession->forceFill(['is_current' => false, 'status' => 'closed'])->save();

            Term::query()->where('academic_session_id', $targetSession->id)->update(['is_current' => false]);
            $start->forceFill(['is_current' => true, 'status' => 'active'])->save();
            $targetSession->forceFill(['is_current' => true, 'status' => 'active'])->save();

            return $this->record($actor, 'session_rollover', $currentSession, $currentTerm, $targetSession->fresh(), $start->fresh(), $notes);
        });
    }

    /**
     * Only System Admin can open or roll over a session. Head of School and Board prepare it.
     */
    public function activateSession(User $actor, AcademicSession $session, ?Term $startingTerm = null, ?string $notes = null): AcademicPeriodTransition
    {
        $this->assertCanActivate($actor);
        $this->assertSameSchool($actor, $session);

        if ($session->is_current) {
            throw ValidationException::withMessages(['session' => 'This session is already current.']);
        }

        $start = $startingTerm ?? $this->startingTerm($session);
        if (! $start) {
            throw ValidationException::withMessages([
                'session' => 'Add a First Term (sequence 1) before activating this session.',
            ]);
        }

        if (! $this->currentSession((int) $actor->school_id)) {
            return $this->openInitialPeriod($actor, $session, $start, $notes);
        }

        return $this->rolloverToSession($actor, $session, $start, $notes);
    }

    public function sessionHasDependents(AcademicSession $session): bool
    {
        if ($session->is_current) {
            return true;
        }

        if ($this->periodHasRows('academic_session_id', (int) $session->id)) {
            return true;
        }

        if ($this->transitionReferences('session', (int) $session->id)) {
            return true;
        }

        return $session->terms()->get()->contains(fn (Term $term) => $this->termHasDependents($term));
    }

    public function termHasDependents(Term $term): bool
    {
        if ($term->is_current) {
            return true;
        }

        return $this->periodHasRows('term_id', (int) $term->id)
            || $this->transitionReferences('term', (int) $term->id);
    }

    protected function lockCurrentSession(int $schoolId): AcademicSession
    {
        $session = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('is_current', true)
            ->lockForUpdate()
            ->first();

        if (! $session) {
            throw ValidationException::withMessages(['session' => 'There is no current academic session.']);
        }

        return $session;
    }

    protected function lockCurrentTerm(AcademicSession $session): Term
    {
        $term = Term::query()
            ->where('academic_session_id', $session->id)
            ->where('is_current', true)
            ->lockForUpdate()
            ->first();

        if (! $term) {
            throw ValidationException::withMessages(['term' => 'There is no current term in the current session.']);
        }

        return $term;
    }

    protected function record(
        User $actor,
        string $action,
        ?AcademicSession $previousSession,
        ?Term $previousTerm,
        AcademicSession $newSession,
        Term $newTerm,
        ?string $notes,
    ): AcademicPeriodTransition {
        return AcademicPeriodTransition::create([
            'school_id' => $newSession->school_id,
            'action' => $action,
            'performed_by' => $actor->id,
            'performed_at' => now(),
            'previous_session_id' => $previousSession?->id,
            'previous_term_id' => $previousTerm?->id,
            'new_session_id' => $newSession->id,
            'new_term_id' => $newTerm->id,
            'notes' => $notes,
        ]);
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    protected function transaction(int $schoolId, callable $callback): mixed
    {
        return DB::transaction(function () use ($schoolId, $callback) {
            AcademicSession::query()
                ->where('school_id', $schoolId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            return $callback();
        });
    }

    protected function assertCanManage(User $actor): void
    {
        abort_unless($actor->canManageAcademicPeriod(), 403, 'Only System Admin, Head of School, or Board may change the academic period.');
    }

    protected function assertCanActivate(User $actor): void
    {
        abort_unless($actor->canActivateAcademicPeriod(), 403, 'Only System Admin can activate or roll over an academic session.');
    }

    protected function assertSameSchool(User $actor, AcademicSession $session): void
    {
        abort_unless((int) $session->school_id === (int) $actor->school_id, 404);
    }

    /**
     * @var list<string>
     */
    protected function historicalPeriodTables(): array
    {
        return [
            'schemes_of_work',
            'class_subject_teacher',
            'exam_results',
            'attendance_logs',
            'homework_logs',
            'observations',
            'reading_assessments',
            'at_risk_learners',
            'discipline_incidents',
            'bullying_cases',
            'service_logs',
            'scripture_assessments',
            'character_ratings',
            'chapel_sessions',
            'partnership_signatures',
            'digital_ethics_audits',
            'digital_competency_ratings',
            'stem_project_completions',
            'subject_digital_assessments',
            'lms_usage_logs',
            'parent_portal_engagements',
            'kpi_periodic_data',
        ];
    }

    protected function periodHasRows(string $column, int $id): bool
    {
        foreach ($this->historicalPeriodTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if (DB::table($table)->where($column, $id)->exists()) {
                return true;
            }
        }

        return false;
    }

    protected function transitionReferences(string $kind, int $id): bool
    {
        if (! Schema::hasTable('academic_period_transitions')) {
            return false;
        }

        $columns = $kind === 'session'
            ? ['previous_session_id', 'new_session_id']
            : ['previous_term_id', 'new_term_id'];

        return AcademicPeriodTransition::query()
            ->where(function ($query) use ($columns, $id) {
                foreach ($columns as $column) {
                    $query->orWhere($column, $id);
                }
            })
            ->exists();
    }
}
