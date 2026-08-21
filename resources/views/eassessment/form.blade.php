<x-portal-layout title="Record E-Assessment Usage">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-05"
        title="Subject digital assessment marksheet"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Mark which subjects use e-assessment and/or e-portfolio tools this term.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel :title="'Subjects ('.$rows->count().')'" subtitle="A subject counts when either checkbox is selected.">
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-500">No active subjects found.</p>
        @else
            <form method="POST" action="{{ route('eassessment.store') }}">
                @csrf
                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Subject</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">E-assessment</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">E-portfolio</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Primary tool</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Evidence notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                @php $record = $row['record']; @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                        {{ $row['subject']->name }}
                                        <input type="hidden" name="rows[{{ $i }}][subject_id]" value="{{ $row['subject']->id }}">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="hidden" name="rows[{{ $i }}][uses_e_assessment]" value="0">
                                        <input type="checkbox" name="rows[{{ $i }}][uses_e_assessment]" value="1"
                                               @checked((bool) old("rows.$i.uses_e_assessment", $record?->uses_e_assessment))
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="hidden" name="rows[{{ $i }}][uses_e_portfolio]" value="0">
                                        <input type="checkbox" name="rows[{{ $i }}][uses_e_portfolio]" value="1"
                                               @checked((bool) old("rows.$i.uses_e_portfolio", $record?->uses_e_portfolio))
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="text" name="rows[{{ $i }}][primary_tool]"
                                               value="{{ old("rows.$i.primary_tool", $record?->primary_tool) }}"
                                               class="w-full min-w-[8rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                               placeholder="e.g. Portal quizzes">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="text" name="rows[{{ $i }}][evidence_notes]"
                                               value="{{ old("rows.$i.evidence_notes", $record?->evidence_notes) }}"
                                               class="w-full min-w-[10rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                               placeholder="Optional">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-primary-button>Save & recalculate DI-05</x-primary-button>
                    <a href="{{ route('eassessment.index') }}"
                       class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
