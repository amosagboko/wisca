<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Guardian;
use App\Models\PartnershipSignature;
use App\Models\Term;
use App\Services\PartnershipCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PartnershipSignatureController extends Controller
{
    public function index(Request $request, PartnershipCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $allSessions = AcademicSession::where('school_id', $user->school_id)->orderByDesc('start_date')->get();
        $sessionId = $request->integer('session_id');
        $session = $sessionId ? $allSessions->firstWhere('id', $sessionId) : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)->orderBy('start_date')->get();
        $termId = $request->integer('term_id');
        $term = $termId ? $allTerms->firstWhere('id', $termId) : Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $statusFilter = $request->string('status')->toString();
        if (! in_array($statusFilter, ['', 'pending', 'signed', 'declined'], true)) {
            $statusFilter = '';
        }

        $summary = $service->sessionSummary($session, $term);
        $rows = $service->guardianRows($session, $term, $statusFilter ?: null);
        $charter = $service->activeCharterForSchool($user->school_id);

        $filters = compact('sessionId', 'termId');
        $filters['status'] = $statusFilter;
        $activeFilters = (bool) ($sessionId || $termId || $statusFilter);

        return view('partnership.index', compact(
            'session',
            'term',
            'allSessions',
            'allTerms',
            'summary',
            'rows',
            'charter',
            'filters',
            'activeFilters'
        ));
    }

    public function create(Request $request, PartnershipCalculationService $service): View
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $charter = $service->activeCharterForSchool($user->school_id);
        abort_unless($charter, 403, 'No active partnership charter configured.');

        $rows = $service->guardianRows($session, $term);

        return view('partnership.form', compact('session', 'term', 'charter', 'rows'));
    }

    public function store(Request $request, PartnershipCalculationService $service): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->canAccess($user), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');
        $term = Term::currentForSession($session->id);
        abort_unless($term, 403, 'No active term configured.');

        $charter = $service->activeCharterForSchool($user->school_id);
        abort_unless($charter, 403, 'No active partnership charter configured.');

        $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.parent_id' => ['required', 'integer', 'exists:parents,id'],
            'rows.*.status' => ['required', 'in:pending,signed,declined'],
            'rows.*.signature_method' => ['nullable', 'in:in_person,paper,digital,other'],
            'rows.*.notes' => ['nullable', 'string', 'max:300'],
        ]);

        foreach ($request->input('rows', []) as $row) {
            $guardian = Guardian::where('school_id', $user->school_id)->find($row['parent_id']);
            if (! $guardian) {
                continue;
            }

            $status = $row['status'];
            $signedAt = $status === 'signed' ? now() : null;

            PartnershipSignature::updateOrCreate(
                [
                    'parent_id' => $guardian->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                ],
                [
                    'school_id' => $user->school_id,
                    'partnership_charter_id' => $charter->id,
                    'status' => $status,
                    'signature_method' => $status === 'signed' ? ($row['signature_method'] ?? null) : null,
                    'signed_at' => $signedAt,
                    'notes' => $row['notes'] ?? null,
                    'recorded_by' => $user->id,
                ]
            );
        }

        $service->recalculateForSession($session, $term);

        return redirect()->route('partnership.index')
            ->with('success', 'Partnership commitments recorded and CE-07 recalculated.');
    }

    private function canAccess($user): bool
    {
        return $user->isAdmin()
            || $user->isHoS()
            || $user->isParentRelationsLead();
    }
}
