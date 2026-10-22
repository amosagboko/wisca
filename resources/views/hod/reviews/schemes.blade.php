@php $f = $filters; @endphp

<x-portal-layout title="Review schemes">
    <x-portal.page-intro
        eyebrow="Action Queue"
        title="Schemes of Work for review"
        meta="Submitted drafts awaiting your approve or return decision. {{ $session->name }}."
    >
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-[#0f2d4a] transition hover:bg-slate-100">
                Back to overview
            </a>
        </x-slot:actions>
    </x-portal.page-intro>

    <form method="GET" action="{{ route('hod.reviews.schemes') }}"
          class="portal-enter mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <x-input-label for="rq_session" value="Session" />
            <select id="rq_session" name="session_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach ($allSessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="rq_term" value="Term" />
            <select id="rq_term" name="term_id" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="0" @selected($f['termId'] === 0)>All terms</option>
                @foreach ($allTerms as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $f['termId'])>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="pt-5">
            <x-primary-button type="submit">Apply</x-primary-button>
        </div>
    </form>

    <x-portal.panel title="Submitted schemes" :subtitle="$items->total().' awaiting review · 15 per page'">
        @if ($items->isEmpty())
            <p class="text-sm font-medium text-emerald-800">All caught up ✓</p>
            <p class="mt-1 text-sm text-slate-500">No submitted schemes are waiting for review.</p>
        @else
            <div class="space-y-3">
                @foreach ($items as $item)
                    @include('dashboard.partials.hod-queue-item', ['item' => $item, 'cta' => 'Review scheme'])
                @endforeach
            </div>
            @if ($items->hasPages())
                <div class="mt-4">{{ $items->onEachSide(1)->links() }}</div>
            @endif
        @endif
    </x-portal.panel>
</x-portal-layout>
