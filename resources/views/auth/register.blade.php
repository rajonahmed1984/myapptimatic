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
                <div class="relative">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 pr-10 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
                    />
                    <button type="button" onclick="togglePassword('password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path class="eye-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path class="eye-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            <path class="eye-closed hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
            </div>
            <div>
                <label class="text-sm text-slate-600">Confirm password</label>
                <div class="relative">
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 pr-10 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
                    />
                    <button type="button" onclick="togglePassword('password_confirmation', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path class="eye-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path class="eye-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            <path class="eye-closed hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <div id="password-match-message" class="mt-1 text-xs"></div>
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
            <div>
                <label class="text-sm text-slate-600">Currency</label>
                <select
                    name="currency"
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
                >
                    <option value="BDT" {{ old('currency', 'BDT') === 'BDT' ? 'selected' : '' }}>BDT (৳)</option>
                    <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD ($)</option>
                </select>
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

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const eyeOpen = button.querySelectorAll('.eye-open');
            const eyeClosed = button.querySelectorAll('.eye-closed');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.forEach(el => el.classList.add('hidden'));
                eyeClosed.forEach(el => el.classList.remove('hidden'));
            } else {
                input.type = 'password';
                eyeOpen.forEach(el => el.classList.remove('hidden'));
                eyeClosed.forEach(el => el.classList.add('hidden'));
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmation = document.getElementById('password_confirmation').value;
            const message = document.getElementById('password-match-message');
            
            if (confirmation === '') {
                message.textContent = '';
                return;
            }
            
            if (password === confirmation) {
                message.textContent = '✓ Passwords match';
                message.className = 'mt-1 text-xs text-green-600';
            } else {
                message.textContent = '✗ Passwords do not match';
                message.className = 'mt-1 text-xs text-red-600';
            }
        }

        document.getElementById('password').addEventListener('input', checkPasswordMatch);
        document.getElementById('password_confirmation').addEventListener('input', checkPasswordMatch);
    </script>
@endsection
