<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\TopicCatchUpPlan;
use App\Services\TopicCatchUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TopicCatchUpPlanController extends Controller
{
    public function store(Request $request, TopicCatchUpService $catchUps): RedirectResponse
    {
        $validated = $request->validate([
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'target_week_number' => ['nullable', 'integer', 'min:1', 'max:40'],
        ]);

        $topic = Topic::query()
            ->with(['schemeOfWork.subject', 'schemeOfWork.academicSession', 'schemeOfWork.term', 'coverageLogs.verifier'])
            ->findOrFail($validated['topic_id']);

        $catchUps->open(
            $request->user(),
            $topic,
            $validated['notes'] ?? null,
            isset($validated['target_week_number']) ? (int) $validated['target_week_number'] : null,
        );

        return back()->with('success', 'Catch-up opened. The teacher still plans from the Active Scheme of Work, gets HOD lesson-plan approval, delivers, and records coverage. This topic counts as addressed only after you verify delivery.');
    }

    public function cancel(Request $request, TopicCatchUpPlan $catchUp, TopicCatchUpService $catchUps): RedirectResponse
    {
        $catchUps->cancel($request->user(), $catchUp);

        return back()->with('success', 'Catch-up cancelled. It no longer counts as an identified untaught topic.');
    }
}
