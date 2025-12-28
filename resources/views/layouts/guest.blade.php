<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-guest">
    <main class="min-h-screen px-6 py-12">
        <div class="mx-auto flex min-h-[70vh] max-w-5xl flex-col items-center justify-center gap-10 md:flex-row">
            <div class="max-w-md">
                <div class="flex items-center gap-3">
                    @if(!empty($portalBranding['logo_url']))
                        <img src="{{ $portalBranding['logo_url'] }}" alt="Logo" class="h-10 w-10 rounded-xl bg-white p-1">
                    @endif
                    <div class="section-label">{{ $portalBranding['company_name'] ?? 'MyApptimatic' }}</div>
                </div>
                <h1 class="mt-3 text-4xl font-semibold">License and billing control center.</h1>
                <p class="mt-4 text-sm text-slate-600">
                    Manage subscriptions, invoices, and domain verification from one modern portal.
                </p>
                <div class="mt-6 flex flex-wrap gap-2 text-xs text-slate-500">
                    <span class="pill">Secure</span>
                    <span class="pill">Automated billing</span>
                    <span class="pill">Domain checks</span>
                </div>
            </div>
            <div class="w-full max-w-md">
                <div class="card p-8">
                    @if ($errors->any())
                        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <ul class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="mb-6 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </div>
    </main>
</body>
</html>
