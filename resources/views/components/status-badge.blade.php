@props(['status', 'label' => null])

@php
    use App\Support\StatusColorHelper;
    $colors = StatusColorHelper::getStatusColors($status);
    $displayLabel = $label ?? ucfirst(str_replace('_', ' ', $status));
@endphp

<div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $colors['bg'] }} {{ $colors['text'] }}">
    {{ $displayLabel }}
</div>
