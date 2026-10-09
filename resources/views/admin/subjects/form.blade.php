@php
    $isEdit = $subject->exists;
    $selectedClassId = old('school_class_id', $selectedClassId ?? null);
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Subject' : 'Add Subject'">
    <x-portal.page-intro
        eyebrow="School Structure"
        :title="$isEdit ? 'Edit subject' : 'New subject'"
        meta="Choose the class first. A subject is offered in that class for schemes of work and teacher assignments."
    />

    @if ($classes->isEmpty())
        <x-portal.panel title="Create a class first">
            <p class="text-sm text-slate-600">Subjects are tied to a class. Add at least one class, then return here to add a subject.</p>
            <a href="{{ route('admin.classes.create') }}" class="mt-4 inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">Add class</a>
        </x-portal.panel>
    @else
        <form method="POST" action="{{ $isEdit ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}" class="space-y-6 max-w-2xl"
              x-data="{ classId: @js($selectedClassId ? (string) $selectedClassId : '') }">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <x-portal.panel title="1. Select class">
                <div>
                    <x-input-label for="school_class_id" value="Class" />
                    <select id="school_class_id" name="school_class_id" required x-model="classId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select class...</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected((int) $selectedClassId === (int) $class->id)>{{ $class->name }} ({{ $class->level }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('school_class_id')" class="mt-2" />
                    <p class="mt-1 text-xs text-slate-500">Select a class first. Subject details appear after you choose one.</p>
                </div>
                @if ($isEdit && $subject->classes->isNotEmpty())
                    <p class="mt-4 text-xs text-slate-500">Currently offered in: {{ $subject->classes->pluck('name')->join(', ') }}. Selecting a class here adds that class if it is not already listed.</p>
                @endif
            </x-portal.panel>

            <p x-cloak x-show="!classId" class="text-sm text-slate-600">Select a class above to enter the subject name, code, and status.</p>

            <div x-cloak x-show="classId || {{ $isEdit ? 'true' : 'false' }}">
                <x-portal.panel title="2. Subject details">
                    <div class="space-y-5">
                        <div>
                            <x-input-label for="name" value="Subject name" />
                            <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $subject->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="code" value="Subject code (optional)" />
                            <x-text-input id="code" name="code" type="text" class="block mt-1 w-full" :value="old('code', $subject->code)" placeholder="e.g. MTH" />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="department_id" value="Department" />
                            <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">No department</option>
                                @foreach ($departments ?? [] as $department)
                                    <option value="{{ $department->id }}" @selected((int) old('department_id', $subject->department_id) === (int) $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
                            <p class="mt-1 text-xs text-slate-500">HODs only review lesson plans and evidence for subjects in their department.</p>
                        </div>

                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (['active', 'inactive'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $subject->status) === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create subject' }}</x-primary-button>
                            <a href="{{ route('admin.subjects.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                        </div>
                    </div>
                </x-portal.panel>
            </div>
        </form>
    @endif
</x-portal-layout>
