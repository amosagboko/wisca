@php
    $f = $filters;
    $percent = $summary['total_parents'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 85;
@endphp

<x-portal-layout title="Parent Partnership">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-07"
        title="Parent-school partnership commitments"
        :meta="$session->name.' · '.$term->name.'. Signed partnership commitments ÷ total parent body. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('partnership.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="p_session" value="Session" />
            <select id="p_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="p_term" value="Term" />
            <select id="p_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $term->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="p_status" value="Status" />
            <select id="p_status" name="status" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" @selected($f['status'] === '')>All</option>
                <option value="signed" @selected($f['status'] === 'signed')>Signed</option>
                <option value="pending" @selected($f['status'] === 'pending')>Pending</option>
                <option value="declined" @selected($f['status'] === 'declined')>Declined</option>
            </select>
        </div>
        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('partnership.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">CE-07 this term</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['signed'] }} signed of {{ $summary['total_parents'] }} active parents · target {{ $target }}%</p>
                @if ($charter)
                    <p class="mt-2 text-xs text-white/60">Charter: {{ $charter->title }}@if ($charter->version) ({{ $charter->version }})@endif</p>
                @endif
            </div>
            <a href="{{ route('partnership.create') }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Record commitments
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <x-portal.panel title="Parent commitments ({{ $rows->count() }})">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Parent/guardian</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Learners</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Signed</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Method</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $guardian = $row['guardian'];
                            $signature = $row['signature'];
                        @endphp
                        <tr class="border-t border-slate-100">
                            <td class="px-5 sm:px-6 py-3">
                                <p class="font-medium text-slate-800">{{ $guardian->name }}</p>
                                @if ($guardian->email)
                                    <p class="text-xs text-slate-500">{{ $guardian->email }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                @forelse ($guardian->learners as $learner)
                                    {{ $learner->name }}@if (! $loop->last), @endif
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $row['status'] === 'signed',
                                    'bg-amber-100 text-amber-800' => $row['status'] === 'pending',
                                    'bg-red-100 text-red-800' => $row['status'] === 'declined',
                                ])>{{ ucfirst($row['status']) }}</span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $signature?->signed_at?->format('d M Y') ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $signature?->signature_method ? str_replace('_', ' ', ucfirst($signature->signature_method)) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 sm:px-6 py-8 text-center text-slate-500">
                                No active parents registered.
                                @if (auth()->user()?->isAdmin())
                                    <a href="{{ route('admin.guardians.create') }}" class="text-[#0f2d4a] font-semibold hover:underline">Add parents in the registry</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-portal.panel>
</x-portal-layout>
