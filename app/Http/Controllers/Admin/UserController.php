<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
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
            ->with(['roles', 'department'])
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
            'departments' => $this->schoolDepartments(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        $user = User::create([
            'school_id' => $this->schoolId(),
            'department_id' => $this->departmentIdForRole($validated['role'], $validated['department_id'] ?? null),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
        ]);

        $user->syncRoles([$validated['role']]);

        if ($request->hasFile('passport')) {
            $user->storePassport($request->file('passport'));
        }

        return redirect()->route('admin.users.index')->with('success', 'Staff member created.');
    }

    public function edit(User $user): View
    {
        $this->ensureSchoolUser($user);

        return view('admin.users.form', [
            'user' => $user->load('roles'),
            'roles' => $this->assignableRoles(),
            'departments' => $this->schoolDepartments(),
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
            'department_id' => $this->departmentIdForRole($validated['role'], $validated['department_id'] ?? null),
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

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
            'department_id' => [
                $request->input('role') === 'head_of_department' ? 'required' : 'nullable',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where('school_id', $this->schoolId())
                    ->where('status', 'active')),
            ],
        ]);
    }

    protected function departmentIdForRole(string $role, mixed $departmentId): ?int
    {
        if ($role !== 'head_of_department') {
            return null;
        }

        return $departmentId ? (int) $departmentId : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Department>
     */
    protected function schoolDepartments()
    {
        return Department::where('school_id', $this->schoolId())
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    protected function ensureSchoolUser(User $user): void
    {
        abort_unless($user->school_id === $this->schoolId(), 404);
        abort_if($user->isAdmin() && $user->id !== auth()->id(), 403);
    }
}
