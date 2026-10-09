@php
    $f = $filters;
    $activeFilters = $f['termId'] || $f['filterClassId'] || $f['filterSubjectId'] || $f['filterStatus'] !== '';
    $activeCount = $schemes->where('status', 'active')->count();
    $inFlight = $schemes->filter(fn ($scheme) => in_array($scheme->status, ['draft', 'approved'], true))->count();
@endphp

<x-portal-layout title="Schemes of Work">
    <x-portal.page-intro
        eyebrow="Appendix C · AE-01"
        title="Schemes of work"
        meta="Controlled curriculum baseline: draft, dual approval (HoS then Board), then Active. Teachers plan against the Active version only."
    >
        @if ($canPrepare)
            <x-slot:actions>
                <a href="{{ route('schemes.create') }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                    New draft
                </a>
            </x-slot:actions>
        @endif
    </x-portal.page-intro>

    <form method="GET" action="{{ route('schemes.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="f_session" value="Session" />
            <select id="f_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_term" value="Term" />
            <select id="f_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_class" value="Class" />
            <select id="f_class" name="class_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0">All classes</option>
                @foreach ($allClasses as $class)
                    <option value="{{ $class->id }}" @selected($f['filterClassId'] === $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_subject" value="Subject" />
            <select id="f_subject" name="subject_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0">All subjects</option>
                @foreach ($allSubjects as $subject)
                    <option value="{{ $subject->id }}" @selected($f['filterSubjectId'] === $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_status" value="Status" />
            <select id="f_status" name="status" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">All</option>
                @foreach (['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)
                    <option value="{{ $value }}" @selected($f['filterStatus'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('schemes.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    @if ($schemes->isNotEmpty())
        <div class="portal-enter mb-6 flex flex-wrap gap-4">
            <div class="flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Versions</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ $schemes->count() }}</p>
            </div>
            <div class="flex-1 min-w-[120px] rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-emerald-500">Active</p>
                <p class="mt-1 text-2xl font-display font-semibold text-emerald-700">{{ $activeCount }}</p>
            </div>
            <div class="flex-1 min-w-[120px] rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-amber-500">In flight</p>
                <p class="mt-1 text-2xl font-display font-semibold text-amber-700">{{ $inFlight }}</p>
            </div>
        </div>
    @endif

    <x-portal.panel :title="'Scheme versions'.($schemes->count() ? ' ('.$schemes->count().')' : '')">
        @if ($schemes->isEmpty())
            <p class="text-sm text-slate-500">
                No schemes of work match these filters.
                @if ($canPrepare)
                    <a href="{{ route('schemes.create') }}" class="font-medium text-[#0f2d4a] hover:underline">Create a draft</a>.
                @endif
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Term</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Version</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Topics</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Approvals</th>
                            <th class="px-5 py-3 font-semibold text-slate-600"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($schemes as $scheme)
                            <tr>
                                <td class="px-5 sm:px-6 py-3">
                                    <p class="font-medium text-slate-800">{{ $scheme->schoolClass?->name }} — {{ $scheme->subject?->name }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $scheme->term?->name }}</td>
                                <td class="px-5 py-3 text-slate-600">v{{ $scheme->version }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
                                        'bg-emerald-50 text-emerald-700' => $scheme->isActive(),
                                        'bg-sky-50 text-sky-700' => $scheme->isApproved(),
                                        'bg-slate-100 text-slate-600' => $scheme->isArchived(),
                                        'bg-amber-50 text-amber-700' => $scheme->isSubmitted(),
                                        'bg-slate-50 text-slate-500' => $scheme->isEditableDraft(),
                                    ])>{{ $scheme->processLabel() }}</span>
                                    @if ($scheme->isActive())
                                        <span class="ml-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Baseline</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $scheme->topics->count() }}</td>
                                <td class="px-5 py-3 text-xs text-slate-500">
                                    HoS {{ $scheme->hasHosApproval() ? '✓' : '—' }}
                                    · Board {{ $scheme->hasBoardApproval() ? '✓' : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('schemes.show', $scheme) }}" class="font-medium text-[#0f2d4a] hover:underline">Open</a>
                                    @if ($canPrepare && $scheme->canBePermanentlyDeleted())
                                        <span class="mx-1 text-slate-300">·</span>
                                        <button type="button"
                                                onclick="document.getElementById('delete-scheme-{{ $scheme->id }}').classList.toggle('hidden')"
                                                class="font-medium text-red-700 hover:underline">Delete</button>
                                        <div id="delete-scheme-{{ $scheme->id }}" class="hidden mt-3 max-w-sm text-left rounded-lg border border-red-200 bg-red-50 px-3 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-red-800">This action is permanent</p>
                                            <p class="mt-1 text-xs text-red-900">
                                                Version {{ $scheme->version }} of {{ $scheme->schoolClass?->name }} — {{ $scheme->subject?->name }}
                                                ({{ $scheme->term?->name }}) will be deleted. Weekly topics on this version will be removed
                                                and cannot be recovered. Lesson plans and coverage on other versions are not affected.
                                            </p>
                                            <form method="POST" action="{{ route('schemes.destroy', $scheme) }}" class="mt-3"
                                                  onsubmit="return confirm('This permanently deletes version {{ $scheme->version }} of {{ $scheme->schoolClass?->name }} — {{ $scheme->subject?->name }}. Topics on this version cannot be recovered. Continue?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-red-800 hover:underline">Permanently delete this version</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>
</x-portal-layout>
