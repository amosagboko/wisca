@php
    $f = $filters;
    $isStaff = auth()->user()->isHoD() || auth()->user()->isHoS() || auth()->user()->isAdmin();
    $canLog  = auth()->user()->isTeacher();

    $activeFilters = $f['termId'] || $f['filterClassId'] || $f['filterSubjectId']
        || $f['filterStatus'] !== '' || $f['search'] !== ''
        || ($f['sessionId'] && $f['sessionId'] !== ($allSessions->first()?->id ?? 0));

    $submitted = $logs->where('status', 'submitted')->count();
    $verified  = $logs->where('status', 'verified')->count();
    $rejected  = $logs->where('status', 'rejected')->count();
@endphp

<x-portal-layout title="Coverage Logs">
    <x-portal.page-intro
        eyebrow="Appendix C · AE-01"
        title="Curriculum coverage logs"
        :meta="$session->name.'. Topics submitted for verification against approved schemes of work.'"
    />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('coverage-logs.index') }}"
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

        @if ($allClasses->isNotEmpty())
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
        @endif

        @if ($allSubjects->isNotEmpty())
            <div>
                <x-input-label for="f_subject" value="Subject" />
                <select id="f_subject" name="subject_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['filterSubjectId'] === 0)>All subjects</option>
                    @foreach ($allSubjects as $sub)
                        <option value="{{ $sub->id }}" @selected($sub->id === $f['filterSubjectId'])>{{ $sub->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <x-input-label for="f_status" value="Status" />
            <select id="f_status" name="status"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value=""          @selected($f['filterStatus'] === '')>All statuses</option>
                <option value="submitted" @selected($f['filterStatus'] === 'submitted')>Submitted</option>
                <option value="verified"  @selected($f['filterStatus'] === 'verified')>Verified</option>
                <option value="rejected"  @selected($f['filterStatus'] === 'rejected')>Rejected</option>
            </select>
        </div>

        <div class="flex-1 min-w-[160px]">
            <x-input-label for="f_search" value="Search" />
            <x-text-input id="f_search" name="search" type="text" placeholder="Teacher or topic name…"
                          :value="$f['search']" class="mt-1 block w-full text-sm" />
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('coverage-logs.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Summary strip --}}
    @if ($logs->isNotEmpty())
        <div class="portal-enter mb-6 flex flex-wrap gap-4">
            <div class="flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Showing</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ $logs->count() }}</p>
                <p class="text-xs text-slate-500">log{{ $logs->count() === 1 ? '' : 's' }}</p>
            </div>
            <div class="flex-1 min-w-[120px] rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-amber-500">Pending</p>
                <p class="mt-1 text-2xl font-display font-semibold text-amber-700">{{ $submitted }}</p>
                <p class="text-xs text-amber-600">awaiting verification</p>
            </div>
            <div class="flex-1 min-w-[120px] rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-emerald-500">Verified</p>
                <p class="mt-1 text-2xl font-display font-semibold text-emerald-700">{{ $verified }}</p>
                <p class="text-xs text-emerald-600">count toward AE-01</p>
            </div>
            @if ($rejected > 0)
                <div class="flex-1 min-w-[120px] rounded-xl border border-red-100 bg-red-50 px-4 py-3 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-red-500">Rejected</p>
                    <p class="mt-1 text-2xl font-display font-semibold text-red-700">{{ $rejected }}</p>
                    <p class="text-xs text-red-600">need resubmission</p>
                </div>
            @endif
        </div>
    @endif

    @if ($canLog)
        <div class="mb-4 flex justify-end">
            <a href="{{ route('coverage-logs.create') }}"
               class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Log coverage
            </a>
        </div>
    @endif

    <x-portal.panel :title="'Coverage logs'.($logs->count() ? ' ('.$logs->count().')' : '')">
        @if ($logs->isEmpty())
            <p class="text-sm text-slate-500">
                @if ($activeFilters)
                    No logs match the current filters.
                @else
                    No coverage logs submitted yet this session.
                    @if ($canLog)
                        Use <a href="{{ route('coverage-logs.create') }}" class="font-medium text-[#0f2d4a] hover:underline">Log Coverage</a> to submit a topic once its lesson plan is approved.
                    @endif
                @endif
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Date</th>
                            @if ($isStaff)
                                <th class="px-5 py-3 font-semibold text-slate-600">Teacher</th>
                            @endif
                            <th class="px-5 py-3 font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Topic</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Workbook ref.</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            @if ($isStaff)
                                <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 text-slate-700">
                                    {{ $log->coverage_date->format('d M Y') }}
                                </td>
                                @if ($isStaff)
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$log->teacher" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $log->teacher->name }}</span>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $log->schoolClass->name }} · {{ $log->subject->name }}
                                </td>
                                <td class="px-5 py-3 font-medium text-slate-800">
                                    {{ $log->topic->title }}
                                    <span class="ml-1 text-xs font-normal text-slate-400">Wk {{ $log->topic->week_number }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-600 text-xs">{{ $log->workbook_reference }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-amber-100 text-amber-800'   => $log->status === 'submitted',
                                        'bg-emerald-100 text-emerald-800' => $log->status === 'verified',
                                        'bg-red-100 text-red-800'       => $log->status === 'rejected',
                                    ])>{{ ucfirst($log->status) }}</span>
                                </td>
                                @if ($isStaff)
                                    <td class="px-5 py-3 text-right">
                                        @if ($log->status === 'submitted')
                                            <form method="POST" action="{{ route('coverage-logs.verify', $log) }}" class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="text-xs font-semibold uppercase tracking-wide text-emerald-700 hover:underline">Verify</button>
                                            </form>
                                            <span class="mx-1 text-slate-300">·</span>
                                            <button type="button"
                                                    onclick="document.getElementById('reject-{{ $log->id }}').classList.toggle('hidden')"
                                                    class="text-xs font-semibold uppercase tracking-wide text-red-600 hover:underline">Reject</button>
                                            <div id="reject-{{ $log->id }}" class="hidden mt-2 text-left">
                                                <form method="POST" action="{{ route('coverage-logs.reject', $log) }}" class="space-y-2">
                                                    @csrf
                                                    <textarea name="rejection_reason" rows="2" required placeholder="Reason for rejection…"
                                                              class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                                    <button type="submit"
                                                            class="text-xs font-semibold uppercase tracking-wide text-red-700 hover:underline">Confirm reject</button>
                                                </form>
                                            </div>
                                        @elseif ($log->status === 'verified')
                                            <span class="text-xs text-slate-400">{{ $log->verifier?->name }} · {{ optional($log->verified_at)->format('d M Y') }}</span>
                                        @elseif ($log->status === 'rejected')
                                            <span class="text-xs text-slate-400" title="{{ $log->rejection_reason }}">Rejected</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-portal.panel>

    @if (session('success'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
</x-portal-layout>
