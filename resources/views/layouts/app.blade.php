<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MyApptimatic')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-xl bg-emerald-400/20 text-emerald-300 grid place-items-center font-semibold">LM</div>
                <div>
                    <div class="text-sm uppercase tracking-[0.3em] text-slate-400">License Portal</div>
                    <div class="text-lg font-semibold">MyApptimatic</div>
                </div>
            </div>
            @auth
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-slate-300">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-slate-700 px-4 py-2 text-slate-200 transition hover:border-emerald-400 hover:text-emerald-300">
                            Sign out
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </div>

    @auth
        <div class="bg-slate-900/60">
            <div class="mx-auto flex max-w-6xl gap-6 px-6 py-3 text-sm">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="text-slate-200 hover:text-emerald-300">Admin Dashboard</a>
                @else
                    <a href="{{ route('client.dashboard') }}" class="text-slate-200 hover:text-emerald-300">Client Dashboard</a>
                @endif
            </div>
        </div>
    @endauth

    <main class="mx-auto max-w-6xl px-6 py-10">
        @if(!empty($clientInvoiceNotice) && $clientInvoiceNotice['has_due'])
            @include('partials.overdue-banner', ['notice' => $clientInvoiceNotice])
        @endif

        @if ($errors->any())
            <div data-flash-message data-flash-type="error" class="mb-6 rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-200">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('status'))
            <div data-flash-message data-flash-type="success" class="mb-6 rounded-2xl border border-emerald-400/40 bg-emerald-400/10 p-4 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>

    @include('layouts.partials.delete-confirm-modal')
</body>
</html>
