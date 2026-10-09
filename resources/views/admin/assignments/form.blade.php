@php
    $isEdit = $assignment->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Assignment' : 'Add Assignment'">
    <x-portal.page-intro
        eyebrow="School Structure"
        :title="$isEdit ? 'Edit teacher assignment' : 'New teacher assignment'"
        meta="Teachers must have the teacher role before they appear in this list."
    />

    <x-portal.panel title="Assignment details">
        <form method="POST" action="{{ $isEdit ? route('admin.assignments.update', $assignment) : route('admin.assignments.store') }}" class="space-y-5 max-w-2xl"
              x-data="{
                  classId: @js((string) old('school_class_id', $assignment->school_class_id ?? '')),
                  offeredByClass: @js($offeredByClass ?? []),
                  subjectOffered(id) {
                      if (!this.classId) {
                          return false;
                      }
                      const offered = this.offeredByClass[String(this.classId)] || [];
                      if (!offered.length) {
                          return true;
                      }
                      return offered.map(String).includes(String(id));
                  }
              }">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="teacher_id" value="Teacher" />
                <select id="teacher_id" name="teacher_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select teacher...</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id', $assignment->teacher_id) == $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="school_class_id" value="Class" />
                <select id="school_class_id" name="school_class_id" required x-model="classId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select class...</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(old('school_class_id', $assignment->school_class_id) == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('school_class_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="subject_id" value="Subject" />
                <select id="subject_id" name="subject_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select subject...</option>
                    @foreach ($subjects as $subject)
                        <option
                            value="{{ $subject->id }}"
                            x-show="subjectOffered({{ $subject->id }})"
                            :disabled="!subjectOffered({{ $subject->id }})"
                            @selected(old('subject_id', $assignment->subject_id) == $subject->id)
                        >{{ $subject->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                <p class="mt-1 text-xs text-slate-500">Subjects shown are those offered in the selected class.</p>
            </div>

            <div>
                <x-input-label for="academic_session_id" value="Academic session" />
                <select id="academic_session_id" name="academic_session_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select session...</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(old('academic_session_id', $assignment->academic_session_id) == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('academic_session_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $assignment->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create assignment' }}</x-primary-button>
                <a href="{{ route('admin.assignments.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
