<x-portal-layout title="Academic Period">
    <x-portal.page-intro
        eyebrow="Configuration"
        title="Academic session and term"
        meta="Head of School and Board move terms and prepare the next year. System Admin activates a session. Historical records stay on the period they were created under."
    >
        <x-slot:actions>
            @if (auth()->user()->isAdmin() || auth()->user()->isHoS())
                <a href="{{ route('planning-policy.edit') }}" class="inline-flex items-center rounded-lg border border-white/30 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-white/10">Planning policy</a>
            @endif
        </x-slot:actions>
    </x-portal.page-intro>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-portal.panel title="Current academic period">
                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Current session</dt>
                        <dd class="mt-1 text-lg font-semibold text-[#0f2d4a]">{{ $currentSession?->name ?? 'None' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Current term</dt>
                        <dd class="mt-1 text-lg font-semibold text-[#0f2d4a]">
                            {{ $currentTerm?->name ?? 'None' }}
                            @if ($currentTerm)
                                <span class="text-sm font-normal text-slate-500"> · sequence {{ $currentTerm->sequence }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-portal.panel>

            @if ($currentSession && $nextTerm)
                <x-portal.panel title="Transition to next term">
                    <p class="text-sm text-slate-600">
                        Move from <strong>{{ $currentSession->name }} — {{ $currentTerm->name }}</strong>
                        to <strong>{{ $currentSession->name }} — {{ $nextTerm->name }}</strong>.
                        The session stays current. Historical records remain on their original term.
                    </p>
                    <form method="POST" action="{{ route('academic-period.transition') }}" class="mt-5 space-y-4" onsubmit="return confirm('You are moving the school from {{ $currentSession->name }} — {{ $currentTerm->name }} to {{ $currentSession->name }} — {{ $nextTerm->name }}. Historical academic records will remain attached to their original academic period. Continue?')">
                        @csrf
                        <div>
                            <x-input-label for="transition_notes" value="Notes (optional)" />
                            <textarea id="transition_notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        </div>
                        <x-primary-button>Transition term</x-primary-button>
                    </form>
                </x-portal.panel>
            @endif

            @if ($canRollover)
                <x-portal.panel title="Activate a prepared session">
                    @if ($targetSessions->isEmpty())
                        <p class="text-sm text-slate-600">Head of School or Board must prepare a future session with at least one term. You then activate it here. No further approval is required.</p>
                    @else
                        <p class="text-sm text-slate-600 mb-4">
                            Close <strong>{{ $currentSession->name }} — {{ $currentTerm->name }}</strong>
                            and open a prepared future session at First Term.
                            Historical records are not migrated.
                        </p>
                        <form method="POST" action="{{ route('academic-period.rollover') }}" class="space-y-4" onsubmit="return confirm('You are moving the school from {{ $currentSession->name }} — {{ $currentTerm->name }} to the selected future session. Historical academic records will remain attached to their original academic period. Continue?')">
                            @csrf
                            <div>
                                <x-input-label for="target_session_id" value="Target session" />
                                <select id="target_session_id" name="target_session_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($targetSessions as $session)
                                        <option value="{{ $session->id }}">{{ $session->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="rollover_notes" value="Notes (optional)" />
                                <textarea id="rollover_notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            </div>
                            <x-primary-button>Activate session</x-primary-button>
                        </form>
                    @endif
                </x-portal.panel>
            @elseif ($currentSession && ! $nextTerm)
                <x-portal.panel title="Next session">
                    <p class="text-sm text-slate-600">
                        This is the last term in <strong>{{ $currentSession->name }}</strong>.
                        Prepare the next session and its terms below.
                        System Admin activates that session — Head of School and Board cannot open it.
                    </p>
                </x-portal.panel>
            @endif

            @if (! $currentSession && ($canActivateAnytime ?? false))
                <x-portal.panel title="Open initial academic period">
                    <p class="text-sm text-slate-600 mb-4">There is no current period. Open a prepared session and term. This does not rewrite history.</p>
                    <form method="POST" action="{{ route('academic-period.open') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="session_id" value="Session" />
                            <select id="session_id" name="session_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="term_id" value="Starting term" />
                            <select id="term_id" name="term_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($sessions as $session)
                                    @foreach ($session->terms as $term)
                                        <option value="{{ $term->id }}">{{ $session->name }} — {{ $term->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>Open academic period</x-primary-button>
                    </form>
                </x-portal.panel>
            @endif

            <x-portal.panel title="Prepare future session">
                <p class="mb-4 text-sm text-slate-600">Create the next school year and add its terms. This does not make it current. System Admin activates it.</p>
                <form method="POST" action="{{ route('academic-period.sessions.store') }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Session name" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" required placeholder="e.g. 2026/2027" />
                    </div>
                    <div>
                        <x-input-label for="start_date" value="Start date" />
                        <x-text-input id="start_date" name="start_date" type="date" class="block mt-1 w-full" required />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="End date" />
                        <x-text-input id="end_date" name="end_date" type="date" class="block mt-1 w-full" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-primary-button>Create future session</x-primary-button>
                    </div>
                </form>
            </x-portal.panel>

            <x-portal.panel title="Configure a term">
                <form method="POST" action="{{ route('academic-period.terms.store') }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="academic_session_id" value="Session" />
                        <select id="academic_session_id" name="academic_session_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($sessions as $session)
                                <option value="{{ $session->id }}">{{ $session->name }} ({{ $session->status }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="term_name" value="Term name" />
                        <x-text-input id="term_name" name="name" type="text" class="block mt-1 w-full" required placeholder="e.g. Second Term" />
                    </div>
                    <div>
                        <x-input-label for="sequence" value="Sequence" />
                        <x-text-input id="sequence" name="sequence" type="number" min="1" class="block mt-1 w-full" placeholder="Leave blank to append" />
                    </div>
                    <div>
                        <x-input-label for="term_start" value="Start date" />
                        <x-text-input id="term_start" name="start_date" type="date" class="block mt-1 w-full" required />
                    </div>
                    <div>
                        <x-input-label for="term_end" value="End date" />
                        <x-text-input id="term_end" name="end_date" type="date" class="block mt-1 w-full" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-primary-button>Add term</x-primary-button>
                    </div>
                </form>
            </x-portal.panel>
        </div>

        <div class="space-y-6">
            <x-portal.panel title="Sessions">
                <ul class="space-y-3 text-sm">
                    @forelse ($sessions as $session)
                        <li class="rounded-lg border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-800">
                                {{ $session->name }}
                                @if ($session->is_current)
                                    <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-emerald-800">Current</span>
                                @endif
                            </p>
                            <p class="text-xs capitalize text-slate-500">{{ $session->status }}</p>
                            <ul class="mt-2 space-y-1 text-xs text-slate-600">
                                @foreach ($session->terms as $term)
                                    <li>
                                        {{ $term->sequence }}. {{ $term->name }}
                                        · {{ $term->status }}
                                        @if ($term->is_current)
                                            · current
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">No sessions yet.</li>
                    @endforelse
                </ul>
            </x-portal.panel>

            <x-portal.panel title="Recent transitions">
                @if ($transitions->isEmpty())
                    <p class="text-sm text-slate-500">No transitions recorded yet.</p>
                @else
                    <ul class="space-y-3 text-xs text-slate-600">
                        @foreach ($transitions as $row)
                            <li>
                                <p class="font-medium text-slate-800">{{ str_replace('_', ' ', $row->action) }}</p>
                                <p>{{ $row->previousSession?->name }} {{ $row->previousTerm?->name }} → {{ $row->newSession?->name }} {{ $row->newTerm?->name }}</p>
                                <p>{{ $row->performer?->name }} · {{ $row->performed_at?->format('d M Y H:i') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-portal.panel>
        </div>
    </div>
</x-portal-layout>
