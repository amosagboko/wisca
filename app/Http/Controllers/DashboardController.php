<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Services\AtRiskCalculationService;
use App\Services\AttendanceCalculationService;
use App\Services\CurriculumDashboardService;
use App\Services\DashboardService;
use App\Services\HodReviewFeed;
use App\Services\LeadershipReviewFeed;
use App\Services\TeacherTaskFeed;
use App\Services\WorkInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        CurriculumDashboardService $curriculum,
        AttendanceCalculationService $attendance,
        AtRiskCalculationService $atRisk,
        HodReviewFeed $reviews,
        LeadershipReviewFeed $leadership,
    ): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // All sessions for this school, newest first
        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')
            ->get();

        // Resolve selected session (defaults to current active one)
        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);

        if (! $session) {
            abort(403, 'No active academic session configured.');
        }

        session(['active_academic_session_id' => $session->id]);

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isParentRelationsLead()) {
            return redirect()->route('partnership.index');
        }

        if ($user->isStemCoordinator()) {
            return redirect()->route('stem.index');
        }

        if ($user->isIctCoordinator()) {
            return redirect()->route('lms.index');
        }

        if ($user->isAdminManager()) {
            return redirect()->route('portal-engagement.index');
        }

        if ($user->isChaplain()) {
            return redirect()->route('chapel.index');
        }

        if ($user->isSubjectLead()) {
            return redirect()->route('coverage-logs.index');
        }

        if ($user->isBoard() || $user->isHoS() || $user->isAssistantHead()) {
            $allTerms = Term::where('academic_session_id', $session->id)
                ->orderBy('start_date')->get();
            $currentTerm = Term::currentForSession($session->id);
            $termId = $request->has('term_id')
                ? $request->integer('term_id')
                : (int) ($currentTerm?->id ?? 0);
            $term = $termId
                ? $allTerms->firstWhere('id', $termId)
                : $currentTerm;
            $ops = $term
                ? $leadership->compose($user, $session, $term)
                : ['items' => collect(), 'counts' => [], 'week_number' => 1, 'review' => null];
            $ops['inbox'] = WorkInbox::present(
                collect($ops['items'] ?? []),
                $request,
                LeadershipReviewFeed::TYPE_LABELS,
                'none',
            );

            return view('dashboard.executive', [
                'summary'     => $dashboard->executiveSummary($user->school_id, $session->id),
                'session'     => $session,
                'allSessions' => $allSessions,
                'allTerms'    => $allTerms,
                'term'        => $term,
                'leadership'  => $ops,
                'canReviewWeek' => $user->isHoS() || $user->isAssistantHead(),
            ]);
        }

        if ($user->isHoD()) {
            $filterTeacherId = $request->integer('teacher_id');
            $filterClassId   = $request->integer('class_id');
            $filterSubjectId = $request->integer('subject_id');
            $filterWeek      = $request->integer('week');
            $filterGroup     = (string) $request->query('group', 'teacher');

            $allTerms = Term::where('academic_session_id', $session->id)
                ->orderBy('start_date')->get();
            $currentTerm = Term::currentForSession($session->id);
            $termId = $request->has('term_id')
                ? $request->integer('term_id')
                : (int) ($currentTerm?->id ?? 0);

            $user->loadMissing('department');

            $data = $curriculum->hodOperations($user, $session, [
                'term_id' => $termId,
                'week' => $filterWeek,
                'teacher_id' => $filterTeacherId,
                'class_id' => $filterClassId,
                'subject_id' => $filterSubjectId,
                'group' => $filterGroup,
            ]);

            $data['pending_homework']   = $data['homework_logs']->filter(fn ($log) => $log->isSubmitted())->values();
            $data['pending_attendance'] = $data['attendance_logs']->filter(fn ($log) => $log->isSubmitted())->values();
            $data['pending_exams']      = ($data['exam_sittings'] ?? collect())
                ->filter(fn ($row) => ($row['review_status'] ?? null) === 'submitted')
                ->values();
            $inbox = WorkInbox::present(
                $reviews->compose($data, null),
                $request,
                HodReviewFeed::TYPE_LABELS,
                $filterGroup,
            );
            $data['review_feed'] = $inbox['items'];
            $data['review_feed_total'] = $inbox['total'];
            $data['review_feed_filtered_total'] = $inbox['filtered_total'];
            $data['review_feed_types'] = $inbox['types'];
            $data['review_feed_groups'] = $inbox['groups'];
            $data['review_feed_paginator'] = $inbox['paginator'];
            $inboxType = $inbox['active_type'];

            $hodFilters = compact(
                'sessionId', 'termId', 'filterTeacherId', 'filterClassId', 'filterSubjectId', 'filterWeek', 'filterGroup', 'inboxType'
            );
            $hodActiveFilters = (bool) array_filter([
                $filterTeacherId, $filterClassId, $filterSubjectId, $filterWeek,
                $filterGroup !== 'teacher',
                $inboxType,
                $termId && $termId !== (int) ($currentTerm?->id ?? 0),
                $sessionId && $sessionId !== ($allSessions->first()?->id ?? 0),
            ]);

            return view('dashboard.hod', [
                ...$data,
                'session'          => $session,
                'allSessions'      => $allSessions,
                'allTerms'         => $allTerms,
                'hodFilters'       => $hodFilters,
                'hodActiveFilters' => $hodActiveFilters,
            ]);
        }

        if ($user->isAdminOfficer()) {
            $week = $attendance->officerWeek($session);
            $week['inbox'] = WorkInbox::present(
                collect($week['missing_classes'] ?? [])->map(function ($class) {
                    return [
                        'key' => 'register-'.$class->id,
                        'type' => 'register',
                        'urgency' => 10,
                        'title' => 'Take register',
                        'meta' => $class->name.' · No register for the suggested date.',
                        'href' => route('attendance.create', ['class' => $class->id]),
                        'cta' => 'Take register',
                        'badge' => 'Today',
                        'blocked' => false,
                        'teacher' => null,
                        'class' => $class->name,
                        'subject' => null,
                    ];
                })->values(),
                $request,
                ['register' => 'Registers still to take'],
                'class',
            );

            return view('dashboard.officer', [
                ...$week,
                'session'     => $session,
                'allSessions' => $allSessions,
            ]);
        }

        if ($user->isLearningSupport()) {
            $allTerms = Term::where('academic_session_id', $session->id)
                ->orderBy('start_date')->get();
            $termId = $request->integer('term_id');
            $term   = $termId
                ? $allTerms->firstWhere('id', $termId)
                : Term::currentForSession($session->id);

            $summary = $term
                ? $atRisk->monthSummary($session, $term)
                : ['identified' => 0, 'with_plan' => 0, 'without_plan' => 0, 'rate' => 0.0, 'records' => collect()];
            $unflagged = $term ? $atRisk->belowPassUnflagged($session, $term) : collect();
            $gaps = collect($summary['records'] ?? [])->reject->hasActivePlan()->values();
            $supportItems = $unflagged->map(function (array $row) {
                $learner = $row['learner'];

                return [
                    'key' => 'unflagged-'.$learner->id,
                    'type' => 'unflagged',
                    'urgency' => 10,
                    'title' => $learner->name,
                    'meta' => ($learner->schoolClass->name ?? 'Class').' · Verified score '.number_format((float) $row['lowest'], 0).'% is below pass mark.',
                    'href' => null,
                    'cta' => 'Flag learner',
                    'badge' => 'Not flagged',
                    'blocked' => false,
                    'teacher' => null,
                    'class' => $learner->schoolClass->name ?? null,
                    'subject' => null,
                    'form' => [
                        'action' => route('at-risk.from-exam'),
                        'fields' => ['learner_id' => $learner->id],
                    ],
                ];
            })->concat($gaps->map(function ($record) {
                return [
                    'key' => 'no-plan-'.$record->id,
                    'type' => 'no-plan',
                    'urgency' => 20,
                    'title' => $record->learner->name,
                    'meta' => $record->schoolClass->name.' · '.implode(', ', $record->factorLabels()),
                    'href' => route('at-risk.plans.create', $record),
                    'cta' => 'Add plan',
                    'badge' => 'No plan',
                    'blocked' => false,
                    'teacher' => null,
                    'class' => $record->schoolClass->name,
                    'subject' => null,
                ];
            }))->values();

            return view('dashboard.support', [
                'at_risk'     => $summary,
                'unflagged'   => $unflagged,
                'inbox'       => WorkInbox::present($supportItems, $request, [
                    'unflagged' => 'Below pass — not flagged',
                    'no-plan' => 'Plans needed',
                ], 'class'),
                'session'     => $session,
                'term'        => $term,
                'allSessions' => $allSessions,
                'allTerms'    => $allTerms,
                'termId'      => $termId,
                'sessionId'   => $sessionId,
            ]);
        }

        if ($user->isTeacher()) {
            $week = $curriculum->teacherWeek($user, $session);
            $week['inbox'] = WorkInbox::present(
                collect($week['tasks'] ?? []),
                $request,
                TeacherTaskFeed::TYPE_LABELS,
                'class',
            );

            return view('dashboard.teacher', [
                ...$week,
                'session'     => $session,
                'allSessions' => $allSessions,
            ]);
        }

        return view('dashboard.executive', [
            'summary'     => $dashboard->executiveSummary($user->school_id, $session->id),
            'session'     => $session,
            'allSessions' => $allSessions,
        ]);
    }
}
