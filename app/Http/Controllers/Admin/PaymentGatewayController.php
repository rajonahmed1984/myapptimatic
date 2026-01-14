<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Support\Currency;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        return view('admin.payment-gateways.index', [
            'gateways' => PaymentGateway::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(PaymentGateway $paymentGateway)
    {
        $defaultCurrency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($defaultCurrency)) {
            $defaultCurrency = Currency::DEFAULT;
        }

        return view('admin.payment-gateways.edit', [
            'gateway' => $paymentGateway,
            'currencyOptions' => Currency::allowed(),
            'defaultCurrency' => $defaultCurrency,
        ]);
    }

    public function update(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $wasActive = (bool) $paymentGateway->is_active;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string'],
            'payment_url' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'routing_number' => ['nullable', 'string', 'max:255'],
            'merchant_number' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'merchant_short_code' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'app_key' => ['nullable', 'string', 'max:255'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'button_label' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'string', 'max:255'],
            'store_password' => ['nullable', 'string', 'max:255'],
            'easy_checkout' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'paypal_email' => ['nullable', 'email', 'max:255'],
            'api_username' => ['nullable', 'string', 'max:255'],
            'api_password' => ['nullable', 'string', 'max:255'],
            'api_signature' => ['nullable', 'string', 'max:255'],
            'force_one_time' => ['nullable', 'boolean'],
            'force_subscriptions' => ['nullable', 'boolean'],
            'require_shipping' => ['nullable', 'boolean'],
            'client_address_matching' => ['nullable', 'boolean'],
            'processing_currency' => ['nullable', 'string', 'size:3', Rule::in(Currency::allowed())],
            'sandbox' => ['nullable', 'boolean'],
            'deactivate' => ['nullable', 'boolean'],
        ]);

        $settings = $paymentGateway->settings ?? [];

        switch ($paymentGateway->driver) {
            case 'manual':
                $settings = array_merge($settings, [
                    'instructions' => $data['instructions'] ?? '',
                    'payment_url' => $data['payment_url'] ?? '',
                    'account_name' => $data['account_name'] ?? '',
                    'account_number' => $data['account_number'] ?? '',
                    'bank_name' => $data['bank_name'] ?? '',
                    'branch' => $data['branch'] ?? '',
                    'routing_number' => $data['routing_number'] ?? '',
                    'button_label' => $data['button_label'] ?? '',
                ]);
                break;
            case 'bkash':
                $settings = array_merge($settings, [
                    'merchant_number' => $data['merchant_number'] ?? '',
                    'payment_url' => $data['payment_url'] ?? '',
                ]);
                break;
            case 'bkash_api':
                $settings = array_merge($settings, [
                    'api_key' => $data['api_key'] ?? '',
                    'merchant_short_code' => $data['merchant_short_code'] ?? '',
                    'service_id' => $data['service_id'] ?? '',
                    'sandbox' => $request->boolean('sandbox'),
                ]);
                break;
            case 'sslcommerz':
                $settings = array_merge($settings, [
                    'instructions' => $data['instructions'] ?? '',
                    'payment_url' => $data['payment_url'] ?? '',
                    'store_id' => $data['store_id'] ?? '',
                    'store_password' => $data['store_password'] ?? '',
                    'button_label' => $data['button_label'] ?? '',
                    'easy_checkout' => $request->boolean('easy_checkout'),
                    'processing_currency' => isset($data['processing_currency'])
                        ? strtoupper($data['processing_currency'])
                        : '',
                    'sandbox' => $request->boolean('sandbox'),
                ]);
                break;
            case 'paypal':
                $settings = array_merge($settings, [
                    'instructions' => $data['instructions'] ?? '',
                    'payment_url' => $data['payment_url'] ?? '',
                    'paypal_email' => $data['paypal_email'] ?? '',
                    'api_username' => $data['api_username'] ?? '',
                    'api_password' => $data['api_password'] ?? '',
                    'api_signature' => $data['api_signature'] ?? '',
                    'force_one_time' => $request->boolean('force_one_time'),
                    'force_subscriptions' => $request->boolean('force_subscriptions'),
                    'require_shipping' => $request->boolean('require_shipping'),
                    'client_address_matching' => $request->boolean('client_address_matching'),
                    'client_id' => $data['client_id'] ?? '',
                    'client_secret' => $data['client_secret'] ?? '',
                    'button_label' => $data['button_label'] ?? '',
                    'processing_currency' => 'USD',
                    'sandbox' => $request->boolean('sandbox'),
                ]);
                break;
        }

        $isActive = $request->boolean('is_active');
        if ($request->boolean('deactivate')) {
            $isActive = false;
        }

        $paymentGateway->update([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? $paymentGateway->sort_order,
            'is_active' => $isActive,
            'settings' => $settings,
        ]);

        SystemLogger::write('activity', 'Payment gateway updated.', [
            'gateway_id' => $paymentGateway->id,
            'name' => $paymentGateway->name,
            'driver' => $paymentGateway->driver,
            'was_active' => $wasActive,
            'is_active' => $isActive,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.payment-gateways.index')
            ->with('status', 'Payment gateway updated.');
    }
}
