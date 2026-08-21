<x-portal-layout title="Teacher Assignments">
    <x-portal.page-intro
        eyebrow="School Structure"
        title="Teacher Assignments"
        meta="Link teachers to class-subject combinations for a given academic session."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.assignments.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add assignment
        </a>
    </div>

    <x-portal.panel title="All assignments">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Teacher</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Subject</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Session</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $assignment->teacher->name }}</td>
                            <td class="px-5 py-3">{{ $assignment->schoolClass->name }}</td>
                            <td class="px-5 py-3">{{ $assignment->subject->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $assignment->academicSession->name }}</td>
                            <td class="px-5 py-3 capitalize">{{ $assignment->status }}</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.assignments.edit', $assignment) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.assignments.destroy', $assignment) }}" class="inline" onsubmit="return confirm('Remove this assignment?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 sm:px-6 py-8 text-center text-slate-500">No teacher assignments yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
