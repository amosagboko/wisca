@if (is_array($plan->review_checklist['items'] ?? null))
    <ul class="mt-2 max-w-xs space-y-1 text-left text-xs">
        @foreach ($plan->review_checklist['items'] as $item)
            <li class="{{ ($item['passed'] ?? false) ? 'text-emerald-700' : 'text-red-700' }}">
                {{ ($item['passed'] ?? false) ? 'Pass' : 'Fail' }} — {{ $item['label'] ?? '' }}
            </li>
        @endforeach
    </ul>
@endif
