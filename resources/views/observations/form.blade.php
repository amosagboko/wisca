@php
    $isEdit = $observation->exists;
    $selected = old(
        'assignment',
        $observation->teacher_id && $observation->school_class_id && $observation->subject_id
            ? $observation->teacher_id.':'.$observation->school_class_id.':'.$observation->subject_id
            : ''
    );
    $savedScores = old('scores', $observation->rubric_scores ?? []);
@endphp

<x-portal-layout :title="$isEdit ? 'Update Observation' : 'Record Observation'">
    <x-portal.page-intro
        eyebrow="Appendix B · AE-06"
        :title="$isEdit ? 'Update lesson observation' : 'Classroom observation'"
        meta="Score 12 instructional standards from 1 Emerging to 4 Exemplary. A lesson counts as effective when the average is Secure (3) or better."
    />

    <x-portal.panel :title="$isEdit ? 'Observation details' : 'Lesson to observe'">
        @if ($assignments->isEmpty())
            <p class="text-sm text-slate-500">No active teacher assignments this session. Assign a teacher to a class and subject first.</p>
        @else
            <form method="POST" action="{{ $isEdit ? route('observations.update', $observation) : route('observations.store') }}" class="space-y-6">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <x-input-label for="assignment" value="Teacher / class / subject" />
                        <select id="assignment" name="assignment" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select lesson...</option>
                            @foreach ($assignments as $assignment)
                                @php $key = $assignment->teacher_id.':'.$assignment->school_class_id.':'.$assignment->subject_id; @endphp
                                <option value="{{ $key }}" @selected($selected === $key)>
                                    {{ $assignment->teacher->name }} — {{ $assignment->schoolClass->name }} — {{ $assignment->subject->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('assignment')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="observation_date" value="Date" />
                        <x-text-input id="observation_date" name="observation_date" type="date" class="block mt-1 w-full" :value="old('observation_date', optional($observation->observation_date)->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('observation_date')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <h3 class="font-display text-lg font-semibold text-[#0f2d4a]">12 instructional standards</h3>
                    <p class="mt-1 text-xs text-slate-500">1 Emerging · 2 Developing · 3 Secure · 4 Exemplary. All 12 are required to complete.</p>
                    <x-input-error :messages="$errors->get('scores')" class="mt-2" />
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-semibold text-slate-600">Standard</th>
                                    @foreach ($scale as $value => $label)
                                        <th class="px-2 py-2 text-center font-semibold text-slate-600 w-24">{{ $value }}<span class="block text-[10px] font-normal uppercase tracking-wide">{{ $label }}</span></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($standards as $key => $label)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-3 py-3 text-slate-800">
                                            <span class="text-xs font-semibold text-slate-400">{{ strtoupper($key) }}</span>
                                            {{ $label }}
                                            <x-input-error :messages="$errors->get('scores.'.$key)" class="mt-1" />
                                        </td>
                                        @foreach ($scale as $value => $scaleLabel)
                                            <td class="px-2 py-3 text-center">
                                                <input
                                                    type="radio"
                                                    name="scores[{{ $key }}]"
                                                    value="{{ $value }}"
                                                    @checked((string) ($savedScores[$key] ?? '') === (string) $value)
                                                    class="border-slate-300 text-[#0f2d4a] focus:ring-[#0f2d4a]"
                                                >
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-3">
                    <div>
                        <x-input-label for="strengths" value="Strengths" />
                        <textarea id="strengths" name="strengths" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('strengths', $observation->strengths) }}</textarea>
                    </div>
                    <div>
                        <x-input-label for="areas_for_improvement" value="Areas for improvement" />
                        <textarea id="areas_for_improvement" name="areas_for_improvement" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('areas_for_improvement', $observation->areas_for_improvement) }}</textarea>
                    </div>
                    <div>
                        <x-input-label for="action_plan" value="Action plan" />
                        <textarea id="action_plan" name="action_plan" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('action_plan', $observation->action_plan) }}</textarea>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button type="submit" name="intent" value="complete" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                        Complete observation
                    </button>
                    <button type="submit" name="intent" value="schedule" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                        Save as scheduled
                    </button>
                    <a href="{{ route('observations.index') }}" class="text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-[#0f2d4a]">Cancel</a>
                    @if ($isEdit)
                        <button type="submit" form="delete-observation" class="ml-auto text-xs font-semibold uppercase tracking-widest text-red-700 hover:underline">Remove</button>
                    @endif
                </div>
            </form>

            @if ($isEdit)
                <form id="delete-observation" method="POST" action="{{ route('observations.destroy', $observation) }}" class="hidden" onsubmit="return confirm('Remove this observation?');">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endif
    </x-portal.panel>
</x-portal-layout>
