@extends('layouts.admin')

@section('title', 'Edit Payment Gateway')
@section('page-title', 'Edit Payment Gateway')

@section('content')
    @php($settings = $gateway->settings ?? [])

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Edit {{ $gateway->name }}</h1>
        <p class="mt-1 text-sm text-slate-500">Update credentials and instructions for this gateway.</p>
    </div>

    <form method="POST" action="{{ route('admin.payment-gateways.update', $gateway) }}" class="card grid gap-4 p-6 md:grid-cols-2">
        @csrf
        @method('PUT')

        <div class="md:col-span-2">
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="hidden" name="is_active" value="0" />
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $gateway->is_active)) class="rounded border-slate-300 text-teal-500" />
                {{ $gateway->driver === 'bkash' ? 'Show on Order Form' : 'Enable gateway' }}
            </label>
        </div>

        <div>
            <label class="text-sm text-slate-600">Display Name</label>
            <input name="name" value="{{ old('name', $gateway->name) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" required />
        </div>

        <div>
            <label class="text-sm text-slate-600">Sort order</label>
            <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $gateway->sort_order) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
        </div>

        <div class="md:col-span-2">
            <label class="text-sm text-slate-600">Callback URLs (register in gateway)</label>
            <div class="mt-2 space-y-2 text-xs text-slate-500">
                @if($gateway->driver === 'sslcommerz')
                    <div>SSLCommerz success: {{ url('/payments/sslcommerz/{attempt}/success') }}</div>
                    <div>SSLCommerz fail: {{ url('/payments/sslcommerz/{attempt}/fail') }}</div>
                    <div>SSLCommerz cancel: {{ url('/payments/sslcommerz/{attempt}/cancel') }}</div>
                @elseif($gateway->driver === 'bkash')
                    <div>bKash callback: {{ url('/payments/bkash/{attempt}/callback') }}</div>
                @elseif($gateway->driver === 'paypal')
                    <div>PayPal return: {{ url('/payments/paypal/{attempt}/return') }}</div>
                    <div>PayPal cancel: {{ url('/payments/paypal/{attempt}/cancel') }}</div>
                @endif
            </div>
        </div>

        @if($gateway->driver === 'bkash')
            <div>
                <label class="text-sm text-slate-600">Merchant No</label>
                <input name="merchant_number" value="{{ old('merchant_number', $settings['merchant_number'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                <p class="mt-2 text-xs text-slate-500">Enter your merchant number here.</p>
            </div>
            <div>
                <label class="text-sm text-slate-600">Payment Link URL</label>
                <input name="payment_url" value="{{ old('payment_url', $settings['payment_url'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" placeholder="https://shop.bkash.com/your-link" />
                <p class="mt-2 text-xs text-slate-500">Enter your bKash payment url.</p>
            </div>
            <div>
                <label class="text-sm text-slate-600">Convert To For Processing</label>
                @php($processingCurrency = old('processing_currency', $settings['processing_currency'] ?? $defaultCurrency ?? 'BDT'))
                <select name="processing_currency" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    @foreach($currencyOptions as $currency)
                        <option value="{{ $currency }}" @selected(strtoupper($processingCurrency) === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Username (optional)</label>
                <input name="username" value="{{ old('username', $settings['username'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Password (optional)</label>
                <input name="password" type="password" value="{{ old('password', $settings['password'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">App key (optional)</label>
                <input name="app_key" value="{{ old('app_key', $settings['app_key'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">App secret (optional)</label>
                <input name="app_secret" type="password" value="{{ old('app_secret', $settings['app_secret'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="sandbox" value="0" />
                    <input type="checkbox" name="sandbox" value="1" @checked(old('sandbox', $settings['sandbox'] ?? true)) class="rounded border-slate-300 text-teal-500" />
                    Sandbox mode (optional)
                </label>
            </div>
        @elseif($gateway->driver === 'paypal')
            <div>
                <label class="text-sm text-slate-600">PayPal Email</label>
                <input name="paypal_email" type="email" value="{{ old('paypal_email', $settings['paypal_email'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Convert To For Processing</label>
                @php($processingCurrency = old('processing_currency', $settings['processing_currency'] ?? $defaultCurrency ?? 'USD'))
                <select name="processing_currency" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    @foreach($currencyOptions as $currency)
                        <option value="{{ $currency }}" @selected(strtoupper($processingCurrency) === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">API Username</label>
                <input name="api_username" value="{{ old('api_username', $settings['api_username'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">API Password</label>
                <input name="api_password" type="password" value="{{ old('api_password', $settings['api_password'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">API Signature</label>
                <input name="api_signature" type="password" value="{{ old('api_signature', $settings['api_signature'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div class="md:col-span-2 grid gap-3 text-sm text-slate-600">
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="force_one_time" value="0" />
                    <input type="checkbox" name="force_one_time" value="1" @checked(old('force_one_time', $settings['force_one_time'] ?? false)) class="rounded border-slate-300 text-teal-500" />
                    Force One Time Payments
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="force_subscriptions" value="0" />
                    <input type="checkbox" name="force_subscriptions" value="1" @checked(old('force_subscriptions', $settings['force_subscriptions'] ?? false)) class="rounded border-slate-300 text-teal-500" />
                    Force Subscriptions
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="require_shipping" value="0" />
                    <input type="checkbox" name="require_shipping" value="1" @checked(old('require_shipping', $settings['require_shipping'] ?? false)) class="rounded border-slate-300 text-teal-500" />
                    Require Shipping Address
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="client_address_matching" value="0" />
                    <input type="checkbox" name="client_address_matching" value="1" @checked(old('client_address_matching', $settings['client_address_matching'] ?? false)) class="rounded border-slate-300 text-teal-500" />
                    Client Address Matching
                </label>
            </div>
        @elseif($gateway->driver === 'sslcommerz')
            <div>
                <label class="text-sm text-slate-600">Payment button label</label>
                <input name="button_label" value="{{ old('button_label', $settings['button_label'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" placeholder="Pay Now" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Store ID</label>
                <input name="store_id" value="{{ old('store_id', $settings['store_id'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Store password</label>
                <input name="store_password" type="password" value="{{ old('store_password', $settings['store_password'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Convert To For Processing</label>
                @php($processingCurrency = old('processing_currency', $settings['processing_currency'] ?? $defaultCurrency ?? 'BDT'))
                <select name="processing_currency" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    @foreach($currencyOptions as $currency)
                        <option value="{{ $currency }}" @selected(strtoupper($processingCurrency) === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="sandbox" value="0" />
                    <input type="checkbox" name="sandbox" value="1" @checked(old('sandbox', $settings['sandbox'] ?? true)) class="rounded border-slate-300 text-teal-500" />
                    Test Mode (Enable for Sandbox)
                </label>
            </div>
            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="easy_checkout" value="0" />
                    <input type="checkbox" name="easy_checkout" value="1" @checked(old('easy_checkout', $settings['easy_checkout'] ?? false)) class="rounded border-slate-300 text-teal-500" />
                    easyCheckout (Enable for easyCheckout Popup)
                </label>
            </div>
        @elseif($gateway->driver === 'manual')
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Payment instructions (shown to clients)</label>
                <textarea name="instructions" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('instructions', $settings['instructions'] ?? '') }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Payment URL (optional)</label>
                <input name="payment_url" value="{{ old('payment_url', $settings['payment_url'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" placeholder="https://pay.example.com/checkout" />
                <p class="mt-2 text-xs text-slate-500">Shown as a "Pay now" button to clients. Leave blank to hide the button.</p>
            </div>
            <div>
                <label class="text-sm text-slate-600">Account name</label>
                <input name="account_name" value="{{ old('account_name', $settings['account_name'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Account number</label>
                <input name="account_number" value="{{ old('account_number', $settings['account_number'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Bank name</label>
                <input name="bank_name" value="{{ old('bank_name', $settings['bank_name'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Branch</label>
                <input name="branch" value="{{ old('branch', $settings['branch'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Routing number</label>
                <input name="routing_number" value="{{ old('routing_number', $settings['routing_number'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
        @else
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Payment instructions (shown to clients)</label>
                <textarea name="instructions" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('instructions', $settings['instructions'] ?? '') }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Payment URL (optional)</label>
                <input name="payment_url" value="{{ old('payment_url', $settings['payment_url'] ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" placeholder="https://pay.example.com/checkout" />
                <p class="mt-2 text-xs text-slate-500">Shown as a "Pay now" button to clients. Leave blank to hide the button.</p>
            </div>
        @endif

        <div class="md:col-span-2 flex items-center justify-end gap-3">
            <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Save changes</button>
            <button type="submit" name="deactivate" value="1" class="rounded-full border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-600">Deactivate</button>
        </div>
    </form>
@endsection
