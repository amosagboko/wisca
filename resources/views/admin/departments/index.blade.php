<x-portal-layout title="Departments">
    <x-portal.page-intro
        eyebrow="School Structure"
        title="Departments"
        meta="Assign subjects and Heads of Department so verification queues stay inside one department."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.departments.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add department
        </a>
    </div>

    <x-portal.panel title="All departments">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Subjects</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Staff</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $department->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $department->subjects_count }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $department->heads_count }}</td>
                            <td class="px-5 py-3 capitalize">{{ $department->status }}</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.departments.edit', $department) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="inline" onsubmit="return confirm('Delete this department?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No departments yet. Add one, then attach subjects and HODs.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
