@extends('layouts.guest')

@section('title', 'Client Sign In')

@section('content')
    <div class="section-label">Client login</div>
    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Sign in to your workspace</h2>
    <p class="mt-2 text-sm text-slate-600">Track invoices, licenses, and subscription status.</p>

    <form class="mt-8 space-y-5" method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <div>
            <label class="text-sm text-slate-600">Email</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
            />
        </div>
        <div>
            <label class="text-sm text-slate-600">Password</label>
            <input
                type="password"
                name="password"
                required
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
            />
        </div>
        <div class="flex items-center gap-2 text-sm text-slate-500">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-teal-500 focus:ring-teal-200" />
            Remember me
        </div>
        <button
            type="submit"
            class="w-full rounded-xl bg-teal-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-400"
        >
            Sign in
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500">
        Admin access? Use the <a href="{{ route('admin.login') }}" class="font-semibold text-teal-600 hover:text-teal-500">admin login</a>.
    </p>
@endsection
