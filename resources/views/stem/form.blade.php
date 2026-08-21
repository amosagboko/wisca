<x-portal-layout title="Record STEM Completions">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-02"
        title="STEM project marksheet"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Mark each learner as completed, in progress, or not started.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel title="Assessment setup" class="mb-6">
        <form method="GET" action="{{ route('stem.create') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <x-input-label for="class_sel" value="Class" />
                <select id="class_sel" name="class" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($class->id === $selectedClassId)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="type_sel" value="Project type" />
                <select id="type_sel" name="type" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected($type->id === $selectedTypeId)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-primary-button type="submit">Load</x-primary-button>
        </form>
    </x-portal.panel>

    <x-portal.panel :title="'Learners ('.$learners->count().')'" subtitle="Only Completed counts toward DI-02.">
        @if ($learners->isEmpty())
            <p class="text-sm text-slate-500">No enrolled learners found in this class.</p>
        @else
            <form method="POST" action="{{ route('stem.store') }}">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">
                <input type="hidden" name="stem_project_type_id" value="{{ $selectedTypeId }}">

                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Score</th>
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
                                        <select name="rows[{{ $i }}][status]"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            @foreach (['not_started', 'in_progress', 'completed'] as $status)
                                                <option value="{{ $status }}" @selected(old("rows.$i.status", $row?->status ?? 'not_started') === $status)>
                                                    {{ str_replace('_', ' ', ucfirst($status)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="number" min="0" max="100" name="rows[{{ $i }}][score]"
                                               value="{{ old("rows.$i.score", $row?->score) }}"
                                               class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                               placeholder="0–100">
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="text" name="rows[{{ $i }}][notes]" value="{{ old("rows.$i.notes", $row?->notes) }}"
                                               class="w-full min-w-[10rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                               placeholder="Optional">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-primary-button>Save & recalculate DI-02</x-primary-button>
                    <a href="{{ route('stem.index') }}"
                       class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
