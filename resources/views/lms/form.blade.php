<x-portal-layout title="Record LMS Usage">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-01"
        title="Weekly LMS usage marksheet"
        :meta="$session->name.' · Week of '.\Carbon\Carbon::parse($weekStartDate)->format('d M Y').'. Capture login and activity counts for staff and learners.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('lms.store') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="week_start_date" value="{{ $weekStartDate }}">

        <x-portal.panel :title="'Staff rows ('.$staff->count().')'" subtitle="A user is active when login count + activity count is greater than zero.">
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Staff</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Logins</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Activities</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $i => $member)
                            @php $row = $existing->get('staff:'.$member->id); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                    {{ $member->name }}
                                    <input type="hidden" name="staff_rows[{{ $i }}][user_id]" value="{{ $member->id }}">
                                </td>
                                <td class="px-5 py-3">
                                    <input type="number" min="0" name="staff_rows[{{ $i }}][login_count]"
                                           value="{{ old("staff_rows.$i.login_count", $row?->login_count ?? 0) }}"
                                           class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </td>
                                <td class="px-5 py-3">
                                    <input type="number" min="0" name="staff_rows[{{ $i }}][activity_count]"
                                           value="{{ old("staff_rows.$i.activity_count", $row?->activity_count ?? 0) }}"
                                           class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </td>
                                <td class="px-5 py-3">
                                    <input type="text" name="staff_rows[{{ $i }}][notes]"
                                           value="{{ old("staff_rows.$i.notes", $row?->notes) }}"
                                           class="w-full min-w-[10rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                           placeholder="Optional">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-portal.panel>

        <x-portal.panel :title="'Learner rows ('.$learners->count().')'">
            <div class="overflow-x-auto -mx-5 sm:-mx-6">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Class</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Logins</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Activities</th>
                            <th class="px-5 py-3 font-semibold text-slate-600">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($learners as $i => $learner)
                            @php $row = $existing->get('learner:'.$learner->id); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                    {{ $learner->name }}
                                    <input type="hidden" name="learner_rows[{{ $i }}][learner_id]" value="{{ $learner->id }}">
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $learner->schoolClass?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <input type="number" min="0" name="learner_rows[{{ $i }}][login_count]"
                                           value="{{ old("learner_rows.$i.login_count", $row?->login_count ?? 0) }}"
                                           class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </td>
                                <td class="px-5 py-3">
                                    <input type="number" min="0" name="learner_rows[{{ $i }}][activity_count]"
                                           value="{{ old("learner_rows.$i.activity_count", $row?->activity_count ?? 0) }}"
                                           class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </td>
                                <td class="px-5 py-3">
                                    <input type="text" name="learner_rows[{{ $i }}][notes]"
                                           value="{{ old("learner_rows.$i.notes", $row?->notes) }}"
                                           class="w-full min-w-[10rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                           placeholder="Optional">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-portal.panel>

        <div class="flex flex-wrap gap-3">
            <x-primary-button>Save & recalculate DI-01</x-primary-button>
            <a href="{{ route('lms.index', ['week_start' => $weekStartDate]) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</x-portal-layout>
