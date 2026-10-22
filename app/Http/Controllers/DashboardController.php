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
use App\Services\HodApprovalQueue;
use App\Services\HodExceptionFeed;
use App\Services\HodQuickLog;
use App\Services\HodTermlyDrawer;
use App\Services\HodWeeklyPulse;
use App\Services\LeadershipAccountability;
use App\Services\LeadershipReviewFeed;
use App\Services\TeacherActivityDrawer;
use App\Services\TeacherPerformance;
use App\Services\TeacherQuickLog;
use App\Services\TeacherTaskFeed;
use App\Services\TeacherWeekBoard;
use App\Services\WorkInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
        HodApprovalQueue $approvals,
        HodWeeklyPulse $pulse,
        HodExceptionFeed $exceptions,
        HodTermlyDrawer $drawer,
        HodQuickLog $quickLog,
        LeadershipReviewFeed $leadership,
        LeadershipAccountability $accountability,
        TeacherTaskFeed $teacherTasks,
        TeacherWeekBoard $weekBoard,
        TeacherPerformance $teacherPerformance,
        TeacherActivityDrawer $teacherDrawer,
        TeacherQuickLog $teacherQuickLog,
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
            $composed = $term
                ? $accountability->compose($user, $session, $term)
                : ['hods' => collect(), 'teachers' => collect(), 'week_number' => 1];

            return view('dashboard.executive', [
                'summary'     => $dashboard->executiveSummary($user->school_id, $session->id),
                'session'     => $session,
                'allSessions' => $allSessions,
                'allTerms'    => $allTerms,
                'term'        => $term,
                'leadership'  => $ops,
                'accountability' => $this->presentAccountability($composed, $request),
                'canReviewWeek' => $user->isHoS() || $user->isAssistantHead(),
            ]);
        }

        if ($user->isHoD()) {
            $filterTeacherId = $request->integer('teacher_id');
            $filterClassId   = $request->integer('class_id');
            $filterSubjectId = $request->integer('subject_id');
            $filterWeek      = $request->integer('week');

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
            ]);

            $approvalQueue = $approvals->compose($data);
            $weeklyPulse = $pulse->compose($data, $user);
            $flags = $exceptions->compose($data, $weeklyPulse);
            $flags['closed'] = $exceptions->recentlyClosed($data);
            $termlyDrawer = $drawer->compose($data, $user);

            $hodFilters = compact(
                'sessionId', 'termId', 'filterTeacherId', 'filterClassId', 'filterSubjectId', 'filterWeek'
            );
            $hodActiveFilters = (bool) array_filter([
                $filterTeacherId, $filterClassId, $filterSubjectId, $filterWeek,
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
                'approval_queue'   => $approvalQueue,
                'weekly_pulse'     => $weeklyPulse,
                'hod_flags'        => $flags,
                'termly_drawer'    => $termlyDrawer,
                'quicklog_actions' => $quickLog->actions($user),
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
            $actions = $teacherTasks->actionableFrom(collect($week['tasks'] ?? []));
            $pageSize = 10;
            $paginator = null;
            $actionItems = $actions;
            if ($actions->count() > $pageSize) {
                $page = max(1, $request->integer('actions_page'));
                $actionItems = $actions->forPage($page, $pageSize)->values();
                $paginator = new LengthAwarePaginator(
                    $actionItems,
                    $actions->count(),
                    $pageSize,
                    $page,
                    [
                        'path' => $request->url(),
                        'pageName' => 'actions_page',
                        'query' => $request->except('actions_page'),
                    ]
                );
            }

            return view('dashboard.teacher', [
                ...$week,
                'session' => $session,
                'allSessions' => $allSessions,
                'my_actions' => $actionItems,
                'my_actions_total' => $actions->count(),
                'my_actions_paginator' => $paginator,
                'teaching_week' => $weekBoard->compose($week + ['session' => $session]),
                'performance' => $teacherPerformance->compose($week, $user),
                'activity_drawer' => $teacherDrawer->compose($week, $user),
                'quicklog_actions' => $teacherQuickLog->actions($user),
            ]);
        }

        return view('dashboard.executive', [
            'summary'     => $dashboard->executiveSummary($user->school_id, $session->id),
            'session'     => $session,
            'allSessions' => $allSessions,
            'accountability' => $this->presentAccountability([
                'hods' => collect(),
                'teachers' => collect(),
                'week_number' => 1,
            ], $request),
            'leadership' => ['items' => collect(), 'counts' => [], 'week_number' => 1, 'review' => null],
            'canReviewWeek' => false,
        ]);
    }

    /**
     * @param  array{hods?: mixed, teachers?: mixed, week_number?: int}  $composed
     * @return array<string, mixed>
     */
    protected function presentAccountability(array $composed, Request $request): array
    {
        $hods = collect($composed['hods'] ?? []);
        $teachers = collect($composed['teachers'] ?? []);
        $tab = $request->query('behind') === 'teachers' ? 'teachers' : 'hods';

        return [
            'week_number' => (int) ($composed['week_number'] ?? 1),
            'tab' => $tab,
            'hods_total' => $hods->count(),
            'teachers_total' => $teachers->count(),
            'hods' => $this->paginateBehind($hods, $request, 'hods_page'),
            'teachers' => $this->paginateBehind($teachers, $request, 'teachers_page'),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    protected function paginateBehind($rows, Request $request, string $pageName): LengthAwarePaginator
    {
        $pageSize = LeadershipAccountability::PAGE_SIZE;
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $pageSize));
        $page = max(1, min($lastPage, $request->integer($pageName) ?: 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $pageSize)->values(),
            $total,
            $pageSize,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
                'query' => $request->except($pageName),
            ]
        );
    }
}
