<x-portal-layout title="Record Parent Portal Logins">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-06"
        title="Parent portal login marksheet"
        :meta="$session->name.' · Month of '.\Carbon\Carbon::parse($monthStart)->format('M Y').'. Enter login counts for each enrolled family.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($rows->isEmpty())
        <x-portal.panel title="No enrolled families">
            <p class="text-sm text-slate-600">Add parents linked to enrolled learners before recording portal engagement.</p>
        </x-portal.panel>
    @else
        <x-portal.panel :title="'Families ('.$rows->count().')'" subtitle="A login count greater than zero marks the family as active for DI-06.">
            <form method="POST" action="{{ route('portal-engagement.store') }}">
                @csrf
                <input type="hidden" name="month_start_date" value="{{ $monthStart }}">

                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Parent/guardian</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Logins this month</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                @php
                                    $guardian = $row['guardian'];
                                    $engagement = $row['engagement'];
                                @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                        {{ $guardian->name }}
                                        <input type="hidden" name="rows[{{ $i }}][parent_id]" value="{{ $guardian->id }}">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="number" min="0" name="rows[{{ $i }}][login_count]"
                                               value="{{ old("rows.$i.login_count", $engagement?->login_count ?? 0) }}"
                                               class="w-28 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="text" name="rows[{{ $i }}][notes]"
                                               value="{{ old("rows.$i.notes", $engagement?->notes) }}"
                                               class="w-full min-w-[10rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                               placeholder="Optional">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-primary-button>Save & recalculate DI-06</x-primary-button>
                    <a href="{{ route('portal-engagement.index', ['month_start' => $monthStart]) }}"
                       class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        </x-portal.panel>
    @endif
</x-portal-layout>
