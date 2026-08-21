@php
    $f = $filters;
    $activeFilters = $f['sessionId'] || $f['termId'] || $f['typeId'] || $f['filterStatus'] !== '';
@endphp

<x-portal-layout title="Chapel Sessions">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-01"
        title="Chapel sessions"
        meta="Schedule and manage chapel, assembly, and devotion sessions. Mark as Held after roll is taken."
    />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.chapel-sessions.create') }}"
           class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
            Schedule session
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('admin.chapel-sessions.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">

        @if ($allSessions->count() > 1)
            <div>
                <x-input-label for="s_session" value="Session" />
                <select id="s_session" name="session_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($allSessions as $s)
                        <option value="{{ $s->id }}" @selected($s->id === ($session?->id))>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($allTerms->count() > 1)
            <div>
                <x-input-label for="s_term" value="Term" />
                <select id="s_term" name="term_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['termId'] === 0)>All terms</option>
                    @foreach ($allTerms as $t)
                        <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($activityTypes->count() > 1)
            <div>
                <x-input-label for="s_type" value="Activity type" />
                <select id="s_type" name="type_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['typeId'] === 0)>All types</option>
                    @foreach ($activityTypes as $at)
                        <option value="{{ $at->id }}" @selected($at->id === $f['typeId'])>{{ $at->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <x-input-label for="s_status" value="Status" />
            <select id="s_status" name="status"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['filterStatus'] === '')>All statuses</option>
                <option value="scheduled" @selected($f['filterStatus'] === 'scheduled')>Scheduled</option>
                <option value="held"      @selected($f['filterStatus'] === 'held')>Held</option>
                <option value="cancelled" @selected($f['filterStatus'] === 'cancelled')>Cancelled</option>
            </select>
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('admin.chapel-sessions.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <x-portal.panel :title="'Sessions ('.$sessions->count().')'">
        @if ($sessions->isEmpty())
            <p class="text-sm text-slate-500">
                {{ $activeFilters ? 'No sessions match the current filters.' : 'No sessions scheduled yet.' }}
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Type</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Theme</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Led by</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Term</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $cs)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $cs->session_date->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $cs->activityType?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $cs->theme ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $cs->leader?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-500 text-xs">{{ $cs->term?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $cs->status === 'held',
                                        'bg-amber-100 text-amber-800'     => $cs->status === 'scheduled',
                                        'bg-slate-100 text-slate-600'     => $cs->status === 'cancelled',
                                    ])>{{ ucfirst($cs->status) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right space-x-2">
                                    <a href="{{ route('admin.chapel-sessions.edit', $cs) }}"
                                       class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('admin.chapel-sessions.destroy', $cs) }}"
                                          class="inline" onsubmit="return confirm('Delete this session?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    <div class="mt-4 text-xs text-slate-400">Roll-taking is done by Chaplain / Admin Officer from the <a href="{{ route('chapel.index') }}" class="text-[#0f2d4a] hover:underline">Chapel index</a>.</div>
</x-portal-layout>
