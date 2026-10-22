@php
    $f = $filters;
    $activeFilters = $f['sectionId'] || $f['teacherId'] || $f['classId'] || $f['subjectId'] || $f['hodId'];
    $sortUrl = function (string $column) use ($f) {
        $direction = ($f['sort'] ?? '') === $column && ($f['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc';

        return route('admin.assignments.index', array_filter([
            'section_id' => $f['sectionId'] ?: null,
            'teacher_id' => $f['teacherId'] ?: null,
            'class_id' => $f['classId'] ?: null,
            'subject_id' => $f['subjectId'] ?: null,
            'hod_id' => $f['hodId'] ?: null,
            'sort' => $column,
            'direction' => $direction,
        ]));
    };
@endphp

<x-portal-layout title="Teacher Assignments">
    <x-portal.page-intro
        eyebrow="School Structure"
        title="Teacher Assignments"
        meta="Link teachers to class-subject combinations for a given academic session. Section and HOD come from the class."
    />

    <form method="GET" action="{{ route('admin.assignments.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="f_section" value="Section" />
            <select id="f_section" name="section_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0">All sections</option>
                @foreach ($sections as $section)
                    <option value="{{ $section->id }}" @selected($f['sectionId'] === $section->id)>{{ $section->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_teacher" value="Teacher" />
            <select id="f_teacher" name="teacher_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0">All teachers</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected($f['teacherId'] === $teacher->id)>{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_class" value="Class" />
            <select id="f_class" name="class_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0">All classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($f['classId'] === $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_subject" value="Subject" />
            <select id="f_subject" name="subject_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0">All subjects</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected($f['subjectId'] === $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_hod" value="HOD" />
            <select id="f_hod" name="hod_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0">All HODs</option>
                @foreach ($hods as $hod)
                    <option value="{{ $hod->id }}" @selected($f['hodId'] === $hod->id)>{{ $hod->name }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="sort" value="{{ $f['sort'] }}">
        <input type="hidden" name="direction" value="{{ $f['direction'] }}">
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('admin.assignments.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.assignments.create') }}" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Add assignment
        </a>
    </div>

    <x-portal.panel :title="'All assignments'.($assignments->total() ? ' ('.$assignments->total().')' : '')">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('teacher') }}" class="hover:underline">Teacher</a></th>
                        <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('section') }}" class="hover:underline">Section</a></th>
                        <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('hod') }}" class="hover:underline">HOD</a></th>
                        <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('class') }}" class="hover:underline">Class</a></th>
                        <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('subject') }}" class="hover:underline">Subject</a></th>
                        <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('session') }}" class="hover:underline">Session</a></th>
                        <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('status') }}" class="hover:underline">Status</a></th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $assignment->teacher->name }}</td>
                            <td class="px-5 py-3">{{ $assignment->schoolClass->schoolSection?->name ?? 'Unassigned' }}</td>
                            <td class="px-5 py-3">{{ $assignment->schoolClass->schoolSection?->hod?->name ?? '—' }}</td>
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
                            <td colspan="8" class="px-5 sm:px-6 py-8 text-center text-slate-500">No teacher assignments match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($assignments->hasPages())
            <div class="mt-4">{{ $assignments->onEachSide(1)->links() }}</div>
        @endif
    </x-portal.panel>
</x-portal-layout>
