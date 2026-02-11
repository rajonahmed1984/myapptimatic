@props(['status', 'label' => null, 'full' => false])

@php
    use App\Support\StatusColorHelper;
    $safeStatus = $status ?? 'inactive';
    $colors = StatusColorHelper::getStatusColors($safeStatus);
    $displayLabel = $label ?? ucfirst(str_replace('_', ' ', (string) $safeStatus));

    $solidMap = [
        'emerald' => 'bg-emerald-600 text-white',
        'amber' => 'bg-amber-500 text-white',
        'rose' => 'bg-rose-600 text-white',
        'blue' => 'bg-blue-600 text-white',
        'slate' => 'bg-slate-600 text-white',
    ];

    $badgeClasses = $full
        ? ($solidMap[$colors['color']] ?? 'bg-slate-600 text-white')
        : ($colors['bg'].' '.$colors['text']);
@endphp

<div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClasses }}">
    {{ $displayLabel }}
</div>
