<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\CharacterDomain;
use App\Models\CharacterRating;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\CharacterCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CharacterRatingController extends Controller
{
    public function index(Request $request, CharacterCalculationService $service): View
    {
        $user = auth()->user();
        abort_unless(
            $user->isAdmin() || $user->isLeadership() || $user->isHoD()
                || $user->isTeacher() || $user->isChaplain() || $user->hasRole('student_life_coordinator'),
            403
        );

        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')->get();

        $sessionId = $request->integer('session_id');
        $session   = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);

        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $termId   = $request->integer('term_id');
        $term     = $termId ? $allTerms->firstWhere('id', $termId) : Term::currentForSession($session->id);

        $domains  = CharacterDomain::where('school_id', $user->school_id)
            ->where('status', 'active')
            ->orderBy('display_order')->get();

        $summary  = $service->sessionSummary($session, $term ?: null);
        $classSummaries = $service->classSummaries($session, $term ?: null, $domains);

        $filters       = compact('sessionId', 'termId');
        $activeFilters = (bool) ($sessionId || $termId);

        return view('character.index', compact(
            'session', 'term', 'allSessions', 'allTerms',
            'domains', 'summary', 'classSummaries', 'filters', 'activeFilters'
        ));
    }

    /**
     * Marksheet: rate all learners in a class across all active domains.
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        abort_unless(
            $user->isAdmin() || $user->isLeadership() || $user->isHoD()
                || $user->isTeacher() || $user->isChaplain() || $user->hasRole('student_life_coordinator'),
            403
        );

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403);

        $term = Term::currentForSession($session->id);

        $domains = CharacterDomain::where('school_id', $user->school_id)
            ->where('status', 'active')
            ->orderBy('display_order')->get();

        abort_unless($domains->isNotEmpty(), 403, 'No active character domains configured. Ask Admin to set them up.');

        // Determine accessible classes
        $classIds = $this->accessibleClassIds($user, $session->id);
        $classes  = SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();

        $selectedClassId = $request->integer('class', $classes->first()?->id ?? 0);
        abort_unless(in_array($selectedClassId, $classIds, true), 403);

        $service  = app(CharacterCalculationService::class);
        $marksheet = $service->classMarksheet($session, $term, $selectedClassId, $domains);

        return view('character.form', compact(
            'session', 'term', 'domains', 'classes', 'selectedClassId', 'marksheet'
        ));
    }

    public function store(Request $request, CharacterCalculationService $service): RedirectResponse
    {
        $user = auth()->user();
        abort_unless(
            $user->isAdmin() || $user->isLeadership() || $user->isHoD()
                || $user->isTeacher() || $user->isChaplain() || $user->hasRole('student_life_coordinator'),
            403
        );

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403);

        $term    = Term::currentForSession($session->id);
        $classId = (int) $request->input('school_class_id');

        $classIds = $this->accessibleClassIds($user, $session->id);
        abort_unless(in_array($classId, $classIds, true), 403);

        $domains = CharacterDomain::where('school_id', $user->school_id)
            ->where('status', 'active')->pluck('id');

        $request->validate([
            'rows'                        => ['required', 'array'],
            'rows.*.learner_id'           => ['required', 'integer', 'exists:learners,id'],
            'rows.*.domains'              => ['required', 'array'],
            'rows.*.domains.*'            => ['nullable', 'integer', 'min:1', 'max:4'],
            'rows.*.notes'                => ['nullable', 'array'],
            'rows.*.notes.*'              => ['nullable', 'string', 'max:300'],
        ]);

        foreach ($request->input('rows') as $row) {
            $learnerId = (int) $row['learner_id'];

            foreach (($row['domains'] ?? []) as $domainId => $level) {
                if (! $domains->contains((int) $domainId) || $level === null || $level === '') {
                    continue;
                }

                CharacterRating::updateOrCreate(
                    [
                        'learner_id'          => $learnerId,
                        'character_domain_id' => (int) $domainId,
                        'academic_session_id' => $session->id,
                        'term_id'             => $term?->id,
                    ],
                    [
                        'level'    => (int) $level,
                        'notes'    => $row['notes'][$domainId] ?? null,
                        'rated_by' => $user->id,
                    ]
                );
            }
        }

        $service->recalculateForSession($session, $term);

        return redirect()->route('character.index')
            ->with('success', 'Character ratings saved and CE-02 recalculated.');
    }

    private function accessibleClassIds($user, int $sessionId): array
    {
        if ($user->isAdmin() || $user->isHoS() || $user->hasRole('student_life_coordinator')) {
            return SchoolClass::where('school_id', $user->school_id)->pluck('id')->all();
        }

        if ($user->isHoD()) {
            return SchoolClass::where('school_id', $user->school_id)
                ->where('department_id', $user->department_id)
                ->pluck('id')->all();
        }

        return \App\Models\TeacherAssignment::where('teacher_id', $user->id)
            ->where('academic_session_id', $sessionId)
            ->pluck('school_class_id')->unique()->values()->all();
    }
}
