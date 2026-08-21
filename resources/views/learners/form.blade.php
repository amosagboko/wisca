@php
    $isEdit = $learner->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Learner' : 'Add Learner'">
    <x-portal.page-intro
        eyebrow="Class roll · AE-02"
        :title="$isEdit ? 'Edit learner' : 'Add learner'"
        meta="Learners are a lightweight class roll, not staff accounts. Withdrawn learners drop out of the AE-02 denominator."
    />

    <x-portal.panel :title="$isEdit ? $learner->name : 'Learner details'">
        <form method="POST" action="{{ $isEdit ? route('learners.update', $learner) : route('learners.store') }}" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="name" value="Full name" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $learner->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="admission_no" value="Admission number (optional)" />
                <x-text-input id="admission_no" name="admission_no" type="text" class="block mt-1 w-full" :value="old('admission_no', $learner->admission_no)" />
                <x-input-error :messages="$errors->get('admission_no')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="school_class_id" value="Class" />
                <select id="school_class_id" name="school_class_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select class...</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((int) old('school_class_id', $learner->school_class_id) === (int) $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('school_class_id')" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="gender" value="Gender (optional)" />
                    <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Not specified</option>
                        @foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('gender', $learner->gender) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('gender')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (['enrolled' => 'Enrolled', 'withdrawn' => 'Withdrawn'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $learner->status ?? 'enrolled') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Add learner' }}</x-primary-button>
                <a href="{{ route('learners.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                @if ($isEdit)
                    <button type="submit" form="delete-learner" class="ml-auto text-xs font-semibold uppercase tracking-widest text-red-700 hover:underline">Remove</button>
                @endif
            </div>
        </form>

        @if ($isEdit)
            <form id="delete-learner" method="POST" action="{{ route('learners.destroy', $learner) }}" class="hidden" onsubmit="return confirm('Remove this learner from the roll?');">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
