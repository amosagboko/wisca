<article id="review-plan-{{ $plan->id }}" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap justify-between gap-4">
        <div class="min-w-0 max-w-xl">
            <div class="flex items-center gap-3">
                <x-user-avatar :user="$plan->teacher" size="md" />
                <div>
                    <p class="font-medium text-slate-800">{{ $plan->teacher->name }}</p>
                    <p class="text-xs text-slate-500">{{ $plan->schoolClass->name }} · {{ $plan->subject->name }}</p>
                </div>
            </div>
            <p class="mt-3 font-display text-lg font-semibold text-[#0f2d4a]">{{ $plan->topic->title }}</p>
            <p class="text-sm text-slate-500">Week {{ $plan->topic->week_number }}</p>
            <p class="mt-1 text-sm {{ $plan->on_time ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $plan->on_time ? 'Submitted on time' : 'Submitted after the '.($plan->due_at?->format('l') ?? 'planning-policy').' deadline' }}
            </p>
            @if ($plan->reviewDeadlineAt())
                <p class="mt-1 text-sm {{ $plan->isReviewSlaOverdue() ? 'text-amber-700' : 'text-slate-500' }}">
                    @if ($plan->isReviewSlaOverdue())
                        24-hour review SLA overdue · submitted {{ $plan->submitted_at->format('d M Y H:i') }}
                    @else
                        Decide by {{ $plan->reviewDeadlineAt()->format('d M Y H:i') }} (24 hours from submission)
                    @endif
                </p>
            @endif
            <p class="mt-2 text-sm text-slate-700"><span class="font-medium">Objectives:</span> {{ $plan->objectives }}</p>
            @if ($plan->fileUrl())
                <a href="{{ $plan->fileUrl() }}" class="mt-2 inline-block text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline" target="_blank">View attachment</a>
            @endif
        </div>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:min-w-[16rem]">
            <form method="POST" action="{{ route('lesson-plans.approve', $plan) }}" class="space-y-3">
                @csrf
                @include('lesson-plans.partials.review-form')
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Approve plan
                </button>
            </form>
            <form method="POST" action="{{ route('lesson-plans.reject', $plan) }}" class="flex flex-col gap-2">
                @csrf
                @include('lesson-plans.partials.review-form')
                <input type="text" name="rejection_reason" placeholder="Revision notes" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                    Return for revision
                </button>
            </form>
        </div>
    </div>
</article>
