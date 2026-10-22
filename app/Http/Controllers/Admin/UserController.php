<?php

namespace App\Http\Controllers\Admin;

use App\Models\SchoolSection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends AdminController
{
    public function index(): View
    {
        $users = User::where('school_id', $this->schoolId())
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->with(['roles', 'ledSchoolSections'])
            ->orderBy('name')
            ->get();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['status' => 'active']),
            'roles' => $this->assignableRoles(),
            'sections' => $this->schoolSections(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        $user = User::create([
            'school_id' => $this->schoolId(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
        ]);

        $user->syncRoles([$validated['role']]);
        $this->syncHodSection($user, $validated['role'], $validated['school_section_id'] ?? null);

        if ($request->hasFile('passport')) {
            $user->storePassport($request->file('passport'));
        }

        return redirect()->route('admin.users.index')->with('success', 'Staff member created.');
    }

    public function edit(User $user): View
    {
        $this->ensureSchoolUser($user);

        return view('admin.users.form', [
            'user' => $user->load(['roles', 'ledSchoolSections']),
            'roles' => $this->assignableRoles(),
            'sections' => $this->schoolSections(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureSchoolUser($user);

        $validated = $this->validateUser($request, $user);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);
        $this->syncHodSection($user, $validated['role'], $validated['school_section_id'] ?? null);

        if ($request->boolean('remove_passport') && ! $request->hasFile('passport')) {
            $user->deletePassportFile();
        }

        if ($request->hasFile('passport')) {
            $user->storePassport($request->file('passport'));
        }

        return redirect()->route('admin.users.index')->with('success', 'Staff member updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureSchoolUser($user);

        if ($user->isAdmin()) {
            return back()->withErrors(['user' => 'Administrator accounts cannot be deleted from here.']);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Staff member removed.');
    }

    protected function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'passport' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_passport' => ['nullable', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'in:active,inactive'],
            'role' => ['required', 'string', Rule::in(array_keys($this->assignableRoles()))],
            'school_section_id' => [
                $request->input('role') === 'head_of_department' ? 'required' : 'nullable',
                Rule::exists('school_sections', 'id')->where(fn ($query) => $query->where('school_id', $this->schoolId())),
            ],
        ], [
            'school_section_id.required' => 'Select a school section for this Head of Department.',
        ]);
    }

    protected function syncHodSection(User $user, string $role, mixed $sectionId): void
    {
        $sectionId = $sectionId ? (int) $sectionId : null;

        SchoolSection::where('school_id', $this->schoolId())
            ->where('hod_id', $user->id)
            ->when($role === 'head_of_department' && $sectionId, fn ($query) => $query->where('id', '!=', $sectionId))
            ->update(['hod_id' => null]);

        if ($role !== 'head_of_department' || ! $sectionId) {
            return;
        }

        SchoolSection::where('id', $sectionId)
            ->where('school_id', $this->schoolId())
            ->update(['hod_id' => $user->id]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolSection>
     */
    protected function schoolSections()
    {
        return SchoolSection::where('school_id', $this->schoolId())
            ->orderBy('name')
            ->get();
    }

    protected function ensureSchoolUser(User $user): void
    {
        abort_unless($user->school_id === $this->schoolId(), 404);
        abort_if($user->isAdmin() && $user->id !== auth()->id(), 403);
    }
}
