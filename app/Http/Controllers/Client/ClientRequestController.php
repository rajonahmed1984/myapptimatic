<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\Invoice;
use App\Models\LicenseDomain;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientRequestController extends Controller
{
    private const TYPES = [
        'invoice_edit',
        'invoice_cancel',
        'invoice_delete',
        'subscription_edit',
        'subscription_cancel',
        'subscription_delete',
        'domain_edit',
        'domain_delete',
    ];

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;

        if (! $customer) {
            abort(403);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'license_domain_id' => ['nullable', 'integer', 'exists:license_domains,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice = null;
        $subscription = null;
        $licenseDomain = null;

        if (str_starts_with($data['type'], 'invoice_')) {
            if (empty($data['invoice_id'])) {
                return back()->withErrors(['type' => 'Select an invoice to submit this request.']);
            }

            $invoice = Invoice::query()->findOrFail($data['invoice_id']);

            if ($invoice->customer_id !== $customer->id) {
                abort(403);
            }
        }

        if (str_starts_with($data['type'], 'subscription_')) {
            if (empty($data['subscription_id'])) {
                return back()->withErrors(['type' => 'Select a service to submit this request.']);
            }

            $subscription = Subscription::query()->findOrFail($data['subscription_id']);

            if ($subscription->customer_id !== $customer->id) {
                abort(403);
            }
        }

        if (str_starts_with($data['type'], 'domain_')) {
            if (empty($data['license_domain_id'])) {
                return back()->withErrors(['type' => 'Select a domain to submit this request.']);
            }

            $licenseDomain = LicenseDomain::query()
                ->with('license.subscription')
                ->findOrFail($data['license_domain_id']);

            $subscriptionId = $licenseDomain->license?->subscription?->id;
            $customerId = $licenseDomain->license?->subscription?->customer_id;

            if (! $subscriptionId || $customerId !== $customer->id) {
                abort(403);
            }
        }

        $duplicateQuery = ClientRequest::query()
            ->where('customer_id', $customer->id)
            ->where('type', $data['type'])
            ->where('status', 'pending');

        if ($invoice) {
            $duplicateQuery->where('invoice_id', $invoice->id);
        }

        if ($subscription) {
            $duplicateQuery->where('subscription_id', $subscription->id);
        }

        if ($licenseDomain) {
            $duplicateQuery->where('license_domain_id', $licenseDomain->id);
        }

        if ($duplicateQuery->exists()) {
            return back()->with('status', 'A similar request is already pending.');
        }

        ClientRequest::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()?->id,
            'invoice_id' => $invoice?->id,
            'subscription_id' => $subscription?->id ?? $licenseDomain?->license?->subscription_id,
            'license_domain_id' => $licenseDomain?->id,
            'type' => $data['type'],
            'status' => 'pending',
            'message' => $data['message'] ?? null,
        ]);

        return back()->with('status', 'Request submitted. Our team will review it shortly.');
    }
}
