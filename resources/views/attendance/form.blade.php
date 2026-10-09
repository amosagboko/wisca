@php
    $isEdit = $log->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Update Attendance' : 'Take Register'">
    <x-portal.page-intro
        eyebrow="Appendix A · AE-04"
        :title="$isEdit ? 'Update class register' : 'Morning class register'"
        meta="Record how many learners were on the roll and how many were present. Late arrivals count as present. One log per class per day."
    />

    @if ($isEdit && $log->isRejected() && $log->rejection_reason)
        <div class="portal-enter mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Returned by HOD: {{ $log->rejection_reason }}
        </div>
    @endif

    <x-portal.panel :title="$isEdit ? 'Edit register' : 'Class roll'">
        @if ($classes->isEmpty())
            <p class="text-sm text-slate-500">No classes are available for you to take attendance. Ask Admin to assign a class, or add classes first.</p>
        @else
            <form method="POST" action="{{ $isEdit ? route('attendance.update', $log) : route('attendance.store') }}" class="space-y-5 max-w-2xl">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="school_class_id" value="Class" />
                    <select id="school_class_id" name="school_class_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @disabled($isEdit)>
                        <option value="">Select class...</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected((int) old('school_class_id', $log->school_class_id) === (int) $class->id)>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                    @if ($isEdit)
                        <input type="hidden" name="school_class_id" value="{{ $log->school_class_id }}">
                    @endif
                    <x-input-error :messages="$errors->get('school_class_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="attendance_date" value="Date" />
                    <x-text-input id="attendance_date" name="attendance_date" type="date" class="block mt-1 w-full" :value="old('attendance_date', optional($log->attendance_date)->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('attendance_date')" class="mt-2" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="enrolled_count" value="Enrolled (on roll)" />
                        <x-text-input id="enrolled_count" name="enrolled_count" type="number" min="1" class="block mt-1 w-full" :value="old('enrolled_count', $log->enrolled_count)" required />
                        <p class="mt-1 text-xs text-slate-500">Learners expected in class that day.</p>
                        <x-input-error :messages="$errors->get('enrolled_count')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="present_count" value="Present" />
                        <x-text-input id="present_count" name="present_count" type="number" min="0" class="block mt-1 w-full" :value="old('present_count', $log->present_count ?? 0)" required />
                        <p class="mt-1 text-xs text-slate-500">Include late. Cannot exceed enrolled.</p>
                        <x-input-error :messages="$errors->get('present_count')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="notes" value="Notes (optional)" />
                    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. 2 at clinic, 1 funeral">{{ old('notes', $log->notes) }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <x-primary-button>{{ $isEdit ? 'Save changes' : 'Save register' }}</x-primary-button>
                    <a href="{{ route('attendance.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                    @if ($isEdit)
                        <button type="submit" form="delete-attendance" class="ml-auto text-xs font-semibold uppercase tracking-widest text-red-700 hover:underline">Remove</button>
                    @endif
                </div>
            </form>

            @if ($isEdit)
                <form id="delete-attendance" method="POST" action="{{ route('attendance.destroy', $log) }}" class="hidden" onsubmit="return confirm('Remove this attendance log?');">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endif
    </x-portal.panel>
</x-portal-layout>
