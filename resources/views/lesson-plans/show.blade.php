<x-portal-layout title="Lesson Plan">
    <x-portal.page-intro
        eyebrow="Appendix A · AE-05"
        :title="$plan->topic?->title ?? 'Lesson plan'"
        :meta="($plan->schoolClass?->name ?? 'Class').' — '.($plan->subject?->name ?? 'Subject').' · Week '.($plan->topic?->week_number ?? '—').' · '.ucfirst($plan->status)"
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                @if ($canRevise)
                    <a href="{{ route('lesson-plans.edit', $plan) }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">Revise</a>
                @endif
                <a href="{{ route('lesson-plans.index') }}" class="inline-flex items-center rounded-lg border border-white/40 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-white/10">All plans</a>
            </div>
        </x-slot:actions>
    </x-portal.page-intro>

    <div class="space-y-6">
        <x-portal.panel title="Plan">
            <dl class="mb-6 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Session</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $plan->topic?->schemeOfWork?->academicSession?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Term</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $plan->topic?->schemeOfWork?->term?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">School section</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $plan->schoolClass?->schoolSection?->name ?? $plan->topic?->schemeOfWork?->schoolClass?->schoolSection?->name ?? 'Unassigned' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Class</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $plan->schoolClass?->name }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Subject</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $plan->subject?->name }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Teacher</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $plan->teacher?->name }}</dd>
                </div>
            </dl>

            <div class="space-y-5 text-sm">
                <div>
                    <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Learning objectives</h4>
                    @php
                        $objectives = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $plan->objectives) ?: [])));
                    @endphp
                    @if ($objectives !== [])
                        <ul class="mt-1 list-disc space-y-1 pl-5 text-slate-800">
                            @foreach ($objectives as $objective)
                                <li>{{ $objective }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-1 text-slate-500">—</p>
                    @endif
                </div>
                <div>
                    <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Teaching &amp; learning activities</h4>
                    <p class="mt-1 whitespace-pre-line text-slate-800">{{ $plan->activities ?: '—' }}</p>
                </div>
                <div>
                    <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Assessment</h4>
                    <p class="mt-1 whitespace-pre-line text-slate-800">{{ $plan->assessment ?: '—' }}</p>
                </div>
                <div>
                    <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Resources</h4>
                    <p class="mt-1 text-slate-800">{{ $plan->resources ?: '—' }}</p>
                </div>
                @if ($plan->file_path)
                    <div>
                        <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Attached file</h4>
                        <a href="{{ $plan->fileUrl() }}" class="mt-1 inline-block font-medium text-[#0f2d4a] hover:underline">Download</a>
                    </div>
                @endif
            </div>
        </x-portal.panel>

        <div class="grid max-w-3xl gap-6 md:grid-cols-2">
            <x-portal.panel title="Submission">
                <dl class="space-y-2.5 text-sm">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Status</dt>
                        <dd class="mt-0.5 capitalize text-slate-800">{{ $plan->status }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Submitted</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $plan->submitted_at?->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Timing</dt>
                        <dd class="mt-0.5">
                            @if (! $plan->submitted_at)
                                <span class="text-slate-500">Not submitted</span>
                            @elseif ($plan->on_time)
                                <span class="font-medium text-emerald-700">On time</span>
                            @else
                                <span class="font-medium text-amber-700">Late</span>
                            @endif
                            @if ($plan->due_at)
                                <span class="block text-slate-500">Due {{ $plan->due_at->format('d M Y') }}</span>
                            @endif
                        </dd>
                    </div>
                    @if ($plan->approved_at)
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">HOD decision</dt>
                            <dd class="mt-0.5 text-slate-800">{{ $plan->approver?->name }} · {{ $plan->approved_at->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                    @if ($plan->rejection_reason)
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Return notes</dt>
                            <dd class="mt-0.5 text-red-800">{{ $plan->rejection_reason }}</dd>
                        </div>
                    @endif
                    @include('lesson-plans.partials.review-result', ['plan' => $plan])
                </dl>
            </x-portal.panel>

            @if ($canReviewPlan)
                <x-portal.panel title="Review actions">
                    <div class="space-y-4">
                        <form method="POST" action="{{ route('lesson-plans.approve', $plan) }}" class="space-y-3">
                            @csrf
                            @include('lesson-plans.partials.review-form')
                            <x-primary-button>Approve plan</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('lesson-plans.reject', $plan) }}" class="space-y-3">
                            @csrf
                            @include('lesson-plans.partials.review-form')
                            <div>
                                <x-input-label for="rejection_reason" value="Return notes" />
                                <textarea id="rejection_reason" name="rejection_reason" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('rejection_reason') }}</textarea>
                                <x-input-error :messages="$errors->get('rejection_reason')" class="mt-2" />
                            </div>
                            <button type="submit" class="inline-flex items-center rounded-lg border border-red-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-red-700 transition hover:bg-red-50">Return for revision</button>
                        </form>
                    </div>
                </x-portal.panel>
            @endif
        </div>
    </div>
</x-portal-layout>
