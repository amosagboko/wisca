@php
    $percent = $summary['enrolled_families'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $target = 80;
@endphp

<x-portal-layout title="Parent Portal Engagement">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-06"
        title="Parent portal engagement rate"
        :meta="$session->name.' · Month of '.\Carbon\Carbon::parse($monthStart)->format('M Y').'. Unique active parent portal logins ÷ enrolled families. Target '.$target.'%.'"
    />

    <form method="GET" action="{{ route('portal-engagement.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="pe_session" value="Session" />
            <select id="pe_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="pe_month" value="Month" />
            <input id="pe_month" type="month" name="month_start"
                   value="{{ \Carbon\Carbon::parse($monthStart)->format('Y-m') }}"
                   class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>
        <div class="pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
        </div>
    </form>

    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">DI-06 this month</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">{{ $summary['active_logins'] }} active of {{ $summary['enrolled_families'] }} enrolled families · target {{ $target }}%</p>
            </div>
            <a href="{{ route('portal-engagement.create', ['month_start' => $monthStart]) }}"
               class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Record monthly logins
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <x-portal.panel title="Enrolled families ({{ $rows->count() }})" subtitle="A family counts when the linked parent/guardian has at least one portal login in the month.">
        <div class="overflow-x-auto -mx-5 sm:-mx-6">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Parent/guardian</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Learners</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Logins</th>
                        <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $guardian = $row['guardian'];
                            $engagement = $row['engagement'];
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
                            <td class="px-5 py-3 text-slate-600">{{ $engagement?->login_count ?? 0 }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $engagement?->is_active_monthly,
                                    'bg-slate-100 text-slate-600' => ! $engagement?->is_active_monthly,
                                ])>{{ $engagement?->is_active_monthly ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 sm:px-6 py-8 text-center text-slate-500">
                                No enrolled families found.
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
