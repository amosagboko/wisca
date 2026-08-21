@php
    $isEdit = $plan->exists;
@endphp

<x-portal-layout :title="$isEdit ? 'Update Intervention Plan' : 'New Intervention Plan'">
    <x-portal.page-intro
        eyebrow="Appendix F · AE-07"
        :title="$isEdit ? 'Update plan' : 'Learner academic intervention plan'"
        :meta="$record->learner->name.' · '.$record->schoolClass->name.'. Set status to Active for the plan to count in AE-07.'"
    />

    <x-portal.panel :title="$isEdit ? 'Edit plan' : 'Plan details'">
        <form method="POST" action="{{ $isEdit ? route('intervention-plans.update', $plan) : route('at-risk.plans.store', $record) }}" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="plan_type" value="Plan type" />
                    <select id="plan_type" name="plan_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (\App\Support\AtRiskCriteria::planTypes() as $value => $label)
                            <option value="{{ $value }}" @selected(old('plan_type', $plan->plan_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Tier 2 is targeted class support. Tier 3 is intensive specialist support.</p>
                    <x-input-error :messages="$errors->get('plan_type')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (\App\Support\AtRiskCriteria::planStatuses() as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $plan->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="objectives" value="Objectives" />
                <textarea id="objectives" name="objectives" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('objectives', $plan->objectives) }}</textarea>
                <x-input-error :messages="$errors->get('objectives')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="strategies" value="Strategies / actions" />
                <textarea id="strategies" name="strategies" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('strategies', $plan->strategies) }}</textarea>
                <x-input-error :messages="$errors->get('strategies')" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_date" value="Start date" />
                    <x-text-input id="start_date" name="start_date" type="date" class="block mt-1 w-full" :value="old('start_date', optional($plan->start_date)->format('Y-m-d') ?? $plan->start_date)" required />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="review_date" value="Review date" />
                    <x-text-input id="review_date" name="review_date" type="date" class="block mt-1 w-full" :value="old('review_date', optional($plan->review_date)->format('Y-m-d') ?? $plan->review_date)" required />
                    <x-input-error :messages="$errors->get('review_date')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="notes" value="Notes (optional)" />
                <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $plan->notes) }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save plan' : 'Save intervention plan' }}</x-primary-button>
                <a href="{{ route('at-risk.show', $record) }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
