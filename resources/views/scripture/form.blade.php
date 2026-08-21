<x-portal-layout title="Assess Scripture Mastery">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-06"
        title="Scripture assessment marksheet"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Rate recitation and contextual explanation for each learner.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel title="Assessment setup" class="mb-6">
        <form method="GET" action="{{ route('scripture.create') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <x-input-label for="class_sel" value="Class" />
                <select id="class_sel" name="class" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($class->id === $selectedClassId)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="passage_sel" value="Passage" />
                <select id="passage_sel" name="passage" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($passages as $passage)
                        <option value="{{ $passage->id }}" @selected($passage->id === $selectedPassageId)>{{ $passage->reference }}</option>
                    @endforeach
                </select>
            </div>
            <x-primary-button type="submit">Load</x-primary-button>
        </form>
    </x-portal.panel>

    <x-portal.panel :title="'Learners ('.$learners->count().')'" subtitle="A learner counts toward CE-06 only when both Recites and Explains are Yes.">
        @if ($learners->isEmpty())
            <p class="text-sm text-slate-500">No enrolled learners found in this class.</p>
        @else
            <form method="POST" action="{{ route('scripture.store') }}">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">
                <input type="hidden" name="scripture_passage_id" value="{{ $selectedPassageId }}">

                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Recites correctly</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Explains contextually</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($learners as $i => $learner)
                                @php $row = $existing->get($learner->id); @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                        {{ $learner->name }}
                                        <input type="hidden" name="rows[{{ $i }}][learner_id]" value="{{ $learner->id }}">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="hidden" name="rows[{{ $i }}][recites_correctly]" value="0">
                                        <input type="checkbox" name="rows[{{ $i }}][recites_correctly]" value="1"
                                               @checked((bool) old("rows.$i.recites_correctly", $row?->recites_correctly))
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="hidden" name="rows[{{ $i }}][explains_contextually]" value="0">
                                        <input type="checkbox" name="rows[{{ $i }}][explains_contextually]" value="1"
                                               @checked((bool) old("rows.$i.explains_contextually", $row?->explains_contextually))
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="text" name="rows[{{ $i }}][notes]" value="{{ old("rows.$i.notes", $row?->notes) }}"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                               placeholder="Optional">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end pt-4">
                    <x-primary-button>Save assessments</x-primary-button>
                </div>
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
