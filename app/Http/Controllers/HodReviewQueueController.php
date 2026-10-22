<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Term;
use App\Models\User;
use App\Services\CurriculumDashboardService;
use App\Services\HodApprovalQueue;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HodReviewQueueController extends Controller
{
    public function schemes(
        Request $request,
        CurriculumDashboardService $curriculum,
        HodApprovalQueue $queue,
    ): View {
        return $this->review($request, $curriculum, $queue, 'schemes');
    }

    public function lessonPlans(
        Request $request,
        CurriculumDashboardService $curriculum,
        HodApprovalQueue $queue,
    ): View {
        return $this->review($request, $curriculum, $queue, 'plans');
    }

    protected function review(
        Request $request,
        CurriculumDashboardService $curriculum,
        HodApprovalQueue $queue,
        string $type,
    ): View {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user->isHoD(), 403);

        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')
            ->get();

        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $currentTerm = Term::currentForSession($session->id);
        $termId = $request->has('term_id')
            ? $request->integer('term_id')
            : (int) ($currentTerm?->id ?? 0);
        $teacherId = $request->integer('teacher_id');
        $classId = $request->integer('class_id');
        $subjectId = $request->integer('subject_id');
        $week = $request->integer('week');

        if ($type === 'schemes') {
            $query = $curriculum->pendingSchemeQuery($user, $session, $termId, $teacherId, $classId, $subjectId)
                ->with(['subject', 'schoolClass.schoolSection', 'submittedBy', 'uploadedBy', 'term'])
                ->orderBy('submitted_at');
            $items = $query->paginate(HodApprovalQueue::REVIEW_PAGE_SIZE)->withQueryString();
            $items->setCollection($queue->sortItems($queue->schemes($items->getCollection())));

            return view('hod.reviews.schemes', [
                'items' => $items,
                'session' => $session,
                'allSessions' => $allSessions,
                'allTerms' => $allTerms,
                'filters' => [
                    'sessionId' => $session->id,
                    'termId' => $termId,
                ],
            ]);
        }

        $query = $curriculum->pendingLessonPlanQuery($user, $session, $termId, $week, $teacherId, $classId, $subjectId)
            ->with(['topic', 'teacher', 'schoolClass.schoolSection', 'subject'])
            ->orderBy('submitted_at');
        $items = $query->paginate(HodApprovalQueue::REVIEW_PAGE_SIZE)->withQueryString();
        $items->setCollection($queue->sortItems($queue->plans($items->getCollection())));

        return view('hod.reviews.lesson-plans', [
            'items' => $items,
            'session' => $session,
            'allSessions' => $allSessions,
            'allTerms' => $allTerms,
            'filters' => [
                'sessionId' => $session->id,
                'termId' => $termId,
            ],
        ]);
    }
}
