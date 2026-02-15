@extends('layouts.guest')

@section('title', 'Project Client Sign In')

@section('content')
    <div class="section-label">Project client login</div>
    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Sign in to view your project</h2>
    <p class="mt-2 text-sm text-slate-600">Add tasks and monitor status for your assigned project.</p>

    <form class="mt-8 space-y-5" method="POST" action="{{ route('project-client.login.attempt', [], false) }}">
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
        <div class="flex items-center justify-between text-sm text-slate-500">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-teal-500 focus:ring-teal-200" />
                Remember me
            </label>
        </div>
        @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
            <div class="flex justify-center">
                <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}" data-action="PROJECT_CLIENT_LOGIN"></div>
            </div>
        @endif
        <button
            type="submit"
            class="w-full rounded-xl bg-teal-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-400"
        >
            Sign in to project
        </button>
    </form>

    @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/enterprise.js" async defer></script>
    @endif
@endsection
