<x-portal-layout title="Record Digital Competency">
    <x-portal.page-intro
        eyebrow="Digital Innovation · DI-04"
        title="Staff competency marksheet"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Rate each staff member 1–4 across matrix areas. Level 3+ on all areas counts toward DI-04.'"
    />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel :title="'Staff ('.$marksheet->count().')'">
        @if ($marksheet->isEmpty())
            <p class="text-sm text-slate-500">No active staff found.</p>
        @else
            <form method="POST" action="{{ route('competency.store') }}">
                @csrf
                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Staff</th>
                                @foreach ($areas as $area)
                                    <th class="px-5 py-3 font-semibold text-slate-600">{{ $area->name }}</th>
                                @endforeach
                                <th class="px-5 py-3 font-semibold text-slate-600">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($marksheet as $i => $row)
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-3 font-medium text-slate-800">
                                        {{ $row['staff']->name }}
                                        <input type="hidden" name="rows[{{ $i }}][user_id]" value="{{ $row['staff']->id }}">
                                    </td>
                                    @foreach ($areas as $aIndex => $area)
                                        @php $rating = $row['ratings']->get($area->id); @endphp
                                        <td class="px-5 py-3">
                                            <input type="hidden" name="rows[{{ $i }}][ratings][{{ $aIndex }}][area_id]" value="{{ $area->id }}">
                                            <select name="rows[{{ $i }}][ratings][{{ $aIndex }}][level]"
                                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="">—</option>
                                                @foreach ([1,2,3,4] as $level)
                                                    <option value="{{ $level }}" @selected((int) old("rows.$i.ratings.$aIndex.level", $rating?->level) === $level)>
                                                        L{{ $level }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endforeach
                                    <td class="px-5 py-3">
                                        <input type="text" name="rows[{{ $i }}][notes]"
                                               value="{{ old("rows.$i.notes") }}"
                                               class="w-full min-w-[10rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                               placeholder="Optional">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-primary-button>Save & recalculate DI-04</x-primary-button>
                    <a href="{{ route('competency.index') }}"
                       class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
