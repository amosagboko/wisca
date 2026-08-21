@php
    $item = [
        'teacherId' => $log->teacher_id,
        'classId' => $log->school_class_id,
        'subjectId' => $log->subject_id,
        'teacher' => $log->teacher->name,
        'className' => $log->schoolClass->name,
        'subject' => $log->subject->name,
        'topic' => $log->topic->title,
    ];
@endphp

<article x-show="match(@js($item))" x-cloak class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap justify-between gap-4">
        <div class="min-w-0 max-w-xl">
            <div class="flex items-center gap-3">
                <x-user-avatar :user="$log->teacher" size="md" />
                <div>
                    <p class="font-medium text-slate-800">{{ $log->teacher->name }}</p>
                    <p class="text-xs text-slate-500">{{ $log->schoolClass->name }} · {{ $log->subject->name }}</p>
                </div>
            </div>
            <p class="mt-3 font-display text-lg font-semibold text-[#0f2d4a]">{{ $log->topic->title }}</p>
            <p class="text-sm text-slate-600">Workbook: <span class="font-medium text-slate-800">{{ $log->workbook_reference }}</span></p>
            <p class="text-sm text-slate-500">Covered: {{ $log->coverage_date->format('d M Y') }}</p>
            @if ($log->notes)
                <p class="mt-2 text-sm text-slate-700">{{ $log->notes }}</p>
            @endif
        </div>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:min-w-[16rem]">
            <form method="POST" action="{{ route('coverage-logs.verify', $log) }}" class="space-y-2">
                @csrf
                <input type="text" name="comment" placeholder="Optional comment"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Approve
                </button>
            </form>
            <form method="POST" action="{{ route('coverage-logs.reject', $log) }}" class="flex flex-col gap-2">
                @csrf
                <input type="text" name="rejection_reason" placeholder="Rejection reason" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                    Reject
                </button>
            </form>
        </div>
    </div>
</article>
