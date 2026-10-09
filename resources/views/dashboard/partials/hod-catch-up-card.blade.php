<article id="catch-up-needed-{{ $topic->id }}" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap justify-between gap-4">
        <div class="min-w-0 max-w-xl">
            <p class="font-display text-lg font-semibold text-[#0f2d4a]">Wk {{ $topic->week_number }} · {{ $topic->title }}</p>
            <p class="text-xs text-slate-500">{{ $topic->schemeOfWork->schoolClass->name }} · {{ $topic->schemeOfWork->subject->name }}</p>
            <p class="mt-2 text-xs text-slate-400">Behind the instructional week without verified coverage. Opening catch-up identifies the gap (AE-01.4). It is addressed only after you verify delivery. This is not an IIP.</p>
        </div>
        <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:min-w-[16rem]">
            <form method="POST" action="{{ route('catch-ups.store') }}">
                @csrf
                <input type="hidden" name="topic_id" value="{{ $topic->id }}">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-[#0f2d4a] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#163d63]">
                    Open catch-up
                </button>
            </form>
        </div>
    </div>
</article>
