@php $isEdit = $audit->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Digital Ethics Audit' : 'Log Digital Ethics Audit'">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-03"
        :title="$isEdit ? 'Update digital ethics audit' : 'Log digital ethics audit'"
        :meta="$session?->name.'. Record AI disclosure and tech integrity findings for DI-03.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel :title="$isEdit ? $audit->assignment_title : 'Audit details'" class="max-w-3xl">
        <form method="POST"
              action="{{ $isEdit ? route('ethics.update', $audit) : route('ethics.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="digital_ethics_audit_type_id" value="Audit type *" />
                    <select id="digital_ethics_audit_type_id" name="digital_ethics_audit_type_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected((int) old('digital_ethics_audit_type_id', $audit->digital_ethics_audit_type_id) === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="term_id" value="Term *" />
                    <select id="term_id" name="term_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($terms as $term)
                            <option value="{{ $term->id }}" @selected((int) old('term_id', $audit->term_id) === $term->id)>{{ $term->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-input-label for="assignment_title" value="Assignment title *" />
                <x-text-input id="assignment_title" name="assignment_title" type="text" class="block mt-1 w-full"
                              :value="old('assignment_title', $audit->assignment_title)" required
                              placeholder="e.g. Term essay — digital citizenship" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="learner_id" value="Learner" />
                    <select id="learner_id" name="learner_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Not specified</option>
                        @foreach ($learners as $learner)
                            <option value="{{ $learner->id }}" @selected((int) old('learner_id', $audit->learner_id) === $learner->id)>
                                {{ $learner->name }}@if ($learner->schoolClass) · {{ $learner->schoolClass->name }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="audited_on" value="Audited on *" />
                    <x-text-input id="audited_on" name="audited_on" type="date" class="block mt-1 w-full"
                                  :value="old('audited_on', optional($audit->audited_on)->toDateString() ?? now()->toDateString())" required />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="flex items-center gap-2 pt-6 text-sm text-slate-700 cursor-pointer">
                        <input type="hidden" name="free_of_violations" value="0">
                        <input type="checkbox" name="free_of_violations" value="1"
                               @checked((bool) old('free_of_violations', $audit->free_of_violations ?? true))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        Free of AI / tech violations
                    </label>
                </div>
                <div>
                    <x-input-label for="violation_category" value="Violation category" />
                    <x-text-input id="violation_category" name="violation_category" type="text" class="block mt-1 w-full"
                                  :value="old('violation_category', $audit->violation_category)"
                                  placeholder="e.g. Undisclosed AI use" />
                </div>
            </div>

            <div>
                <x-input-label for="detector_tool" value="Detector / check tool" />
                <x-text-input id="detector_tool" name="detector_tool" type="text" class="block mt-1 w-full"
                              :value="old('detector_tool', $audit->detector_tool)"
                              placeholder="e.g. Manual review + AI detector" />
            </div>

            <div>
                <x-input-label for="findings" value="Findings" />
                <textarea id="findings" name="findings" rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('findings', $audit->findings) }}</textarea>
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes', $audit->notes) }}</textarea>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Log audit' }}</x-primary-button>
                <a href="{{ route('ethics.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
