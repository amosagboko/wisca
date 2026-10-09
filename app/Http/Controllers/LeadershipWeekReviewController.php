<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\LeadershipWeekReview;
use App\Models\Term;
use App\Services\LeadershipReviewFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadershipWeekReviewController extends Controller
{
    public function store(Request $request, LeadershipReviewFeed $feed): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isHoS() || $user->isAssistantHead(), 403, 'Only Head of School or Assistant Head can record a leadership week review.');

        $validated = $request->validate([
            'session_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
            'week_number' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = AcademicSession::where('school_id', $user->school_id)->findOrFail($validated['session_id']);
        $term = Term::where('academic_session_id', $session->id)->findOrFail($validated['term_id']);
        $picture = $feed->compose($user, $session, $term);
        $weekNumber = (int) $picture['week_number'];

        LeadershipWeekReview::query()->updateOrCreate(
            [
                'school_id' => $user->school_id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'week_number' => $weekNumber,
            ],
            [
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'snapshot' => $picture['counts'],
            ]
        );

        return back()->with('success', 'Leadership review recorded for week '.$weekNumber.'. KPI formulas are unchanged. Outstanding HOD work still belongs to departments.');
    }
}
