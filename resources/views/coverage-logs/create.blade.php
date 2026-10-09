@php
    $selectedId = old('topic_id', $selectedTopic?->id);
    $selectableTopics = $schemes->flatMap->topics->filter(
        fn ($topic) => $topic->isLoggable() && $topic->hasApprovedLessonPlan()
    );
@endphp

<x-portal-layout title="Log Coverage">
    <x-portal.page-intro
        eyebrow="Appendix C"
        title="Curriculum Coverage Tracker"
        meta="Record what was actually taught against an approved lesson plan. This is delivery, not HOD verification."
    />

    <x-portal.panel title="Topic coverage log">
        @if ($schemes->isEmpty())
            <p class="text-sm text-slate-500">No assigned schemes of work for the current session. Ask Admin to assign you a class and subject.</p>
        @elseif (! empty($needsPlan) && $selectedTopic)
            <p class="text-sm text-slate-600">An approved lesson plan is required before <span class="font-medium text-slate-800">{{ $selectedTopic->title }}</span> can be logged as covered.</p>
            <a href="{{ route('lesson-plans.create', ['topic' => $selectedTopic->id]) }}" class="mt-4 inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Submit lesson plan
            </a>
        @elseif ($selectableTopics->isEmpty())
            <p class="text-sm text-slate-600">No topics are ready to log yet. A topic appears here only after its lesson plan is approved and it is not already submitted.</p>
            <a href="{{ route('lesson-plans.create') }}" class="mt-4 inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                Submit lesson plan
            </a>
        @else
            <form method="POST" action="{{ route('coverage-logs.store') }}" class="space-y-5 max-w-2xl">
                @csrf

                <div>
                    <x-input-label for="topic_id" value="Topic (from approved scheme)" />
                    <x-input-error :messages="$errors->get('term_id')" class="mt-2" />
                    <select id="topic_id" name="topic_id" required class="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select topic...</option>
                        @foreach ($schemes as $scheme)
                            <optgroup label="{{ $scheme->schoolClass->name }} — {{ $scheme->subject->name }}">
                                @foreach ($scheme->topics as $topic)
                                    @php
                                        $ready = $topic->isLoggable() && $topic->hasApprovedLessonPlan();
                                        $reason = ! $topic->isLoggable()
                                            ? 'already logged'
                                            : (! $topic->hasApprovedLessonPlan() ? 'needs approved plan' : null);
                                    @endphp
                                    <option value="{{ $topic->id }}" @selected((int) $selectedId === (int) $topic->id) @disabled(! $ready)>
                                        Week {{ $topic->week_number }}: {{ $topic->title }}{{ $reason ? ' — '.$reason : '' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Greyed-out topics still need an approved lesson plan, or already have coverage submitted.</p>
                    <x-input-error :messages="$errors->get('topic_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="workbook_reference" value="Student Workbook Reference" />
                    <x-text-input id="workbook_reference" name="workbook_reference" type="text" class="block mt-1 w-full" :value="old('workbook_reference')" required placeholder="e.g. JSS 1A Maths Workbook p.24-26" />
                    <x-input-error :messages="$errors->get('workbook_reference')" class="mt-2" />
                    <p class="mt-1 text-xs text-slate-500">Use the learner book page range. Photos of the board alone are not sufficient.</p>
                </div>

                <div>
                    <x-input-label for="coverage_date" value="Coverage Date" />
                    <x-text-input id="coverage_date" name="coverage_date" type="date" class="block mt-1 w-full" :value="old('coverage_date', date('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('coverage_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="notes" value="Notes (optional)" />
                    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <x-primary-button>Record delivery</x-primary-button>
                    <a href="{{ route('coverage-logs.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                        Cancel
                    </a>
                </div>
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
