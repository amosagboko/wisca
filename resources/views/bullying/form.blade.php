@php $isEdit = $case->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Bullying Case' : 'Log Bullying Case'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-05"
        :title="$isEdit ? 'Update bullying case' : 'Log bullying case'"
        :meta="$session?->name.'. Capture the report, investigation status, and safety plan for CE-05.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel :title="$isEdit ? $case->title : 'Case details'" class="max-w-3xl">
        <form method="POST"
              action="{{ $isEdit ? route('bullying.update', $case) : route('bullying.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="bullying_case_type_id" value="Case type *" />
                    <select id="bullying_case_type_id" name="bullying_case_type_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected((int) old('bullying_case_type_id', $case->bullying_case_type_id) === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="term_id" value="Term *" />
                    <select id="term_id" name="term_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($terms as $term)
                            <option value="{{ $term->id }}" @selected((int) old('term_id', $case->term_id) === $term->id)>{{ $term->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="target_learner_id" value="Target learner" />
                    <select id="target_learner_id" name="target_learner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Not specified</option>
                        @foreach ($learners as $learner)
                            <option value="{{ $learner->id }}" @selected((int) old('target_learner_id', $case->target_learner_id) === $learner->id)>{{ $learner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="reported_by_learner_id" value="Reporting learner" />
                    <select id="reported_by_learner_id" name="reported_by_learner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Not specified</option>
                        @foreach ($learners as $learner)
                            <option value="{{ $learner->id }}" @selected((int) old('reported_by_learner_id', $case->reported_by_learner_id) === $learner->id)>{{ $learner->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="reported_on" value="Reported on *" />
                    <x-text-input id="reported_on" name="reported_on" type="date" class="block mt-1 w-full"
                                  :value="old('reported_on', optional($case->reported_on)->toDateString() ?? now()->toDateString())" required />
                </div>
                <div>
                    <x-input-label for="severity" value="Severity *" />
                    <select id="severity" name="severity" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('severity', $case->severity) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-input-label for="title" value="Title *" />
                <x-text-input id="title" name="title" type="text" class="block mt-1 w-full"
                              :value="old('title', $case->title)" required placeholder="e.g. Repeated verbal intimidation during break" />
            </div>

            <div>
                <x-input-label for="description" value="Case narrative" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $case->description) }}</textarea>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="status" value="Case status *" />
                    <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach (['reported' => 'Reported', 'investigating' => 'Investigating', 'closed' => 'Closed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $case->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pt-6">
                    <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="safety_plan_created" value="1"
                               @checked((bool) old('safety_plan_created', $case->safety_plan_created))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        Safety plan created
                    </label>
                </div>
            </div>

            <div>
                <x-input-label for="safety_plan" value="Safety plan" />
                <textarea id="safety_plan" name="safety_plan" rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="Document the safety measures, monitoring steps, and follow-up supports">{{ old('safety_plan', $case->safety_plan) }}</textarea>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Log case' }}</x-primary-button>
                <a href="{{ route('bullying.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
