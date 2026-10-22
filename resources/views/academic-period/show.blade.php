<x-portal-layout title="Academic Period">
    <x-portal.page-intro
        eyebrow="Configuration"
        title="Academic session and term"
        meta="Operate the current school year: see the active term, add the next term, and transition when it is ready. A new session is created only for a new academic year."
    >
        <x-slot:actions>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.sessions.create') }}" class="inline-flex items-center rounded-lg border border-white/30 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-white/10">New academic year</a>
            @endif
            @if (auth()->user()->isAdmin() || auth()->user()->isHoS())
                <a href="{{ route('planning-policy.edit') }}" class="inline-flex items-center rounded-lg border border-white/30 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-white/10">Planning policy</a>
            @endif
        </x-slot:actions>
    </x-portal.page-intro>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-portal.panel title="Current academic period">
                @if ($currentSession && $currentTerm)
                    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Current session</dt>
                            <dd class="mt-1 text-lg font-semibold text-[#0f2d4a]">{{ $currentSession->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Current term</dt>
                            <dd class="mt-1 text-lg font-semibold text-[#0f2d4a]">{{ $currentTerm->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Term dates</dt>
                            <dd class="mt-1 text-slate-700">{{ $currentTerm->start_date->format('d M Y') }} — {{ $currentTerm->end_date->format('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Next term</dt>
                            <dd class="mt-1 text-slate-700">
                                @if ($nextTerm)
                                    {{ $nextTerm->name }} · {{ $nextTerm->start_date->format('d M Y') }} — {{ $nextTerm->end_date->format('d M Y') }}
                                @elseif ($nextAvailable)
                                    {{ $nextAvailable['name'] }} is not configured yet.
                                @else
                                    All three terms are configured.
                                @endif
                            </dd>
                        </div>
                    </dl>
                @elseif ($canStartYear)
                    <p class="text-sm text-slate-600">There is no current academic period. Create a session and First Term from <strong>New academic year</strong>.</p>
                @else
                    <p class="text-sm text-slate-600">There is no current academic period. Ask System Admin to create and activate the school year.</p>
                @endif
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
            @elseif ($currentSession && $nextAvailable)
                <x-portal.panel title="Add {{ $nextAvailable['name'] }}">
                    <p class="text-sm text-slate-600">
                        {{ $nextAvailable['name'] }} is the next term in {{ $currentSession->name }}.
                        Enter its dates, then you can transition when {{ $currentTerm?->name ?? 'the current term' }} ends.
                    </p>
                    <form method="POST" action="{{ route('academic-period.terms.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="academic_session_id" value="{{ $currentSession->id }}">
                        <div class="sm:col-span-2">
                            <x-input-label for="sequence" value="Term" />
                            <select id="sequence" name="sequence" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="{{ $nextAvailable['sequence'] }}" selected>{{ $nextAvailable['name'] }}</option>
                            </select>
                            <x-input-error :messages="$errors->get('sequence')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="start_date" value="Start date" />
                            <x-text-input id="start_date" name="start_date" type="date" class="block mt-1 w-full" :value="old('start_date')" required />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="end_date" value="End date" />
                            <x-text-input id="end_date" name="end_date" type="date" class="block mt-1 w-full" :value="old('end_date')" required />
                            <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-primary-button>Save {{ $nextAvailable['name'] }}</x-primary-button>
                        </div>
                    </form>
                </x-portal.panel>
            @endif
        </div>

        <div class="space-y-6">
            <x-portal.panel id="sessions" title="Sessions">
                @if (! $currentSession && $closedSessions->isEmpty())
                    <p class="text-sm text-slate-500">No sessions yet.</p>
                @else
                    <ul class="space-y-3 text-sm">
                        @if ($currentSession)
                            @include('academic-period.partials.session-summary', ['session' => $currentSession])
                        @endif
                        @foreach ($closedSessions as $session)
                            @include('academic-period.partials.session-summary', ['session' => $session])
                        @endforeach
                    </ul>
                    @if ($closedSessions->hasPages())
                        <div class="mt-3 border-t border-slate-100 pt-3">
                            {{ $closedSessions->links('pagination::simple-tailwind') }}
                        </div>
                    @endif
                @endif
            </x-portal.panel>

            <x-portal.panel id="transitions" title="Recent transitions">
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
                    @if ($transitions->hasPages())
                        <div class="mt-3 border-t border-slate-100 pt-3">
                            {{ $transitions->links('pagination::simple-tailwind') }}
                        </div>
                    @endif
                @endif
            </x-portal.panel>
        </div>
    </div>
</x-portal-layout>
