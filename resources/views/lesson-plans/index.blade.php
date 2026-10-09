@php
    $f = $filters;
    $canSubmit = auth()->user()->isTeacher();

    $activeFilters = $f['termId'] || $f['filterClassId'] || $f['filterSubjectId']
        || $f['filterTeacherId'] || $f['filterStatus'] !== '' || $f['filterTiming'] !== ''
        || $f['search'] !== '' || $f['sortBy'] !== 'date_desc'
        || ($f['sessionId'] && $f['sessionId'] !== ($allSessions->first()?->id ?? 0));

    $submitted = $planSummary['submitted'] ?? $plans->where('status', 'submitted')->count();
    $approved  = $planSummary['approved'] ?? $plans->where('status', 'approved')->count();
    $rejected  = $planSummary['rejected'] ?? $plans->where('status', 'rejected')->count();
    $onTime    = $planSummary['on_time'] ?? $plans->where('on_time', true)->whereNotNull('submitted_at')->count();
    $late      = $planSummary['late'] ?? $plans->where('on_time', false)->whereNotNull('submitted_at')->count();
@endphp

<x-portal-layout title="Lesson Plans">
    <x-portal.page-intro
        eyebrow="Appendix A · AE-05"
        title="Lesson plans"
        :meta="'Plans must be submitted before '.($dueWeekdayName ?? 'Thursday').' and approved before coverage can be logged · '.$session->name.'.'"
    />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('lesson-plans.index') }}"
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

        @if ($isStaff && $allTeachers->isNotEmpty())
            <div>
                <x-input-label for="f_teacher" value="Teacher" />
                <select id="f_teacher" name="teacher_id"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="0" @selected($f['filterTeacherId'] === 0)>All teachers</option>
                    @foreach ($allTeachers as $t)
                        <option value="{{ $t->id }}" @selected($t->id === $f['filterTeacherId'])>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <x-input-label for="f_status" value="Status" />
            <select id="f_status" name="status"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value=""         @selected($f['filterStatus'] === '')>All statuses</option>
                <option value="submitted"@selected($f['filterStatus'] === 'submitted')>Submitted</option>
                <option value="approved" @selected($f['filterStatus'] === 'approved')>Approved</option>
                <option value="rejected" @selected($f['filterStatus'] === 'rejected')>Rejected</option>
                <option value="draft"    @selected($f['filterStatus'] === 'draft')>Draft</option>
            </select>
        </div>

        <div>
            <x-input-label for="f_timing" value="Timing" />
            <select id="f_timing" name="timing"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value=""        @selected($f['filterTiming'] === '')>Any timing</option>
                <option value="on_time" @selected($f['filterTiming'] === 'on_time')>On time</option>
                <option value="late"    @selected($f['filterTiming'] === 'late')>Late</option>
            </select>
        </div>

        <div class="flex-1 min-w-[160px]">
            <x-input-label for="f_search" value="Search topic" />
            <x-text-input id="f_search" name="search" type="text" placeholder="Topic title…"
                          :value="$f['search']" class="mt-1 block w-full text-sm" />
        </div>

        <div>
            <x-input-label for="f_sort" value="Sort" />
            <select id="f_sort" name="sort"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="date_desc" @selected($f['sortBy'] === 'date_desc')>Submitted ↓ newest</option>
                <option value="date_asc"  @selected($f['sortBy'] === 'date_asc')>Submitted ↑ oldest</option>
                <option value="topic_asc" @selected($f['sortBy'] === 'topic_asc')>Class / Subject</option>
            </select>
        </div>

        <div class="flex gap-2 pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
            @if ($activeFilters)
                <a href="{{ route('lesson-plans.index') }}"
                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Summary strip --}}
    @if ($plans->isNotEmpty())
        <div class="portal-enter mb-6 flex flex-wrap gap-4">
            <div class="flex-1 min-w-[100px] rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-amber-500">Pending</p>
                <p class="mt-1 text-2xl font-display font-semibold text-amber-700">{{ $submitted }}</p>
                <p class="text-xs text-amber-600">awaiting approval</p>
            </div>
            <div class="flex-1 min-w-[100px] rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-emerald-500">Approved</p>
                <p class="mt-1 text-2xl font-display font-semibold text-emerald-700">{{ $approved }}</p>
                <p class="text-xs text-emerald-600">ready to log</p>
            </div>
            @if ($rejected > 0)
                <div class="flex-1 min-w-[100px] rounded-xl border border-red-100 bg-red-50 px-4 py-3 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-red-500">Rejected</p>
                    <p class="mt-1 text-2xl font-display font-semibold text-red-700">{{ $rejected }}</p>
                    <p class="text-xs text-red-600">need revision</p>
                </div>
            @endif
            <div class="flex-1 min-w-[100px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">On time</p>
                <p class="mt-1 text-2xl font-display font-semibold text-[#0f2d4a]">{{ $onTime }}</p>
                <p class="text-xs text-slate-500">{{ $late > 0 ? $late.' late' : 'none late' }}</p>
            </div>
        </div>
    @endif

    @if ($canSubmit)
        <div class="mb-4 flex justify-end">
            <a href="{{ route('lesson-plans.create') }}"
               class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Submit plan
            </a>
        </div>
    @endif

    <x-portal.panel :title="($isStaff ? 'Department lesson plans' : 'My lesson plans').((method_exists($plans, 'total') ? $plans->total() : $plans->count()) ? ' ('.(method_exists($plans, 'total') ? $plans->total() : $plans->count()).')' : '')">
        @if ($plans->isEmpty())
            <p class="text-sm text-slate-500">
                @if ($activeFilters)
                    No lesson plans match the current filters.
                @else
                    No lesson plans yet.
                @endif
            </p>
        @else
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            @if ($isStaff)
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Teacher</th>
                            @endif
                            <th class="px-5 {{ $isStaff ? 'py-3' : 'sm:px-6 py-3' }} font-semibold text-slate-600">Topic</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class / Subject</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Submitted</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Timing</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr class="border-t border-slate-100">
                                @if ($isStaff)
                                    <td class="px-5 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <x-user-avatar :user="$plan->teacher" size="xs" />
                                            <span class="font-medium text-slate-800">{{ $plan->teacher->name }}</span>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-5 {{ $isStaff ? 'py-3' : 'sm:px-6 py-3' }}">
                                    <p class="font-medium text-slate-800">{{ $plan->topic->title }}</p>
                                    <p class="text-xs text-slate-500">Week {{ $plan->topic->week_number }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $plan->schoolClass->name }} · {{ $plan->subject->name }}</td>
                                <td class="px-5 py-3 text-slate-500 text-xs">
                                    {{ $plan->submitted_at ? $plan->submitted_at->format('d M Y') : '—' }}
                                </td>
                                <td class="px-5 py-3 text-xs font-medium">
                                    @if (! $plan->submitted_at)
                                        <span class="text-slate-400">Not submitted</span>
                                    @elseif ($plan->on_time)
                                        <span class="text-emerald-700">On time</span>
                                    @else
                                        <span class="text-amber-700">Late</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $plan->status === 'approved',
                                        'bg-amber-100 text-amber-800'     => $plan->status === 'submitted',
                                        'bg-red-100 text-red-800'         => $plan->status === 'rejected',
                                        'bg-slate-100 text-slate-700'     => $plan->status === 'draft',
                                    ])>{{ ucfirst($plan->status) }}</span>
                                    @if ($plan->rejection_reason)
                                        <p class="mt-1 max-w-xs text-xs text-red-700">{{ $plan->rejection_reason }}</p>
                                    @endif
                                    @include('lesson-plans.partials.review-result', ['plan' => $plan])
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($canSubmit && $plan->isEditable())
                                        <a href="{{ route('lesson-plans.edit', $plan) }}"
                                           class="text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">Revise</a>
                                    @elseif ($isStaff && $plan->status === 'submitted')
                                        <div class="mx-auto w-64 space-y-3 text-left">
                                            <form method="POST" action="{{ route('lesson-plans.approve', $plan) }}" class="space-y-2">
                                                @csrf
                                                @include('lesson-plans.partials.review-form')
                                                <button type="submit"
                                                        class="text-xs font-semibold uppercase tracking-wide text-emerald-700 hover:underline">Approve plan</button>
                                            </form>
                                            <form method="POST" action="{{ route('lesson-plans.reject', $plan) }}" class="space-y-2">
                                                @csrf
                                                @include('lesson-plans.partials.review-form')
                                                <textarea name="rejection_reason" rows="2" required
                                                          placeholder="Revision notes"
                                                          class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                                <button type="submit"
                                                        class="text-xs font-semibold uppercase tracking-wide text-red-700 hover:underline">Return for revision</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($plans, 'hasPages') && $plans->hasPages())
                <div class="mt-4 px-5 sm:px-6">{{ $plans->links() }}</div>
            @endif
        @endif
    </x-portal.panel>

    @if (session('success'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
</x-portal-layout>
