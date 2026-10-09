@php
    $assignment = $sitting['assignment'];
    $pair = $assignment->school_class_id.':'.$assignment->subject_id;
    $anchor = $assignment->school_class_id.'-'.$assignment->subject_id;
@endphp

<article id="review-exam-{{ $anchor }}" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap justify-between gap-4">
        <div class="min-w-0 max-w-xl">
            <div class="flex items-center gap-3">
                <x-user-avatar :user="$assignment->teacher" size="md" />
                <div>
                    <p class="font-medium text-slate-800">{{ $assignment->teacher->name }}</p>
                    <p class="text-xs text-slate-500">{{ $assignment->schoolClass->name }} · {{ $assignment->subject->name }}</p>
                </div>
            </div>
            <p class="mt-3 font-display text-lg font-semibold text-[#0f2d4a]">Term marksheet</p>
            <p class="text-sm text-slate-600">
                {{ $sitting['passed'] }} passed of {{ $sitting['enrolled'] }} enrolled
                · {{ $sitting['recorded'] }} scores
                · {{ number_format($sitting['rate'] * 100, 1) }}%
            </p>
            <p class="mt-2 text-xs text-slate-400">AE-02 still uses scores vs the enrolled roll, whether or not you verify.</p>
            <a href="{{ route('exam-results.edit', ['assignment' => $pair]) }}"
               class="mt-2 inline-block text-xs font-semibold uppercase tracking-wide text-[#0f2d4a] hover:underline">
                Open marksheet
            </a>
        </div>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:min-w-[16rem]">
            <form method="POST" action="{{ route('exam-results.verify') }}">
                @csrf
                <input type="hidden" name="assignment" value="{{ $pair }}">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Approve marksheet
                </button>
            </form>
            <form method="POST" action="{{ route('exam-results.reject') }}" class="flex flex-col gap-2">
                @csrf
                <input type="hidden" name="assignment" value="{{ $pair }}">
                <input type="text" name="rejection_reason" placeholder="Revision notes" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                    Return for revision
                </button>
            </form>
        </div>
    </div>
</article>
