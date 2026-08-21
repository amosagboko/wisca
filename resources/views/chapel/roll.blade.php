<x-portal-layout title="Take Roll">
    <x-portal.page-intro
        eyebrow="Christocentric Education · CE-01"
        :title="'Roll — '.$chapelSession->session_date->format('d M Y')"
        :meta="($chapelSession->activityType?->name ?? 'Session').($chapelSession->theme ? ' · '.$chapelSession->theme : '').'. Mark each learner\'s attendance and participation level.'"
    />

    @php
        $countingLevels = $chapelSession->activityType?->countingLevels() ?? ['active', 'leading'];
    @endphp

    <x-portal.panel title="Session details" class="mb-6">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date</dt>
                <dd class="mt-1 text-slate-800">{{ $chapelSession->session_date->format('l, d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Type</dt>
                <dd class="mt-1 text-slate-800">{{ $chapelSession->activityType?->name ?? '—' }}</dd>
            </div>
            @if ($chapelSession->theme)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Theme</dt>
                    <dd class="mt-1 text-slate-800">{{ $chapelSession->theme }}</dd>
                </div>
            @endif
            @if ($chapelSession->scripture_reference)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Scripture</dt>
                    <dd class="mt-1 text-slate-800 italic">{{ $chapelSession->scripture_reference }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Led by</dt>
                <dd class="mt-1 text-slate-800">{{ $chapelSession->leader?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">CE-01 counts</dt>
                <dd class="mt-1 text-slate-800">{{ implode(', ', $countingLevels) }}</dd>
            </div>
        </dl>
    </x-portal.panel>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-portal.panel :title="'Learner roll ('.$learners->count().' enrolled)'" subtitle="Mark each learner present/absent and their participation level. Submitting saves the roll and recalculates CE-01.">
        @if ($learners->isEmpty())
            <p class="text-sm text-slate-500">No enrolled learners found for this school.</p>
        @else
            <form method="POST" action="{{ route('chapel.roll.save', $chapelSession) }}" class="space-y-4">
                @csrf

                {{-- Bulk-set helpers --}}
                <div class="flex flex-wrap gap-3 text-xs">
                    <span class="text-slate-500 font-medium">Set all:</span>
                    <button type="button" onclick="setAllStatus('present')"
                            class="text-emerald-700 font-semibold hover:underline">Present</button>
                    <button type="button" onclick="setAllStatus('absent')"
                            class="text-red-700 font-semibold hover:underline">Absent</button>
                    <button type="button" onclick="setAllParticipation('active')"
                            class="text-[#0f2d4a] font-semibold hover:underline">Active</button>
                    <button type="button" onclick="setAllParticipation('passive')"
                            class="text-slate-600 font-semibold hover:underline">Passive</button>
                </div>

                <div class="overflow-x-auto -mx-5 sm:-mx-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left">
                            <tr>
                                <th class="px-5 sm:px-6 py-3 font-semibold text-slate-600">Learner</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Status</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Participation</th>
                                <th class="px-5 py-3 font-semibold text-slate-600">Notes</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-center">Counts?</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($learners as $i => $learner)
                                @php
                                    $att   = $existing->get($learner->id);
                                    $stat  = old("rows.$i.status",  $att?->status  ?? 'present');
                                    $level = old("rows.$i.participation_level", $att?->participation_level ?? 'active');
                                    $notes = old("rows.$i.notes",   $att?->notes   ?? '');
                                    $counts = $stat !== 'absent' && in_array($level, $countingLevels, true);
                                @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 sm:px-6 py-2 font-medium text-slate-800">
                                        {{ $learner->name }}
                                        <input type="hidden" name="rows[{{ $i }}][learner_id]" value="{{ $learner->id }}">
                                    </td>
                                    <td class="px-3 py-2">
                                        <select name="rows[{{ $i }}][status]"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm row-status">
                                            <option value="present" @selected($stat === 'present')>Present</option>
                                            <option value="late"    @selected($stat === 'late')>Late</option>
                                            <option value="absent"  @selected($stat === 'absent')>Absent</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select name="rows[{{ $i }}][participation_level]"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm row-participation">
                                            <option value="passive" @selected($level === 'passive')>Passive</option>
                                            <option value="active"  @selected($level === 'active')>Active</option>
                                            <option value="leading" @selected($level === 'leading')>Leading</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-text-input name="rows[{{ $i }}][notes]" type="text"
                                                      placeholder="Optional" :value="$notes"
                                                      class="w-40 text-sm" />
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs font-semibold">
                                        @if ($counts)
                                            <span class="text-emerald-700">✓</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-4 pt-2">
                    <a href="{{ route('chapel.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
                    <x-primary-button>Save roll</x-primary-button>
                </div>
            </form>
        @endif
    </x-portal.panel>

    <script>
        function setAllStatus(val) {
            document.querySelectorAll('.row-status').forEach(s => s.value = val);
        }
        function setAllParticipation(val) {
            document.querySelectorAll('.row-participation').forEach(s => s.value = val);
        }
    </script>
</x-portal-layout>
