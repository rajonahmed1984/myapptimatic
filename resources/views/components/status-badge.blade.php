@props(['status', 'label' => null])

@php
    use App\Support\StatusColorHelper;
    $safeStatus = $status ?? 'inactive';
    $colors = StatusColorHelper::getStatusColors($safeStatus);
    $displayLabel = $label ?? ucfirst(str_replace('_', ' ', (string) $safeStatus));
@endphp

<div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $colors['bg'] }} {{ $colors['text'] }}">
    {{ $displayLabel }}
</div>
