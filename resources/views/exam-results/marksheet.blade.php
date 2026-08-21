<x-portal-layout title="Termly Marksheet">
    <x-portal.page-intro
        eyebrow="Termly Broad Sheet · AE-02"
        title="Enter term exam scores"
        :meta="($term->name).' · '.$session->name.'. Leave a score blank if the learner has no result — that sitting still counts in the enrolled denominator.'"
    />

    @if ($assignments->isEmpty())
        <x-portal.panel title="No assignments">
            <p class="text-sm text-slate-500">You have no active class/subject assignments this session. Ask Admin to assign a class before entering marks.</p>
        </x-portal.panel>
    @else
        <form method="GET" action="{{ route('exam-results.edit') }}" class="mb-4 flex flex-wrap items-center gap-3">
            <label for="assignment" class="text-xs font-semibold uppercase tracking-widest text-slate-500">Sitting</label>
            <select id="assignment" name="assignment" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ($assignments as $row)
                    @php $key = $row->school_class_id.':'.$row->subject_id; @endphp
                    <option value="{{ $key }}" @selected($selected === $key)>
                        {{ $row->schoolClass->name }} — {{ $row->subject->name }}
                    </option>
                @endforeach
            </select>
        </form>

        @if ($assignment && $sitting)
            <x-portal.panel
                :title="$assignment->schoolClass->name.' · '.$assignment->subject->name"
                :subtitle="$sitting['passed'].' passed of '.$sitting['enrolled'].' enrolled · '.$sitting['recorded'].' scores entered · pass mark '.(int) $sitting['pass_mark'].'%'"
            >
                @if ($sitting['enrolled'] === 0)
                    <p class="text-sm text-slate-500">
                        No enrolled learners in this class.
                        @if (auth()->user()->canManageLearners())
                            <a href="{{ route('learners.create') }}" class="font-semibold text-[#0f2d4a] hover:underline">Add to the class roll</a>
                        @else
                            Ask the Admin Officer to add the class roll first.
                        @endif
                    </p>
                @else
                    <form method="POST" action="{{ route('exam-results.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="assignment" value="{{ $selected }}">

                        <div class="overflow-x-auto -mx-5 sm:-mx-6">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left">
                                    <tr>
                                        <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                                        <th class="px-5 py-3 font-semibold text-slate-600">Admission no.</th>
                                        <th class="px-5 py-3 font-semibold text-slate-600 w-40">Score (0–100)</th>
                                        <th class="px-5 py-3 font-semibold text-slate-600">Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sitting['learners'] as $learner)
                                        @php
                                            $result = $sitting['results']->get($learner->id);
                                            $score = old('scores.'.$learner->id, $result?->score);
                                            $passed = $result && $result->score >= $sitting['pass_mark'];
                                        @endphp
                                        <tr class="border-t border-slate-100">
                                            <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">{{ $learner->name }}</td>
                                            <td class="px-5 py-3 text-slate-600">{{ $learner->admission_no ?: '—' }}</td>
                                            <td class="px-5 py-3">
                                                <input
                                                    type="number"
                                                    name="scores[{{ $learner->id }}]"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value="{{ $score }}"
                                                    class="block w-28 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="—"
                                                >
                                            </td>
                                            <td class="px-5 py-3">
                                                @if ($result)
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $passed ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                        {{ $passed ? 'Passed' : 'Below pass mark' }}
                                                    </span>
                                                @else
                                                    <span class="text-xs text-slate-400">Not recorded</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <x-input-error :messages="$errors->get('scores')" class="mt-2" />
                        <x-input-error :messages="$errors->get('assignment')" class="mt-2" />

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <x-primary-button>Save marksheet</x-primary-button>
                            <a href="{{ route('exam-results.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Back</a>
                        </div>
                    </form>
                @endif
            </x-portal.panel>
        @endif
    @endif
</x-portal-layout>
