<x-portal-layout :title="$activity['label']">
    <x-portal.page-intro
        :eyebrow="($activity['pillar'] ?? 'Academic Excellence').' · coming later'"
        :title="$activity['label']"
        meta="This activity is on the WISCA PEMS menu. Use the tabs above for the measurable sub-activities. Capture screens will be added in a later phase."
    />

    <x-portal.panel title="Register not available yet" subtitle="The working register for this activity has not been built. The tabs show the intended work, targets, and evidence from the operational framework.">
        <p class="text-sm text-slate-600">Continue using the other Academic Excellence activities for live data entry.</p>
    </x-portal.panel>
</x-portal-layout>
