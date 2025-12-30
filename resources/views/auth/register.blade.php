@extends('layouts.guest')

@section('title', 'Create Account')

@section('content')
    <div class="section-label">Client registration</div>
    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Create your account</h2>
    <p class="mt-2 text-sm text-slate-600">Get access to invoices, licenses, and support.</p>

    <form class="mt-8 space-y-5" method="POST" action="{{ route('register.store') }}">
        @csrf
        @if(request('redirect'))
            <input type="hidden" name="redirect" value="{{ request('redirect') }}" />
        @endif
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="text-sm text-slate-600">Full name</label>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
                />
            </div>
            <div>
                <label class="text-sm text-slate-600">Company name</label>
                <input
                    type="text"
                    name="company_name"
                    value="{{ old('company_name') }}"
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
                />
            </div>
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
            <div>
                <label class="text-sm text-slate-600">Confirm password</label>
                <input
                    type="password"
                    name="password_confirmation"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
                />
            </div>
            <div>
                <label class="text-sm text-slate-600">Mobile number</label>
                <input
                    type="text"
                    name="phone"
                    value="{{ old('phone') }}"
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
                />
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Address</label>
                <textarea
                    name="address"
                    rows="2"
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
                >{{ old('address') }}</textarea>
            </div>
        </div>
        @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
            <div class="flex justify-center">
                <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}" data-action="REGISTER"></div>
            </div>
        @endif
        <button
            type="submit"
            class="w-full rounded-xl bg-teal-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-400"
        >
            Create account
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500">
        Already have an account? <a href="{{ route('login', request('redirect') ? ['redirect' => request('redirect')] : []) }}" class="font-semibold text-teal-600 hover:text-teal-500">Sign in</a>.
    </p>

    @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/enterprise.js" async defer></script>
    @endif
@endsection
