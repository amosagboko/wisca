<x-portal-layout title="Rate Character">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-02 · Appendix I"
        title="Character development marksheet"
        :meta="$session->name.($term ? ' · '.$term->name : '').'. Rate each learner on all '.($domains->count()).' active domains.'"
    />

    {{-- Class selector --}}
    <x-portal.panel title="Class" class="mb-6">
        <form method="GET" action="{{ route('character.create') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <x-input-label for="class_sel" value="Class" />
                <select id="class_sel" name="class" onchange="this.form.submit()"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($classes as $cls)
                        <option value="{{ $cls->id }}" @selected($cls->id === $selectedClassId)>{{ $cls->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </x-portal.panel>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <x-portal.panel
        :title="'Learner ratings — '.($classes->firstWhere('id', $selectedClassId)?->name ?? 'Class').' ('.$marksheet->count().' learners)'"
        :subtitle="'Domains: '.implode(', ', $domains->pluck('name')->all()).'. Leave a cell blank to skip that rating.'">

        @if ($marksheet->isEmpty())
            <p class="text-sm text-slate-500">No enrolled learners in this class.</p>
        @else
            <form method="POST" action="{{ route('character.store') }}">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">

                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600 sticky left-0 bg-slate-50">Learner</th>
                                @foreach ($domains as $domain)
                                    <th class="px-3 py-3 font-semibold text-slate-600 text-center min-w-[120px]">
                                        {{ $domain->name }}
                                        <span class="block text-[10px] font-normal text-slate-400">≥{{ $domain->labelForLevel($domain->passing_level) }}</span>
                                    </th>
                                @endforeach
                                <th class="px-5 py-3 font-semibold text-slate-600 text-center">CE-02?</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($marksheet as $i => $row)
                                @php $learner = $row['learner']; @endphp
                                <tr class="border-t border-slate-100 {{ $row['all_passed'] ? 'bg-emerald-50/40' : '' }}">
                                    <td class="px-5 sm:px-6 py-2 font-medium text-slate-800 sticky left-0 bg-white">
                                        {{ $learner->name }}
                                        <input type="hidden" name="rows[{{ $i }}][learner_id]" value="{{ $learner->id }}">
                                    </td>
                                    @foreach ($domains as $domain)
                                        @php
                                            $existing = $row['ratings']->get($domain->id);
                                            $current  = old("rows.$i.domains.$domain->id", $existing?->level);
                                        @endphp
                                        <td class="px-2 py-2 text-center">
                                            <select name="rows[{{ $i }}][domains][{{ $domain->id }}]"
                                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs w-full @if($existing?->isPassing()) bg-emerald-50 @endif">
                                                <option value="">—</option>
                                                @foreach ($domain->effectiveRubric() as $rubricRow)
                                                    <option value="{{ $rubricRow['level'] }}"
                                                            @selected((int) $current === (int) $rubricRow['level'])>
                                                        {{ $rubricRow['level'] }} – {{ $rubricRow['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endforeach
                                    <td class="px-5 py-2 text-center text-xs font-semibold">
                                        @if ($row['all_passed'])
                                            <span class="text-emerald-700">✓</span>
                                        @elseif ($row['all_rated'])
                                            <span class="text-amber-700">✗</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-4 pt-4">
                    <a href="{{ route('character.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
                    <x-primary-button>Save ratings</x-primary-button>
                </div>
            </form>
        @endif
    </x-portal.panel>
</x-portal-layout>
