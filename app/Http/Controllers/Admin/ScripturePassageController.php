<?php

namespace App\Http\Controllers\Admin;

use App\Models\ScripturePassage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScripturePassageController extends AdminController
{
    public function index(): View
    {
        $passages = ScripturePassage::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('reference')
            ->get();

        return view('admin.scripture.passages.index', compact('passages'));
    }

    public function create(): View
    {
        return view('admin.scripture.passages.form', [
            'passage' => new ScripturePassage([
                'display_order' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ScripturePassage::create([
            ...$this->validatePassage($request),
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.scripture-passages.index')
            ->with('success', 'Scripture passage created.');
    }

    public function edit(ScripturePassage $scripturePassage): View
    {
        abort_unless($scripturePassage->school_id === $this->schoolId(), 404);

        return view('admin.scripture.passages.form', ['passage' => $scripturePassage]);
    }

    public function update(Request $request, ScripturePassage $scripturePassage): RedirectResponse
    {
        abort_unless($scripturePassage->school_id === $this->schoolId(), 404);

        $scripturePassage->update($this->validatePassage($request));

        return redirect()->route('admin.scripture-passages.index')
            ->with('success', 'Scripture passage updated.');
    }

    public function destroy(ScripturePassage $scripturePassage): RedirectResponse
    {
        abort_unless($scripturePassage->school_id === $this->schoolId(), 404);

        if ($scripturePassage->assessments()->exists()) {
            return back()->withErrors(['passage' => 'Cannot delete this passage because assessments already exist.']);
        }

        $scripturePassage->delete();

        return redirect()->route('admin.scripture-passages.index')
            ->with('success', 'Scripture passage deleted.');
    }

    private function validatePassage(Request $request): array
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
            'verse_text' => ['nullable', 'string', 'max:2000'],
            'theme' => ['nullable', 'string', 'max:150'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] = (int) ($data['display_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
