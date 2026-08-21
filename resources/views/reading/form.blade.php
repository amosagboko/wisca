<x-portal-layout title="Record Reading Assessments">
    <x-portal.page-intro
        eyebrow="Literacy Assessment Battery · AE-08"
        title="Record reading assessments"
        meta="Enter baseline (start of session) and follow-up (end of session / term) grade-level scores for each learner. Growth ≥ 1.0 grade level counts toward AE-08."
    />

    <x-portal.panel title="Class selection">
        <form method="GET" action="{{ route('reading.create') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <x-input-label for="class" value="Class" />
                <select id="class" name="class" onchange="this.form.submit()" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($classes as $cls)
                        <option value="{{ $cls->id }}" @selected($cls->id === $selectedClassId)>{{ $cls->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </x-portal.panel>

    <x-portal.panel title="Learner assessments" subtitle="Leave blank if a checkpoint has not yet been completed.">
        @if ($enrolled->isEmpty())
            <p class="text-sm text-slate-500">No enrolled learners in this class.</p>
        @else
            <form method="POST" action="{{ route('reading.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">

                <x-input-error :messages="$errors->get('rows')" class="mb-2" />

                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Baseline level</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Baseline date</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Follow-up level</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Follow-up date</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Growth</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($enrolled as $i => $learner)
                                @php
                                    $existing  = $assessments->get($learner->id);
                                    $oldRow    = old("rows.$i", []);
                                    $baseline  = $oldRow['baseline_level'] ?? $existing?->baseline_level;
                                    $followup  = $oldRow['followup_level'] ?? $existing?->followup_level;
                                    $bDate     = $oldRow['baseline_date'] ?? $existing?->baseline_date?->format('Y-m-d');
                                    $fDate     = $oldRow['followup_date'] ?? $existing?->followup_date?->format('Y-m-d');
                                    $notes     = $oldRow['notes'] ?? $existing?->notes;
                                    $growth    = ($baseline !== null && $followup !== null) ? round($followup - $baseline, 2) : null;
                                @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-2 font-medium text-slate-800">
                                        {{ $learner->name }}
                                        <input type="hidden" name="rows[{{ $i }}][learner_id]" value="{{ $learner->id }}">
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-text-input
                                            name="rows[{{ $i }}][baseline_level]"
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            max="20"
                                            placeholder="e.g. 3.5"
                                            :value="$baseline"
                                            class="w-24 text-sm"
                                        />
                                        <x-input-error :messages="$errors->get('rows.'.$i.'.baseline_level')" class="mt-1" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-text-input
                                            name="rows[{{ $i }}][baseline_date]"
                                            type="date"
                                            :value="$bDate"
                                            class="w-36 text-sm"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-text-input
                                            name="rows[{{ $i }}][followup_level]"
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            max="20"
                                            placeholder="e.g. 4.8"
                                            :value="$followup"
                                            class="w-24 text-sm"
                                        />
                                        <x-input-error :messages="$errors->get('rows.'.$i.'.followup_level')" class="mt-1" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-text-input
                                            name="rows[{{ $i }}][followup_date]"
                                            type="date"
                                            :value="$fDate"
                                            class="w-36 text-sm"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-sm font-medium">
                                        @if ($growth !== null)
                                            <span class="{{ $growth >= 1.0 ? 'text-emerald-700' : 'text-amber-700' }}">
                                                {{ $growth >= 0 ? '+' : '' }}{{ $growth }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-text-input
                                            name="rows[{{ $i }}][notes]"
                                            type="text"
                                            placeholder="Optional"
                                            :value="$notes"
                                            class="w-44 text-sm"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-4 pt-2">
                    <a href="{{ route('reading.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
                    <x-primary-button>Save assessments</x-primary-button>
                </div>
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
