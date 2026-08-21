<x-portal-layout title="Identify At-Risk Learner">
    <x-portal.page-intro
        eyebrow="Appendix F · AE-07"
        title="Identify an at-risk learner"
        meta="Excel counts learners below the pass mark or marked Concern. An identification without an active Tier 2/3 plan pulls AE-07 off track."
    />

    <x-portal.panel title="Identification">
        @if ($learners->isEmpty())
            <p class="text-sm text-slate-500">Every enrolled learner in your scope is already flagged, or there is no class roll yet.</p>
        @else
            <form method="POST" action="{{ route('at-risk.store') }}" class="space-y-5 max-w-2xl">
                @csrf

                <div>
                    <x-input-label for="learner_id" value="Learner" />
                    <select id="learner_id" name="learner_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select learner...</option>
                        @foreach ($learners as $learner)
                            <option value="{{ $learner->id }}" @selected((int) old('learner_id', $preselected) === (int) $learner->id)>
                                {{ $learner->name }} — {{ $learner->schoolClass->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('learner_id')" class="mt-2" />
                </div>

                <div>
                    <p class="text-sm font-medium text-slate-700">Risk factors</p>
                    <p class="mt-1 text-xs text-slate-500">At least one is required. Concern needs a short note.</p>
                    <div class="mt-3 space-y-2">
                        @foreach ($factors as $value => $label)
                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="risk_factors[]" value="{{ $value }}" class="mt-0.5 rounded border-gray-300 text-[#0f2d4a] shadow-sm focus:ring-[#0f2d4a]" @checked(in_array($value, old('risk_factors', [])))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('risk_factors')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="concern_note" value="Concern note" />
                    <textarea id="concern_note" name="concern_note" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Required when Concern is selected">{{ old('concern_note') }}</textarea>
                    <x-input-error :messages="$errors->get('concern_note')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="risk_level" value="Risk level" />
                    <select id="risk_level" name="risk_level" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($levels as $value => $label)
                            <option value="{{ $value }}" @selected(old('risk_level', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('risk_level')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <x-primary-button>Save identification</x-primary-button>
                    <a href="{{ route('at-risk.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
