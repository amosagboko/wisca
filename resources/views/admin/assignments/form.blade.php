@php
    $isEdit = $assignment->exists;
    $selectedSubjectIds = old('subject_ids', $assignment->exists ? array_filter([(int) $assignment->subject_id]) : []);
    $selectedSectionId = old('school_section_id', $assignment->schoolClass?->school_section_id);
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Assignment' : 'Add Assignment'">
    <x-portal.page-intro
        eyebrow="School Structure"
        :title="$isEdit ? 'Edit teacher assignment' : 'New teacher assignment'"
        meta="Teachers must have the teacher role before they appear in this list. The HOD is taken from the class school section."
    />

    <x-portal.panel title="Assignment details">
        <form method="POST" action="{{ $isEdit ? route('admin.assignments.update', $assignment) : route('admin.assignments.store') }}" class="space-y-5 max-w-2xl"
              x-data="{
                  isEdit: @js($isEdit),
                  sectionId: @js((string) ($selectedSectionId ?? '')),
                  classId: @js((string) old('school_class_id', $assignment->school_class_id ?? '')),
                  classesBySection: @js($classesBySection ?? []),
                  offeredByClass: @js($offeredByClass ?? []),
                  hodsBySection: @js($hodsBySection ?? []),
                  classInSection(id) {
                      if (!this.sectionId) {
                          return this.isEdit && !this.classId ? true : String(id) === String(this.classId);
                      }
                      const ids = this.classesBySection[String(this.sectionId)] || [];
                      return ids.map(String).includes(String(id));
                  },
                  subjectOffered(id) {
                      if (!this.classId) {
                          return false;
                      }
                      const offered = this.offeredByClass[String(this.classId)] || [];
                      if (!offered.length) {
                          return true;
                      }
                      return offered.map(String).includes(String(id));
                  },
                  hodName() {
                      if (!this.sectionId) {
                          return 'Unassigned';
                      }
                      return this.hodsBySection[String(this.sectionId)] || 'No HOD assigned';
                  }
              }"
              x-init="$watch('sectionId', () => {
                  if (classId && !classInSection(classId)) {
                      classId = '';
                  }
              })">
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
                <x-input-label for="school_section_id" value="School section" />
                <select id="school_section_id" name="school_section_id" x-model="sectionId" @unless($isEdit) required @endunless class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ $isEdit ? 'Unassigned / all classes' : 'Select section...' }}</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected((string) $selectedSectionId === (string) $section->id)>{{ $section->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Choose the section first. Only classes in that section appear below.</p>
                <x-input-error :messages="$errors->get('school_section_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Head of Department" />
                <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" x-text="hodName()"></p>
                <p class="mt-1 text-xs text-slate-500">Read-only. Change the HOD on School Sections.</p>
            </div>

            <div>
                <x-input-label for="school_class_id" value="Class" />
                <select id="school_class_id" name="school_class_id" required x-model="classId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select class...</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" x-show="classInSection({{ $class->id }})" :disabled="!classInSection({{ $class->id }})" @selected(old('school_class_id', $assignment->school_class_id) == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('school_class_id')" class="mt-2" />
            </div>

            @if ($isEdit)
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
            @else
                <div>
                    <x-input-label value="Subjects" />
                    <div class="mt-2 space-y-2 rounded-md border border-slate-200 px-3 py-3">
                        @foreach ($subjects as $subject)
                            <label class="flex items-center gap-2 text-sm text-slate-700" x-show="subjectOffered({{ $subject->id }})">
                                <input
                                    type="checkbox"
                                    name="subject_ids[]"
                                    value="{{ $subject->id }}"
                                    @checked(in_array($subject->id, array_map('intval', (array) $selectedSubjectIds), true))
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                >
                                <span>{{ $subject->name }}</span>
                            </label>
                        @endforeach
                        <p class="text-xs text-slate-500" x-show="!classId">Select a class to see the subjects it offers.</p>
                    </div>
                    <x-input-error :messages="$errors->get('subject_ids')" class="mt-2" />
                    <p class="mt-1 text-xs text-slate-500">One assignment row is created for each selected subject. A teacher may teach several subjects in the same class.</p>
                </div>
            @endif

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
