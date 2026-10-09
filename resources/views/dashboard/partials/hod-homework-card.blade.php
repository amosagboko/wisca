<article id="review-homework-{{ $log->id }}" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap justify-between gap-4">
        <div class="min-w-0 max-w-xl">
            <div class="flex items-center gap-3">
                <x-user-avatar :user="$log->teacher" size="md" />
                <div>
                    <p class="font-medium text-slate-800">{{ $log->teacher->name }}</p>
                    <p class="text-xs text-slate-500">{{ $log->schoolClass->name }} · {{ $log->subject->name }}</p>
                </div>
            </div>
            <p class="mt-3 font-display text-lg font-semibold text-[#0f2d4a]">{{ $log->title }}</p>
            <p class="text-sm text-slate-600">
                Given {{ $log->given_count }} · On time {{ $log->completed_on_time_count }}
                · {{ number_format($log->completionRate() * 100, 1) }}%
            </p>
            <p class="text-sm text-slate-500">
                Given {{ $log->given_date->format('d M Y') }} · Due {{ $log->due_date->format('d M Y') }}
            </p>
            @if ($log->notes)
                <p class="mt-2 text-sm text-slate-700">{{ $log->notes }}</p>
            @endif
            <p class="mt-2 text-xs text-slate-400">AE-03 still uses given vs on-time counts, whether or not you verify.</p>
        </div>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:min-w-[16rem]">
            <form method="POST" action="{{ route('homework.verify', $log) }}">
                @csrf
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Approve homework
                </button>
            </form>
            <form method="POST" action="{{ route('homework.reject', $log) }}" class="flex flex-col gap-2">
                @csrf
                <input type="text" name="rejection_reason" placeholder="Revision notes" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                    Return for revision
                </button>
            </form>
        </div>
    </div>
</article>
