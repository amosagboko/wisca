@php
    $isEdit = $plan->exists;
    $selectedId = old('topic_id', $selectedTopic?->id ?? $plan->topic_id);
@endphp

<x-portal-layout :title="$isEdit ? 'Revise Lesson Plan' : 'Submit Lesson Plan'">
    <x-portal.page-intro
        eyebrow="Appendix A · AE-05"
        :title="$isEdit ? 'Revise lesson plan' : 'Weekly lesson plan'"
        meta="Plans must be submitted before Monday of the instructional week. A topic cannot be marked covered without an approved plan."
    />

    <x-portal.panel :title="$isEdit ? 'Update and resubmit' : 'Plan details'">
        <form method="POST" action="{{ $isEdit ? route('lesson-plans.update', $plan) : route('lesson-plans.store') }}" enctype="multipart/form-data" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="topic_id" value="Topic" />
                <select id="topic_id" name="topic_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @disabled($isEdit)>
                    <option value="">Select topic...</option>
                    @foreach ($schemes as $scheme)
                        <optgroup label="{{ $scheme->schoolClass->name }} — {{ $scheme->subject->name }}">
                            @foreach ($scheme->topics as $topic)
                                @continue($topic->hasApprovedLessonPlan() && $topic->id != $selectedId)
                                <option value="{{ $topic->id }}" @selected($selectedId == $topic->id)>
                                    Week {{ $topic->week_number }}: {{ $topic->title }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @if ($isEdit)
                    <input type="hidden" name="topic_id" value="{{ $plan->topic_id }}">
                @endif
                <x-input-error :messages="$errors->get('topic_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="objectives" value="Learning objectives" />
                <textarea id="objectives" name="objectives" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('objectives', $plan->objectives) }}</textarea>
                <x-input-error :messages="$errors->get('objectives')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="activities" value="Teaching & learning activities" />
                <textarea id="activities" name="activities" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('activities', $plan->activities) }}</textarea>
                <x-input-error :messages="$errors->get('activities')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="assessment" value="Assessment" />
                <textarea id="assessment" name="assessment" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('assessment', $plan->assessment) }}</textarea>
                <x-input-error :messages="$errors->get('assessment')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="resources" value="Resources (optional)" />
                <x-text-input id="resources" name="resources" type="text" class="block mt-1 w-full" :value="old('resources', $plan->resources)" />
            </div>

            <div>
                <x-input-label for="document" value="Upload plan (PDF or Word, optional)" />
                <x-file-input id="document" name="document" accept=".pdf,.doc,.docx" button="Choose file" empty="No file chosen" />
                <x-input-error :messages="$errors->get('document')" class="mt-2" />
                @if ($plan->file_path)
                    <p class="mt-1 text-xs text-slate-500">Current file will be replaced if you upload a new one.</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>Submit for HoD approval</x-primary-button>
                <a href="{{ route('lesson-plans.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
