<x-portal-layout title="Staff & Teachers">
    <x-portal.page-intro
        eyebrow="School Structure"
        title="Staff & Teachers"
        meta="Create accounts and assign roles. Administrator accounts are managed separately."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add staff member
        </a>
    </div>

    <x-portal.panel title="All staff">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Email</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Role</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <x-user-avatar :user="$user" size="xs" />
                                    <span class="font-medium text-slate-800">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $user->email }}</td>
                            <td class="px-5 py-3">{{ $roles[$user->roles->first()?->name] ?? $user->roles->first()?->name ?? '—' }}</td>
                            <td class="px-5 py-3 capitalize">{{ $user->status }}</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('Remove this staff member?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No staff members yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
