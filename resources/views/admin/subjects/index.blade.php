<x-portal-layout title="Subjects">
    <x-portal.page-intro
        eyebrow="School Structure"
        title="Subjects"
        meta="Subjects are linked to classes through teacher assignments and schemes of work."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.subjects.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add subject
        </a>
    </div>

    <x-portal.panel title="All subjects">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Code</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $subject->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $subject->code ?: '—' }}</td>
                            <td class="px-5 py-3 capitalize">{{ $subject->status }}</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.subjects.edit', $subject) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}" class="inline" onsubmit="return confirm('Delete this subject?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 sm:px-6 py-8 text-center text-slate-500">No subjects configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
