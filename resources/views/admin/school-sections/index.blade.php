<x-portal-layout title="School Sections">
    <x-portal.page-intro
        eyebrow="School Structure"
        title="School sections"
        meta="Group classes into sections such as EYFS or Basic School. Each section has one HOD who reviews schemes of work and lesson plans for those classes."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.school-sections.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add section
        </a>
    </div>

    <x-portal.panel title="All sections">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Name</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">HOD</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Classes</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sections as $section)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $section->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $section->hod?->name ?? 'Unassigned' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $section->classes_count }}</td>
                            <td class="px-5 py-3 capitalize">{{ $section->status }}</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <a href="{{ route('admin.school-sections.edit', $section) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.school-sections.destroy', $section) }}" class="inline" onsubmit="return confirm('Delete this school section?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">No school sections yet. Add EYFS or Basic School, then assign classes and a HOD.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
