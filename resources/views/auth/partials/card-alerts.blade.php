@php
    $suppressStatus = $suppressStatus ?? false;
    $suppressErrorKeys = $suppressErrorKeys ?? [];
    $authSingleErrorRoutes = [
        'login',
        'admin.login',
        'employee.login',
        'sales.login',
        'support.login',
        'project-client.login',
    ];
    $filteredErrors = [];

    foreach ($errors->getMessages() as $key => $messages) {
        if (in_array($key, $suppressErrorKeys, true)) {
            continue;
        }

        foreach ($messages as $message) {
            $filteredErrors[] = $message;
        }
    }
@endphp

@if (!empty($filteredErrors))
    <div data-flash-message data-flash-type="error" class="mb-5 rounded-xl border border-rose-300/40 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
        @if (request()->routeIs(...$authSingleErrorRoutes))
            {{ $filteredErrors[0] }}
        @else
            <ul class="space-y-1">
                @foreach ($filteredErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if (!$suppressStatus && session('status'))
    <div data-flash-message data-flash-type="success" class="mb-5 rounded-xl border border-emerald-300/40 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
        {{ session('status') }}
    </div>
@endif
