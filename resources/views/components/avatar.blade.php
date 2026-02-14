@props([
    'path' => null,
    'name' => '',
    'size' => 'h-9 w-9',
    'textSize' => 'text-xs',
])

@php
    $label = trim((string) $name);
    $parts = preg_split('/\s+/', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }
@endphp

@php
    $basePath = rtrim(request()->getBasePath(), '/');
    $prefix = $basePath === '' ? '' : $basePath;
    $avatarUrl = null;

    if (is_string($path) && trim($path) !== '') {
        $cleanPath = ltrim(trim($path), '/');

        if (preg_match('/^https?:\/\//i', $cleanPath)) {
            $avatarUrl = $cleanPath;
        } elseif (str_starts_with($cleanPath, 'employees/photos/')) {
            $avatarUrl = $prefix . '/storage/' . $cleanPath;
        } elseif (str_starts_with($cleanPath, 'avatars/')) {
            $avatarUrl = $prefix . '/storage/' . $cleanPath;
        } elseif (str_starts_with($cleanPath, 'users/')
            || str_starts_with($cleanPath, 'customers/')
            || str_starts_with($cleanPath, 'sales-reps/')) {
            $avatarUrl = $prefix . '/storage/avatars/' . $cleanPath;
        } elseif (str_starts_with($cleanPath, 'storage/')) {
            $avatarUrl = $prefix . '/' . $cleanPath;
        } else {
            $avatarUrl = $prefix . '/storage/' . $cleanPath;
        }
    }
@endphp

<div {{ $attributes->merge(['class' => $size . ' rounded-full overflow-hidden flex items-center justify-center bg-slate-100 text-slate-600 ' . $textSize . ' font-semibold']) }}>
    @if($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="{{ $label }}" class="h-full w-full object-cover" loading="lazy">
    @else
        <span>{{ $initials !== '' ? $initials : '?' }}</span>
    @endif
</div>
