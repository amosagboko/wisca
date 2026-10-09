<article id="review-register-{{ $log->id }}" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap justify-between gap-4">
        <div class="min-w-0 max-w-xl">
            <div class="flex items-center gap-3">
                <x-user-avatar :user="$log->recorder" size="md" />
                <div>
                    <p class="font-medium text-slate-800">{{ $log->recorder->name }}</p>
                    <p class="text-xs text-slate-500">{{ $log->schoolClass->name }}</p>
                </div>
            </div>
            <p class="mt-3 font-display text-lg font-semibold text-[#0f2d4a]">
                Register · {{ $log->attendance_date->format('d M Y') }}
            </p>
            <p class="text-sm text-slate-600">
                Present {{ $log->present_count }} / {{ $log->enrolled_count }} enrolled
                · {{ number_format($log->attendanceRate() * 100, 1) }}%
            </p>
            @if ($log->notes)
                <p class="mt-2 text-sm text-slate-700">{{ $log->notes }}</p>
            @endif
            <p class="mt-2 text-xs text-slate-400">AE-04 still uses present vs enrolled, whether or not you verify.</p>
        </div>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:min-w-[16rem]">
            <form method="POST" action="{{ route('attendance.verify', $log) }}">
                @csrf
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Approve register
                </button>
            </form>
            <form method="POST" action="{{ route('attendance.reject', $log) }}" class="flex flex-col gap-2">
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
