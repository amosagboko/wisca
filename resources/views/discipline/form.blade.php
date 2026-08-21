@php $isEdit = $incident->exists; @endphp

<x-portal-layout :title="$isEdit ? 'Edit Incident' : 'Log Incident'">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-04"
        :title="$isEdit ? 'Update discipline incident' : 'Log discipline incident'"
        :meta="$session?->name.'. Capture incident details and restorative agreement progress for CE-04.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel :title="$isEdit ? $incident->title : 'Incident details'" class="max-w-3xl">
        <form method="POST"
              action="{{ $isEdit ? route('discipline.update', $incident) : route('discipline.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="discipline_incident_type_id" value="Incident type *" />
                    <select id="discipline_incident_type_id" name="discipline_incident_type_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected((int) old('discipline_incident_type_id', $incident->discipline_incident_type_id) === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="term_id" value="Term *" />
                    <select id="term_id" name="term_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($terms as $term)
                            <option value="{{ $term->id }}" @selected((int) old('term_id', $incident->term_id) === $term->id)>{{ $term->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="learner_id" value="Learner (optional)" />
                    <select id="learner_id" name="learner_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">General / multiple learners</option>
                        @foreach ($learners as $learner)
                            <option value="{{ $learner->id }}" @selected((int) old('learner_id', $incident->learner_id) === $learner->id)>{{ $learner->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="incident_date" value="Incident date *" />
                    <x-text-input id="incident_date" name="incident_date" type="date" class="block mt-1 w-full"
                                  :value="old('incident_date', optional($incident->incident_date)->toDateString() ?? now()->toDateString())" required />
                </div>
            </div>

            <div>
                <x-input-label for="title" value="Title *" />
                <x-text-input id="title" name="title" type="text" class="block mt-1 w-full"
                              :value="old('title', $incident->title)" required placeholder="e.g. Repeated class disruption" />
            </div>

            <div>
                <x-input-label for="description" value="Incident narrative" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $incident->description) }}</textarea>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <x-input-label for="severity" value="Severity *" />
                    <select id="severity" name="severity" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('severity', $incident->severity) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="status" value="Case status *" />
                    <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach (['open' => 'Open', 'in_review' => 'In review', 'resolved' => 'Resolved'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $incident->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="restorative_status" value="Restorative status *" />
                    <select id="restorative_status" name="restorative_status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach (['not_required' => 'Not required', 'pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('restorative_status', $incident->restorative_status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-input-label for="restorative_agreement" value="Restorative agreement" />
                <textarea id="restorative_agreement" name="restorative_agreement" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="Agreement reached with learner/guardian">{{ old('restorative_agreement', $incident->restorative_agreement) }}</textarea>
            </div>

            <div>
                <x-input-label for="restorative_actions" value="Restorative actions" />
                <textarea id="restorative_actions" name="restorative_actions" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="Concrete restorative steps and follow-up">{{ old('restorative_actions', $incident->restorative_actions) }}</textarea>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Log incident' }}</x-primary-button>
                <a href="{{ route('discipline.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
