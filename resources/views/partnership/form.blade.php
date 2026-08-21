<x-portal-layout title="Record Partnership Commitments">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-07"
        title="Partnership commitment marksheet"
        :meta="$session->name.' · '.$term->name.' · '.$charter->title.'. Mark each parent as signed, pending, or declined.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($rows->isEmpty())
        <x-portal.panel title="No parents registered">
            <p class="text-sm text-slate-600">Add parents to the registry before recording partnership commitments.</p>
            @if (auth()->user()?->isAdmin())
                <a href="{{ route('admin.guardians.create') }}"
                   class="mt-4 inline-flex items-center rounded-lg bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#163d63]">
                    Add parent/guardian
                </a>
            @endif
        </x-portal.panel>
    @else
        <x-portal.panel :title="'Parents ('.$rows->count().')'" subtitle="Only signed commitments count toward CE-07.">
            <form method="POST" action="{{ route('partnership.store') }}">
                @csrf

                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Parent/guardian</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Method</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                @php
                                    $guardian = $row['guardian'];
                                    $signature = $row['signature'];
                                @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                        {{ $guardian->name }}
                                        <input type="hidden" name="rows[{{ $i }}][parent_id]" value="{{ $guardian->id }}">
                                    </td>
                                    <td class="px-5 py-3">
                                        <select name="rows[{{ $i }}][status]"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            @foreach (['pending', 'signed', 'declined'] as $status)
                                                <option value="{{ $status }}" @selected(old("rows.$i.status", $signature?->status ?? 'pending') === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-5 py-3">
                                        <select name="rows[{{ $i }}][signature_method]"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">—</option>
                                            @foreach (['in_person', 'paper', 'digital', 'other'] as $method)
                                                <option value="{{ $method }}" @selected(old("rows.$i.signature_method", $signature?->signature_method) === $method)>{{ str_replace('_', ' ', ucfirst($method)) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="text" name="rows[{{ $i }}][notes]" value="{{ old("rows.$i.notes", $signature?->notes) }}"
                                               class="w-full min-w-[10rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                               placeholder="Optional">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-primary-button>Save & recalculate CE-07</x-primary-button>
                    <a href="{{ route('partnership.index') }}"
                       class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        </x-portal.panel>
    @endif
</x-portal-layout>
