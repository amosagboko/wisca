<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guardian;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianController extends AdminController
{
    public function index(): View
    {
        $guardians = Guardian::where('school_id', $this->schoolId())
            ->with(['learners.schoolClass'])
            ->orderBy('name')
            ->get();

        return view('admin.partnership.guardians.index', compact('guardians'));
    }

    public function create(): View
    {
        $learners = Learner::where('school_id', $this->schoolId())
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        return view('admin.partnership.guardians.form', [
            'guardian' => new Guardian(['status' => 'active']),
            'learners' => $learners,
            'selectedLearnerIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateGuardian($request);

        $guardian = Guardian::create([
            ...$data,
            'school_id' => $this->schoolId(),
        ]);

        $guardian->learners()->sync($request->input('learner_ids', []));

        return redirect()->route('admin.guardians.index')
            ->with('success', 'Parent/guardian created.');
    }

    public function edit(Guardian $guardian): View
    {
        abort_unless($guardian->school_id === $this->schoolId(), 404);

        $learners = Learner::where('school_id', $this->schoolId())
            ->where('status', 'enrolled')
            ->orderBy('name')
            ->get();

        return view('admin.partnership.guardians.form', [
            'guardian' => $guardian,
            'learners' => $learners,
            'selectedLearnerIds' => $guardian->learners->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        abort_unless($guardian->school_id === $this->schoolId(), 404);

        $guardian->update($this->validateGuardian($request));
        $guardian->learners()->sync($request->input('learner_ids', []));

        return redirect()->route('admin.guardians.index')
            ->with('success', 'Parent/guardian updated.');
    }

    public function destroy(Guardian $guardian): RedirectResponse
    {
        abort_unless($guardian->school_id === $this->schoolId(), 404);

        if ($guardian->signatures()->exists()) {
            return back()->withErrors(['guardian' => 'Cannot delete this parent because partnership signatures exist.']);
        }

        $guardian->delete();

        return redirect()->route('admin.guardians.index')
            ->with('success', 'Parent/guardian deleted.');
    }

    private function validateGuardian(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'relationship' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive'],
            'learner_ids' => ['nullable', 'array'],
            'learner_ids.*' => ['integer', 'exists:learners,id'],
        ]);

        return $data;
    }
}
