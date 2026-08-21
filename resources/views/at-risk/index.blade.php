@php
    $percent = $summary['identified'] > 0 ? round($summary['rate'] * 100, 1) : null;
    $f = $filters;
    $activeFilters = $f['termId'] || $f['filterClassId'] || $f['filterPlan'] !== ''
        || $f['filterLevel'] !== '' || $f['filterStatus'] !== 'active' || $f['search'] !== ''
        || ($f['sessionId'] && $f['sessionId'] !== ($allSessions->first()?->id ?? 0));
@endphp

<x-portal-layout title="At-Risk Learners">
    <x-portal.page-intro
        eyebrow="Appendix F · AE-07"
        title="At-risk learners with an active plan"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Identified learners with an active Tier 2/3 plan ÷ total identified (below pass mark or Concern). Target 100%.'"
    />

    {{-- AE-07 hero --}}
    <section class="portal-enter mb-6 overflow-hidden rounded-2xl border border-[#0f2d4a]/15 bg-gradient-to-br from-[#0f2d4a] via-[#163d63] to-[#1a4a72] p-6 text-white shadow-[0_8px_32px_rgba(15,45,74,0.18)] sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">AE-07 this month</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $percent === null ? '—' : $percent.'%' }}</p>
                <p class="mt-1 text-sm text-white/75">
                    @if ($summary['identified'] === 0)
                        No learners currently identified as at-risk.
                    @else
                        {{ $summary['with_plan'] }} with an active plan of {{ $summary['identified'] }} identified
                    @endif
                </p>
            </div>
            @if ($canIdentify)
                <a href="{{ route('at-risk.create') }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                    Identify learner
                </a>
            @endif
        </div>
        @if ($percent !== null)
            <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/15">
                <div class="h-full rounded-full {{ $summary['rate'] >= 1 ? 'bg-emerald-400' : 'bg-amber-400' }}" style="width: {{ min(100, $percent) }}%"></div>
            </div>
        @endif
    </section>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('at-risk.index') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">

        <div>
            <x-input-label for="f_session" value="Session" />
            <select id="f_session" name="session_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="f_term" value="Term" />
            <select id="f_term" name="term_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="f_class" value="Class" />
            <select id="f_class" name="class_id"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['filterClassId'] === 0)>All classes</option>
                @foreach ($allClasses as $cls)
                    <option value="{{ $cls->id }}" @selected($cls->id === $f['filterClassId'])>{{ $cls->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="f_status" value="Status" />
            <select id="f_status" name="status"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="active"   @selected($f['filterStatus'] === 'active')>Active</option>
                <option value="resolved" @selected($f['filterStatus'] === 'resolved')>Resolved</option>
                <option value="all"      @selected($f['filterStatus'] === 'all')>All</option>
            </select>
        </div>

        <div>
            <x-input-label for="f_plan" value="Plan" />
            <select id="f_plan" name="plan"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value=""             @selected($f['filterPlan'] === '')>Any</option>
                <option value="with_plan"    @selected($f['filterPlan'] === 'with_plan')>With active plan</option>
                <option value="without_plan" @selected($f['filterPlan'] === 'without_plan')>No active plan</option>
            </select>
        </div>

        <div>
            <x-input-label for="f_level" value="Risk level" />
            <select id="f_level" name="level"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value=""       @selected($f['filterLevel'] === '')>All levels</option>
                <option value="low"    @selected($f['filterLevel'] === 'low')>Low</option>
                <option value="medium" @selected($f['filterLevel'] === 'medium')>Medium</option>
                <option value="high"   @selected($f['filterLevel'] === 'high')>High</option>
            </select>
        </div>

        <div class="flex-1 min-w-[160px]">
            <x-input-label for="f_search" value="Search" />
            <x-text-input id="f_search" name="search" type="text" placeholder="Name or admission no."
                          :value="$f['search']" class="mt-1 block w-full text-sm" />
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('at-risk.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Below-pass-mark unflagged queue --}}
    @if ($unflagged->isNotEmpty() && $canIdentify)
        <x-portal.panel class="mb-6" tone="danger" title="Below pass mark — not yet flagged" subtitle="These learners scored below the pass mark this term but are not yet in the at-risk register.">
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-red-50/80 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Lowest score</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Subjects</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($unflagged as $row)
                            <tr class="border-t border-red-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $row['learner']->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['learner']->schoolClass->name }}</td>
                                <td class="px-5 py-3 font-medium text-amber-800">
                                    {{ number_format($row['lowest'], 1) }}%
                                    <span class="text-xs font-normal text-slate-400">pass {{ (int) $row['pass_mark'] }}%</span>
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $row['sittings']->map(fn ($s) => $s['subject'].' '.$s['score'].'%')->join(', ') }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('at-risk.from-exam') }}">
                                        @csrf
                                        <input type="hidden" name="learner_id" value="{{ $row['learner']->id }}">
                                        <button type="submit" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Flag as at-risk</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-portal.panel>
    @endif

    {{-- Active records --}}
    @if ($f['filterStatus'] !== 'resolved')
        <x-portal.panel
            :title="'Currently identified'.($activeRecords->count() ? ' ('.$activeRecords->count().')' : '')"
            subtitle="Only an active Tier 2 or Tier 3 plan counts in the numerator. Draft, completed, and discontinued plans do not.">
            @if ($activeRecords->isEmpty())
                <p class="text-sm text-slate-500">No records match the current filters.{{ $canIdentify ? ' Flag a below-pass-mark learner above or record a Concern.' : '' }}</p>
            @else
                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Risk level</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Reason</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Plan</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activeRecords as $record)
                                @php $plan = $record->activePlan(); @endphp
                                <tr class="border-t border-slate-100 {{ $plan ? '' : 'bg-amber-50/70' }}">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $record->learner->name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $record->schoolClass->name }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold
                                            {{ $record->risk_level === 'high' ? 'bg-red-100 text-red-800' : ($record->risk_level === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800') }}">
                                            {{ ucfirst($record->risk_level ?? '—') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ implode(' · ', $record->factorLabels()) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($plan)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ $plan->typeLabel() }} active</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">No active plan</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('at-risk.show', $record) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">View</a>
                                        @if ($canManagePlans && ! $plan)
                                            <a href="{{ route('at-risk.plans.create', $record) }}" class="ml-3 text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Add plan</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-portal.panel>
    @endif

    {{-- Resolved records --}}
    @if ($resolvedRecords->isNotEmpty())
        <x-portal.panel class="mt-6"
            :title="'Resolved'.($f['filterStatus'] === 'resolved' ? ' ('.$resolvedRecords->count().')' : ' (recent)')"
            subtitle="Resolved learners drop out of the AE-07 denominator.">
            @if ($f['filterStatus'] === 'resolved')
                {{-- Full table when status filter is 'resolved' or 'all' --}}
                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Identified</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Resolved</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resolvedRecords as $record)
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $record->learner->name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $record->schoolClass->name }}</td>
                                    <td class="px-5 py-3 text-slate-500 text-xs">{{ optional($record->identification_date)->format('d M Y') }}</td>
                                    <td class="px-5 py-3 text-slate-500 text-xs">{{ optional($record->resolved_at)->format('d M Y') }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('at-risk.show', $record) }}" class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                {{-- Compact list when just showing recent resolved alongside active --}}
                <ul class="space-y-2 text-sm text-slate-600">
                    @foreach ($resolvedRecords->take(12) as $record)
                        <li>
                            <a href="{{ route('at-risk.show', $record) }}" class="font-medium text-[#0f2d4a] hover:underline">{{ $record->learner->name }}</a>
                            · {{ $record->schoolClass->name }}
                            · {{ optional($record->resolved_at)->format('d M Y') }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-portal.panel>
    @endif

    @if (session('success'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
</x-portal-layout>
