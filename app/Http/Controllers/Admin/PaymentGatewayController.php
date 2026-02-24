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
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PaymentGatewayController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $gateways = PaymentGateway::query()->orderBy('sort_order')->get();

        return Inertia::render(
            'Admin/PaymentGateways/Index',
            $this->indexInertiaProps($gateways)
        );
    }

    public function edit(PaymentGateway $paymentGateway): InertiaResponse
    {
        $defaultCurrency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($defaultCurrency)) {
            $defaultCurrency = Currency::DEFAULT;
        }

        return Inertia::render(
            'Admin/PaymentGateways/Edit',
            $this->editInertiaProps($paymentGateway, $defaultCurrency)
        );
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

    private function indexInertiaProps($gateways): array
    {
        return [
            'pageTitle' => 'Payment Gateways',
            'gateways' => collect($gateways)->map(function (PaymentGateway $gateway) {
                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'driver' => ucfirst((string) $gateway->driver),
                    'is_active' => (bool) $gateway->is_active,
                    'routes' => [
                        'edit' => route('admin.payment-gateways.edit', $gateway),
                    ],
                ];
            })->values()->all(),
        ];
    }

    private function editInertiaProps(PaymentGateway $gateway, string $defaultCurrency): array
    {
        $settings = is_array($gateway->settings) ? $gateway->settings : [];

        return [
            'pageTitle' => 'Edit Payment Gateway',
            'gateway' => [
                'id' => $gateway->id,
                'driver' => (string) $gateway->driver,
                'name' => (string) old('name', (string) $gateway->name),
                'sort_order' => (int) old('sort_order', (int) $gateway->sort_order),
                'is_active' => (bool) old('is_active', (bool) $gateway->is_active),
                'deactivate' => (bool) old('deactivate', false),
                'fields' => [
                    'instructions' => (string) old('instructions', (string) ($settings['instructions'] ?? '')),
                    'payment_url' => (string) old('payment_url', (string) ($settings['payment_url'] ?? '')),
                    'account_name' => (string) old('account_name', (string) ($settings['account_name'] ?? '')),
                    'account_number' => (string) old('account_number', (string) ($settings['account_number'] ?? '')),
                    'bank_name' => (string) old('bank_name', (string) ($settings['bank_name'] ?? '')),
                    'branch' => (string) old('branch', (string) ($settings['branch'] ?? '')),
                    'routing_number' => (string) old('routing_number', (string) ($settings['routing_number'] ?? '')),
                    'merchant_number' => (string) old('merchant_number', (string) ($settings['merchant_number'] ?? '')),
                    'api_key' => (string) old('api_key', (string) ($settings['api_key'] ?? '')),
                    'merchant_short_code' => (string) old('merchant_short_code', (string) ($settings['merchant_short_code'] ?? '')),
                    'service_id' => (string) old('service_id', (string) ($settings['service_id'] ?? '')),
                    'username' => (string) old('username', (string) ($settings['username'] ?? '')),
                    'password' => (string) old('password', (string) ($settings['password'] ?? '')),
                    'app_key' => (string) old('app_key', (string) ($settings['app_key'] ?? '')),
                    'app_secret' => (string) old('app_secret', (string) ($settings['app_secret'] ?? '')),
                    'button_label' => (string) old('button_label', (string) ($settings['button_label'] ?? '')),
                    'store_id' => (string) old('store_id', (string) ($settings['store_id'] ?? '')),
                    'store_password' => (string) old('store_password', (string) ($settings['store_password'] ?? '')),
                    'client_id' => (string) old('client_id', (string) ($settings['client_id'] ?? '')),
                    'client_secret' => (string) old('client_secret', (string) ($settings['client_secret'] ?? '')),
                    'paypal_email' => (string) old('paypal_email', (string) ($settings['paypal_email'] ?? '')),
                    'api_username' => (string) old('api_username', (string) ($settings['api_username'] ?? '')),
                    'api_password' => (string) old('api_password', (string) ($settings['api_password'] ?? '')),
                    'api_signature' => (string) old('api_signature', (string) ($settings['api_signature'] ?? '')),
                    'processing_currency' => (string) old('processing_currency', (string) ($settings['processing_currency'] ?? $defaultCurrency)),
                    'sandbox' => (bool) old('sandbox', (bool) ($settings['sandbox'] ?? false)),
                    'easy_checkout' => (bool) old('easy_checkout', (bool) ($settings['easy_checkout'] ?? false)),
                    'force_one_time' => (bool) old('force_one_time', (bool) ($settings['force_one_time'] ?? false)),
                    'force_subscriptions' => (bool) old('force_subscriptions', (bool) ($settings['force_subscriptions'] ?? false)),
                    'require_shipping' => (bool) old('require_shipping', (bool) ($settings['require_shipping'] ?? false)),
                    'client_address_matching' => (bool) old('client_address_matching', (bool) ($settings['client_address_matching'] ?? false)),
                ],
            ],
            'currency_options' => array_values(Currency::allowed()),
            'default_currency' => $defaultCurrency,
            'routes' => [
                'index' => route('admin.payment-gateways.index'),
                'update' => route('admin.payment-gateways.update', $gateway),
            ],
        ];
    }
}
