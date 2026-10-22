@php
    $f = $filters;
    $activeFilters = $f['termId'] || $f['filterClassId'] || $f['filterSubjectId'] || $f['filterStatus'] !== '' || ($f['search'] ?? '') !== '' || ($f['queue'] ?? '') !== '';
    $sortUrl = function (string $column) use ($f) {
        $direction = ($f['sort'] ?? '') === $column && ($f['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc';

        return route('schemes.index', array_filter([
            'session_id' => $f['sessionId'] ?? null,
            'term_id' => $f['termId'] ?: null,
            'class_id' => $f['filterClassId'] ?: null,
            'subject_id' => $f['filterSubjectId'] ?: null,
            'status' => $f['filterStatus'] ?: null,
            'q' => $f['search'] ?: null,
            'queue' => $f['queue'] ?: null,
            'sort' => $column,
            'direction' => $direction,
        ]));
    };
@endphp

<x-portal-layout title="Schemes of Work">
    <x-portal.page-intro
        eyebrow="Appendix C · AE-01"
        title="Schemes of work"
        meta="Teachers upload the class and subject scheme. The section HOD approves and activates. Teachers plan against the Active version only."
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
                @foreach (['draft' => 'Draft', 'returned' => 'Returned', 'submitted' => 'Submitted', 'approved' => 'Approved', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)
                    <option value="{{ $value }}" @selected($f['filterStatus'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="f_search" value="Search" />
            <input id="f_search" type="search" name="q" value="{{ $f['search'] }}" placeholder="Class, subject, session" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>
        @if ($canReviewQueue ?? false)
            <div class="flex items-center gap-2 pt-5">
                <input id="f_queue" type="checkbox" name="queue" value="review" @checked(($f['queue'] ?? '') === 'review') class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <x-input-label for="f_queue" value="Needs my review" />
            </div>
        @endif
        <input type="hidden" name="sort" value="{{ $f['sort'] }}">
        <input type="hidden" name="direction" value="{{ $f['direction'] }}">
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('schemes.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    @if ($schemes->total())
        <div class="portal-enter mb-6 flex flex-wrap gap-4">
            <div class="flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Versions</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ $schemes->total() }}</p>
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

    <x-portal.panel :title="'Scheme versions'.($schemes->total() ? ' ('.$schemes->total().')' : '')">
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
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">S/N</th>
                            <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('session') }}" class="hover:underline">Session</a></th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Term</th>
                            <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('class') }}" class="hover:underline">Class</a></th>
                            <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('subject') }}" class="hover:underline">Subject</a></th>
                            <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('status') }}" class="hover:underline">Status</a></th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Timing</th>
                            <th class="px-5 py-3 font-semibold text-slate-600"><a href="{{ $sortUrl('updated_at') }}" class="hover:underline">Last Updated</a></th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($schemes as $scheme)
                            <tr>
                                <td class="px-5 sm:px-6 py-3 text-slate-500">{{ $schemes->firstItem() + $loop->index }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $scheme->academicSession?->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $scheme->term?->name }}</td>
                                <td class="px-5 py-3 text-slate-800">{{ $scheme->schoolClass?->name }}</td>
                                <td class="px-5 py-3 text-slate-800">{{ $scheme->subject?->name }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
                                        'bg-emerald-50 text-emerald-700' => $scheme->isActive(),
                                        'bg-sky-50 text-sky-700' => $scheme->isApproved(),
                                        'bg-slate-100 text-slate-600' => $scheme->isArchived(),
                                        'bg-amber-50 text-amber-700' => $scheme->isSubmitted(),
                                        'bg-rose-50 text-rose-700' => $scheme->isReturned(),
                                        'bg-slate-50 text-slate-500' => $scheme->isEditableDraft() && ! $scheme->isReturned(),
                                    ])>{{ $scheme->processLabel() }} v{{ $scheme->version }}</span>
                                </td>
                                <td class="px-5 py-3 text-xs font-medium">
                                    @if ($scheme->submittedOnTime() === true)
                                        <span class="text-emerald-700">On time</span>
                                    @elseif ($scheme->submittedOnTime() === false)
                                        <span class="text-amber-700">Late</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $scheme->updated_at?->format('d M Y H:i') }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap space-x-2">
                                    <a href="{{ route('schemes.show', $scheme) }}" class="font-medium text-[#0f2d4a] hover:underline">View</a>
                                    @if ($canPrepare && $scheme->isEditableDraft() && (int) $scheme->uploaded_by === (int) auth()->id())
                                        <a href="{{ route('schemes.edit', $scheme) }}" class="font-medium text-[#0f2d4a] hover:underline">Edit</a>
                                    @endif
                                    @if ($canPrepare)
                                        <form method="POST" action="{{ route('schemes.clone', $scheme) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="font-medium text-[#0f2d4a] hover:underline">Clone as draft</button>
                                        </form>
                                        <a href="{{ route('schemes.clone-session', $scheme) }}" class="font-medium text-[#0f2d4a] hover:underline">Clone to session</a>
                                    @endif
                                    @if ($canPrepare && $scheme->canBePermanentlyDeleted() && (int) $scheme->uploaded_by === (int) auth()->id())
                                        <button type="button"
                                                onclick="document.getElementById('delete-scheme-{{ $scheme->id }}').classList.toggle('hidden')"
                                                class="font-medium text-red-700 hover:underline">Delete</button>
                                        <div id="delete-scheme-{{ $scheme->id }}" class="hidden mt-3 max-w-sm text-left rounded-lg border border-red-200 bg-red-50 px-3 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-red-800">This action is permanent</p>
                                            <p class="mt-1 text-xs text-red-900">
                                                Version {{ $scheme->version }} of {{ $scheme->schoolClass?->name }} — {{ $scheme->subject?->name }}
                                                ({{ $scheme->term?->name }}) will be deleted. Weekly topics on this version will be removed
                                                and cannot be recovered.
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
            @if ($schemes->hasPages())
                <div class="mt-4">{{ $schemes->onEachSide(1)->links() }}</div>
            @endif
        @endif
    </x-portal.panel>
</x-portal-layout>
