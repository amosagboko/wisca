@props([
    'teachers',
    'classes',
    'subjects',
    'showGrouping' => true,
])

<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div class="flex-1">
            <label for="hod-search" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Search</label>
            <input
                id="hod-search"
                type="search"
                x-model="q"
                placeholder="Teacher, class, subject, or topic"
                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0f2d4a] focus:ring-[#0f2d4a]"
            >
        </div>
        <div class="grid flex-1 gap-3 sm:grid-cols-3">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Teacher</label>
                <select x-model="teacher" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0f2d4a] focus:ring-[#0f2d4a]">
                    <option value="">All teachers</option>
                    @foreach ($teachers as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Class</label>
                <select x-model="classId" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0f2d4a] focus:ring-[#0f2d4a]">
                    <option value="">All classes</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Subject</label>
                <select x-model="subjectId" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0f2d4a] focus:ring-[#0f2d4a]">
                    <option value="">All subjects</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        @if ($showGrouping)
            <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                <button type="button" @click="groupBy = 'teacher'" :class="groupBy === 'teacher' ? 'bg-[#0f2d4a] text-white' : 'text-slate-600 hover:bg-white'" class="rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-widest">By teacher</button>
                <button type="button" @click="groupBy = 'class'" :class="groupBy === 'class' ? 'bg-[#0f2d4a] text-white' : 'text-slate-600 hover:bg-white'" class="rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-widest">By class</button>
                <button type="button" @click="groupBy = 'list'" :class="groupBy === 'list' ? 'bg-[#0f2d4a] text-white' : 'text-slate-600 hover:bg-white'" class="rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-widest">List</button>
            </div>
        @else
            <div></div>
        @endif
        <button type="button" @click="clear()" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">Clear filters</button>
    </div>

    @if ($classes->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($classes as $class)
                <button
                    type="button"
                    @click="classId = classId === @js((string) $class->id) ? '' : @js((string) $class->id)"
                    :class="classId === @js((string) $class->id) ? 'border-[#0f2d4a] bg-[#0f2d4a] text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                    class="rounded-full border px-3 py-1 text-xs font-semibold"
                >
                    {{ $class->name }}
                </button>
            @endforeach
        </div>
    @endif
</div>
