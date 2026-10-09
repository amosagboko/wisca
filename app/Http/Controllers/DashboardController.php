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
            $inboxType       = (string) $request->query('inbox_type', '');
            if ($inboxType !== '' && ! array_key_exists($inboxType, HodReviewFeed::TYPE_LABELS)) {
                $inboxType = '';
            }

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
            $reviewAll = $reviews->compose($data, null);
            $filteredFeed = $inboxType === ''
                ? $reviewAll
                : $reviewAll->where('type', $inboxType)->values();
            $inboxPage = max(1, $request->integer('inbox_page'));
            $pageItems = $filteredFeed->forPage($inboxPage, HodReviewFeed::INBOX_LIMIT)->values();

            $data['review_feed'] = $pageItems;
            $data['review_feed_total'] = $reviewAll->count();
            $data['review_feed_filtered_total'] = $filteredFeed->count();
            $data['review_feed_types'] = $reviews->typeCounts($reviewAll);
            $data['review_feed_groups'] = $reviews->grouped($pageItems, $filterGroup);
            $data['review_feed_paginator'] = new LengthAwarePaginator(
                $pageItems,
                $filteredFeed->count(),
                HodReviewFeed::INBOX_LIMIT,
                $inboxPage,
                [
                    'path' => $request->url(),
                    'pageName' => 'inbox_page',
                    'query' => $request->except('inbox_page'),
                ]
            );

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
            return view('dashboard.officer', [
                ...$attendance->officerWeek($session),
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

            return view('dashboard.support', [
                'at_risk'     => $term
                    ? $atRisk->monthSummary($session, $term)
                    : ['identified' => 0, 'with_plan' => 0, 'without_plan' => 0, 'rate' => 0.0, 'records' => collect()],
                'unflagged'   => $term ? $atRisk->belowPassUnflagged($session, $term) : collect(),
                'session'     => $session,
                'term'        => $term,
                'allSessions' => $allSessions,
                'allTerms'    => $allTerms,
                'termId'      => $termId,
                'sessionId'   => $sessionId,
            ]);
        }

        if ($user->isTeacher()) {
            return view('dashboard.teacher', [
                ...$curriculum->teacherWeek($user, $session),
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
