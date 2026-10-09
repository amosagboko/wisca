@php
    $isEdit = $scheme->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Scheme of Work' : 'New Scheme of Work'">
    <x-portal.page-intro
        eyebrow="Appendix C · AE-01"
        :title="$isEdit ? 'Edit draft scheme' : 'Create draft scheme'"
        meta="Enter weekly topics on this form, or download the CSV template and bulk-upload them. A PDF or Word file is optional supplementary evidence only — it does not create topics."
    />

    <x-portal.panel :title="$isEdit ? 'Draft v'.$scheme->version : 'Draft details'">
        @if ($scheme->rejection_reason)
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Returned to draft</p>
                <p class="mt-1">{{ $scheme->rejection_reason }}</p>
            </div>
        @endif

        <form method="POST" action="{{ $isEdit ? route('schemes.update', $scheme) : route('schemes.store') }}" enctype="multipart/form-data" class="space-y-6"
              x-data="{
                  topics: @js($topicRows),
                  sessionId: @js((string) $selectedSessionId),
                  termId: @js((string) ($selectedTermId ?? '')),
                  classId: @js((string) old('school_class_id', $scheme->school_class_id ?? '')),
                  offeredByClass: @js($offeredByClass ?? []),
                  termsBySession: @js($termsBySession),
                  get termsForSession() {
                      return this.termsBySession[this.sessionId] || [];
                  },
                  syncTerm() {
                      const list = this.termsForSession;
                      if (list.some(term => String(term.id) === String(this.termId))) {
                          return;
                      }
                      const current = list.find(term => term.is_current);
                      this.termId = String(current?.id ?? list[0]?.id ?? '');
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
                  }
              }"
              x-init="syncTerm()">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="academic_session_id" value="Session" />
                    <select id="academic_session_id" name="academic_session_id" required x-model="sessionId" @change="syncTerm()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($sessions as $item)
                            <option value="{{ $item->id }}" @selected((int) $selectedSessionId === (int) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('academic_session_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="term_id" value="Term" />
                    <select id="term_id" name="term_id" required x-model="termId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @forelse ($allTerms as $term)
                            <option
                                value="{{ $term->id }}"
                                x-show="String(sessionId) === @js((string) $term->academic_session_id)"
                                :disabled="String(sessionId) !== @js((string) $term->academic_session_id)"
                                @selected((int) ($selectedTermId ?? 0) === (int) $term->id)
                            >{{ $term->name }}@if ($term->is_current) (current)@endif</option>
                        @empty
                            <option value="">No terms in this session</option>
                        @endforelse
                    </select>
                    <x-input-error :messages="$errors->get('term_id')" class="mt-2" />
                    <p class="mt-1 text-xs text-slate-500">Terms shown are only those that belong to the session above.</p>
                </div>
                <div>
                    <x-input-label for="school_class_id" value="Class" />
                    <select id="school_class_id" name="school_class_id" required x-model="classId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select class...</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected((int) old('school_class_id', $scheme->school_class_id) === $class->id)>{{ $class->name }}</option>
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
                                @selected((int) old('subject_id', $scheme->subject_id) === $subject->id)
                            >{{ $subject->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                    <p class="mt-1 text-xs text-slate-500">Subjects shown are those offered in the selected class.</p>
                </div>
            </div>

            <div>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <x-input-label value="Weekly topics and learning objectives" />
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('schemes.template') }}" class="text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] hover:underline">Download bulk-upload template</a>
                        <button type="button" class="text-xs font-semibold uppercase tracking-widest text-[#0f2d4a]" @click="topics.push({ week_number: topics.length + 1, title: '', learning_objectives: '' })">Add topic</button>
                    </div>
                </div>
                <p class="mb-3 text-xs text-slate-500">Enter topics one by one below, or fill the CSV template and upload it. If you upload the template, those rows replace the topics on this form.</p>
                <x-input-error :messages="$errors->get('topics')" class="mb-2" />
                <x-input-error :messages="$errors->get('bulk_template')" class="mb-2" />

                <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4">
                    <x-input-label for="bulk_template" value="Bulk upload from template (CSV)" />
                    <x-file-input id="bulk_template" name="bulk_template" accept=".csv,.txt" button="Choose CSV" empty="No CSV chosen" />
                    <p class="mt-2 text-xs text-slate-500">Keep the header row. One row per week. Put each learning objective on its own line in the cell, or separate them with | . Session, term, class, and subject stay on this form.</p>
                </div>

                <div class="space-y-4">
                    <template x-for="(topic, index) in topics" :key="index">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <div class="grid gap-4 sm:grid-cols-12">
                                <div class="sm:col-span-2">
                                    <x-input-label value="Week" />
                                    <input type="number" min="1" max="52" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :name="'topics[' + index + '][week_number]'" x-model="topic.week_number">
                                </div>
                                <div class="sm:col-span-10">
                                    <x-input-label value="Title" />
                                    <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :name="'topics[' + index + '][title]'" x-model="topic.title">
                                </div>
                                <div class="sm:col-span-12">
                                    <x-input-label value="Learning objectives (one per line)" />
                                    <textarea rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :name="'topics[' + index + '][learning_objectives]'" x-model="topic.learning_objectives" placeholder="Learners will…"></textarea>
                                </div>
                            </div>
                            <button type="button" class="mt-3 text-xs font-medium text-red-700 hover:underline" @click="if (topics.length > 1) topics.splice(index, 1)" x-show="topics.length > 1">Remove topic</button>
                        </div>
                    </template>
                </div>
            </div>

            <div>
                <x-input-label for="document" value="Supplementary file (PDF or Word, optional)" />
                <x-file-input id="document" name="document" accept=".pdf,.doc,.docx" button="Choose file" empty="No file chosen" />
                <p class="mt-1 text-xs text-slate-500">This file is stored as supporting evidence. It does not bulk-create weekly topics — use the CSV template for that.</p>
                <x-input-error :messages="$errors->get('document')" class="mt-2" />
                @if ($scheme->file_path)
                    <p class="mt-1 text-xs text-slate-500">Current file will be replaced if you upload a new one.</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button name="_action" value="save">Save draft</x-primary-button>
                <button type="submit" name="_action" value="submit" class="inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">Save and submit</button>
                <a href="{{ $isEdit ? route('schemes.show', $scheme) : route('schemes.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
