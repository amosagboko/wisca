@php
    $isEdit = $log->exists;
    $selectedAssignment = old('assignment', request('assignment', $log->school_class_id && $log->subject_id ? $log->school_class_id.':'.$log->subject_id : ''));
@endphp

<x-portal-layout :title="$isEdit ? 'Update Homework' : 'Log Homework'">
    <x-portal.page-intro
        eyebrow="Appendix A · AE-03"
        :title="$isEdit ? 'Update homework log' : 'Class homework log'"
        meta="Record how many assignments were given and how many were completed on time. Enter 0 completions until you have marked the work, then update this log."
    />

    @if ($isEdit && $log->isRejected() && $log->rejection_reason)
        <div class="portal-enter mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Returned by HOD: {{ $log->rejection_reason }}
        </div>
    @endif

    <x-portal.panel :title="$isEdit ? 'Edit log' : 'Assignment details'">
        @if ($assignments->isEmpty())
            <p class="text-sm text-slate-500">You have no active class/subject assignments this session. Ask Admin to assign you before logging homework.</p>
        @else
            <form method="POST" action="{{ $isEdit ? route('homework.update', $log) : route('homework.store') }}" class="space-y-5 max-w-2xl">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="assignment" value="Class / Subject" />
                    <select id="assignment" name="assignment" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select class and subject...</option>
                        @foreach ($assignments as $assignment)
                            @php $key = $assignment->school_class_id.':'.$assignment->subject_id; @endphp
                            <option value="{{ $key }}" @selected($selectedAssignment === $key)>
                                {{ $assignment->schoolClass->name }} — {{ $assignment->subject->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('assignment')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="title" value="Assignment title" />
                    <x-text-input id="title" name="title" type="text" class="block mt-1 w-full" :value="old('title', $log->title)" required placeholder="e.g. Exercise 4.2 — fractions" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="given_date" value="Date given" />
                        <x-text-input id="given_date" name="given_date" type="date" class="block mt-1 w-full" :value="old('given_date', optional($log->given_date)->format('Y-m-d') ?? $log->given_date)" required />
                        <x-input-error :messages="$errors->get('given_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="due_date" value="Due date" />
                        <x-text-input id="due_date" name="due_date" type="date" class="block mt-1 w-full" :value="old('due_date', optional($log->due_date)->format('Y-m-d') ?? $log->due_date)" required />
                        <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="given_count" value="Assignments given" />
                        <x-text-input id="given_count" name="given_count" type="number" min="1" class="block mt-1 w-full" :value="old('given_count', $log->given_count)" required />
                        <p class="mt-1 text-xs text-slate-500">Learners who received this assignment.</p>
                        <x-input-error :messages="$errors->get('given_count')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="completed_on_time_count" value="Completed on time" />
                        <x-text-input id="completed_on_time_count" name="completed_on_time_count" type="number" min="0" class="block mt-1 w-full" :value="old('completed_on_time_count', $log->completed_on_time_count ?? 0)" required />
                        <p class="mt-1 text-xs text-slate-500">Submitted by the due date. Cannot exceed assignments given.</p>
                        <x-input-error :messages="$errors->get('completed_on_time_count')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="notes" value="Notes (optional)" />
                    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $log->notes) }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <x-primary-button>{{ $isEdit ? 'Save changes' : 'Save homework log' }}</x-primary-button>
                    <a href="{{ route('homework.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                    @if ($isEdit)
                        <button type="submit" form="delete-homework" class="ml-auto text-xs font-semibold uppercase tracking-widest text-red-700 hover:underline">Remove</button>
                    @endif
                </div>
            </form>

            @if ($isEdit)
                <form id="delete-homework" method="POST" action="{{ route('homework.destroy', $log) }}" class="hidden" onsubmit="return confirm('Remove this homework log?');">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endif
    </x-portal.panel>
</x-portal-layout>
