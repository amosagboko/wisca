<?php

namespace App\Http\Controllers\Admin;

use App\Models\CharacterDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CharacterDomainController extends AdminController
{
    public function index(): View
    {
        $domains = CharacterDomain::where('school_id', $this->schoolId())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.character.domains.index', compact('domains'));
    }

    public function create(): View
    {
        return view('admin.character.domains.form', [
            'domain' => new CharacterDomain([
                'rubric'        => CharacterDomain::DEFAULT_RUBRIC,
                'passing_level' => 3,
                'display_order' => 0,
                'status'        => 'active',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDomain($request);

        CharacterDomain::create([
            ...$validated,
            'school_id' => $this->schoolId(),
        ]);

        return redirect()->route('admin.character-domains.index')
            ->with('success', 'Character domain created.');
    }

    public function edit(CharacterDomain $characterDomain): View
    {
        abort_unless($characterDomain->school_id === $this->schoolId(), 404);

        return view('admin.character.domains.form', ['domain' => $characterDomain]);
    }

    public function update(Request $request, CharacterDomain $characterDomain): RedirectResponse
    {
        abort_unless($characterDomain->school_id === $this->schoolId(), 404);

        $characterDomain->update($this->validateDomain($request));

        return redirect()->route('admin.character-domains.index')
            ->with('success', 'Character domain updated.');
    }

    public function destroy(CharacterDomain $characterDomain): RedirectResponse
    {
        abort_unless($characterDomain->school_id === $this->schoolId(), 404);

        if ($characterDomain->ratings()->exists()) {
            return back()->withErrors(['domain' => 'Cannot delete — this domain has existing ratings.']);
        }

        $characterDomain->delete();

        return redirect()->route('admin.character-domains.index')
            ->with('success', 'Character domain deleted.');
    }

    private function validateDomain(Request $request): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'code'          => ['nullable', 'string', 'max:20'],
            'description'   => ['nullable', 'string', 'max:500'],
            'passing_level' => ['required', 'integer', 'min:1', 'max:4'],
            'display_order' => ['integer', 'min:0'],
            'status'        => ['required', 'in:active,inactive'],
            // rubric sent as JSON string from textarea
            'rubric_json'   => ['nullable', 'string'],
        ]);

        // Parse rubric from JSON textarea if provided
        if (! empty($data['rubric_json'])) {
            $decoded = json_decode($data['rubric_json'], true);
            $data['rubric'] = is_array($decoded) ? $decoded : CharacterDomain::DEFAULT_RUBRIC;
        } else {
            $data['rubric'] = CharacterDomain::DEFAULT_RUBRIC;
        }
        unset($data['rubric_json']);

        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        return $data;
    }
}
