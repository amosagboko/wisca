@props(['status'])

@php
    $evaluator = app(\App\Services\KpiStatusEvaluator::class);
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', $evaluator->statusBadgeClass($status)]) }}>
    <span @class(['h-1.5 w-1.5 shrink-0 rounded-full', $evaluator->statusDotClass($status)]) aria-hidden="true"></span>
    {{ $status }}
</span>
