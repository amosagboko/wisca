@php
    $items = \App\Services\LessonPlanReview::ITEMS;
@endphp

<div class="space-y-2">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quality checklist (AE-05.2)</p>
    @foreach ($items as $key => $label)
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="hidden" name="checklist[{{ $key }}]" value="0">
            <input type="checkbox" name="checklist[{{ $key }}]" value="1" class="mt-0.5 rounded border-slate-300 text-[#0f2d4a] focus:ring-[#0f2d4a]"
                @checked(old('checklist.'.$key, false))>
            <span>{{ $label }}</span>
        </label>
    @endforeach
    <x-input-error :messages="$errors->get('checklist')" class="mt-1" />
    @foreach ($items as $key => $label)
        <x-input-error :messages="$errors->get('checklist.'.$key)" class="mt-1" />
    @endforeach
</div>
