@php
    $severity = (string) ($notice['severity'] ?? 'amber');
    $theme = match ($severity) {
        'critical' => [
            'container' => 'border-red-300 bg-red-50 text-red-900',
            'label' => 'text-red-600',
            'body' => 'text-red-800',
            'button' => 'border-red-300 text-red-700 hover:bg-red-100',
            'heading' => 'Payment overdue - critical',
        ],
        'rose' => [
            'container' => 'border-rose-200 bg-rose-50 text-rose-900',
            'label' => 'text-rose-500',
            'body' => 'text-rose-700',
            'button' => 'border-rose-300 text-rose-700 hover:bg-rose-100',
            'heading' => 'Payment overdue',
        ],
        default => [
            'container' => 'border-amber-200 bg-amber-50 text-amber-900',
            'label' => 'text-amber-500',
            'body' => 'text-amber-700',
            'button' => 'border-amber-300 text-amber-700 hover:bg-amber-100',
            'heading' => 'Payment due warning',
        ],
    };
@endphp

<div class="mb-8 rounded-3xl border px-6 py-4 {{ $theme['container'] }}">
    <div class="flex flex-wrap items-center gap-3">
        <div class="text-xs uppercase tracking-[0.35em] {{ $theme['label'] }}">{{ $theme['heading'] }}</div>
        <div class="flex-1 text-sm {{ $theme['body'] }}">
            <span class="font-semibold">{{ $notice['message'] ?? 'Your account has an outstanding invoice.' }}</span>
        </div>
        @if(!empty($notice['payment_url']))
            <a href="{{ $notice['payment_url'] }}" class="inline-flex items-center rounded-full border px-4 py-2 text-xs font-semibold transition {{ $theme['button'] }}">
                Pay now
            </a>
        @endif
    </div>
</div>
