<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\KpiPeriodicData;
use App\Models\SchemeOfWork;
use App\Models\Term;
use App\Services\AcademicReportingPeriod;
use App\Services\CurriculumCoverageKpiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumCoverageReportController extends Controller
{
    public function __invoke(Request $request, AcademicReportingPeriod $periods, CurriculumCoverageKpiService $kpis): View
    {
        $user = $request->user();
        abort_unless($user->isTeacher() || $user->isHoD() || $user->isLeadership() || $user->isAdmin() || $user->isBoard(), 403);

        $session = AcademicSession::currentForSchool((int) $user->school_id);
        abort_unless($session, 403, 'No current academic session.');
        $term = Term::currentForSession($session->id);
        abort_unless($term, 403, 'No current term.');

        $week = $request->integer('week') ?: $periods->weekNumber($term, $periods->maxWeekForTerm($term));
        $window = $periods->window($term, $week);

        $schemeQuery = SchemeOfWork::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->with(['schoolClass', 'subject', 'topics.lessonPlans', 'topics.coverageLogs.verifier', 'topics.catchUpPlan']);

        if ($user->isTeacher() && ! $user->isHoD() && ! $user->isAdmin() && ! $user->isHoS() && ! $user->isBoard()) {
            $pairs = $user->teacherAssignments()
                ->where('academic_session_id', $session->id)
                ->where('status', 'active')
                ->get(['school_class_id', 'subject_id']);
            if ($pairs->isEmpty()) {
                $schemeQuery->whereRaw('0 = 1');
            } else {
                $schemeQuery->where(function ($query) use ($pairs) {
                    foreach ($pairs as $pair) {
                        $query->orWhere(function ($inner) use ($pair) {
                            $inner->where('school_class_id', $pair->school_class_id)
                                ->where('subject_id', $pair->subject_id);
                        });
                    }
                });
            }
        }

        $schemes = $schemeQuery->get();
        $rows = $schemes->map(function (SchemeOfWork $scheme) use ($kpis, $week) {
            $weekTopics = $kpis->weekTopics($scheme, $week);
            $planned = $weekTopics->filter(fn ($topic) => $topic->lessonPlans->whereIn('status', ['submitted', 'approved'])->isNotEmpty())->count();
            $delivered = $weekTopics->filter(fn ($topic) => $topic->coverageLogs->where('status', 'submitted')->isNotEmpty()
                || $kpis->hasHodVerifiedCoverage($topic))->count();
            $verified = $weekTopics->filter(fn ($topic) => $kpis->hasHodVerifiedCoverage($topic))->count();
            $missed = $kpis->missedTopics($scheme);

            return [
                'scheme' => $scheme,
                'week_topics' => $weekTopics,
                'scheduled' => $weekTopics->count(),
                'planned' => $planned,
                'delivered' => $delivered,
                'verified' => $verified,
                'missed' => $missed,
            ];
        });

        $results = KpiPeriodicData::query()
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->whereNotNull('measure_key')
            ->orderBy('measure_key')
            ->get();

        return view('curriculum-coverage.report', [
            'session' => $session,
            'term' => $term,
            'week' => $week,
            'window' => $window,
            'rows' => $rows,
            'results' => $results,
            'maxWeek' => $periods->maxWeekForTerm($term),
        ]);
    }
}
