<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Support\HybridUiResponder;
use App\Support\SystemLogger;
use App\Support\UiFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function index(
        Request $request,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $status = $request->query('status');
        $allowed = ['pending', 'approved', 'rejected', 'all'];
        $search = trim((string) $request->input('search', ''));

        $query = PaymentProof::query()
            ->with(['invoice.customer', 'paymentGateway', 'reviewer'])
            ->latest();

        if ($status && in_array($status, $allowed, true) && $status !== 'all') {
            $query->where('status', $status);
        } else {
            $status = $status === 'all' || $status === null ? 'all' : $status;
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('reference', 'like', '%'.$search.'%')
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                        $invoiceQuery->where('number', 'like', '%'.$search.'%')
                            ->orWhere('id', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('paymentGateway', function ($gatewayQuery) use ($search) {
                        $gatewayQuery->where('name', 'like', '%'.$search.'%');
                    });

                if (is_numeric($search)) {
                    $inner->orWhere('id', (int) $search)
                        ->orWhere('amount', (float) $search);
                }
            });
        }

        $payload = [
            'paymentProofs' => $query->get(),
            'status' => $status,
            'search' => $search,
        ];

        if ($request->header('HX-Request')) {
            return view('admin.payment-proofs.partials.table', $payload);
        }

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_PAYMENT_PROOFS_INDEX,
            'admin.payment-proofs.index',
            $payload,
            'Admin/PaymentProofs/Index',
            $this->indexInertiaProps($payload['paymentProofs'], (string) $status, $search)
        );
    }

    public function approve(Request $request, PaymentProof $paymentProof, PaymentService $paymentService): RedirectResponse
    {
        $attempt = $paymentProof->paymentAttempt;

        if (! $attempt || $attempt->status === 'paid') {
            return back()->with('status', 'Payment already processed.');
        }

        $paymentService->markPaid($attempt, $paymentProof->reference ?? $attempt->gateway_reference ?? (string) $attempt->id, [
            'manual_proof_id' => $paymentProof->id,
        ]);

        $paymentProof->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        SystemLogger::write('activity', 'Manual payment approved.', [
            'payment_proof_id' => $paymentProof->id,
            'invoice_id' => $paymentProof->invoice_id,
            'gateway_id' => $paymentProof->payment_gateway_id,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Manual payment approved.');
    }

    public function reject(Request $request, PaymentProof $paymentProof): RedirectResponse
    {
        $paymentProof->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        $attempt = $paymentProof->paymentAttempt;
        if ($attempt && $attempt->status !== 'paid') {
            $attempt->update([
                'status' => 'failed',
            ]);
        }

        SystemLogger::write('activity', 'Manual payment rejected.', [
            'payment_proof_id' => $paymentProof->id,
            'invoice_id' => $paymentProof->invoice_id,
            'gateway_id' => $paymentProof->payment_gateway_id,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Manual payment rejected.');
    }

    public function receipt(PaymentProof $paymentProof): StreamedResponse
    {
        $path = (string) $paymentProof->attachment_path;

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    private function indexInertiaProps(
        Collection $paymentProofs,
        string $status,
        string $search
    ): array {
        $filters = [
            'all' => 'All',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
        $dateFormat = (string) Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));

        return [
            'pageTitle' => 'Manual Payments',
            'status' => $status,
            'search' => $search,
            'routes' => [
                'index' => route('admin.payment-proofs.index'),
            ],
            'filter_links' => collect($filters)->map(function (string $label, string $key) use ($status) {
                return [
                    'key' => $key,
                    'label' => $label,
                    'active' => $status === $key,
                    'href' => route('admin.payment-proofs.index', ['status' => $key]),
                ];
            })->values()->all(),
            'payment_proofs' => $paymentProofs->values()->map(function (PaymentProof $proof) use ($dateFormat) {
                $invoice = $proof->invoice;
                $invoiceNumber = is_numeric($invoice?->number) ? (string) $invoice->number : (string) $proof->invoice_id;
                $invoiceUrl = $invoice ? route('admin.invoices.show', $invoice) : null;

                return [
                    'id' => $proof->id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_url' => $invoiceUrl,
                    'customer_name' => (string) ($proof->customer?->name ?? '--'),
                    'gateway_name' => (string) ($proof->paymentGateway?->name ?? 'Manual'),
                    'amount_display' => (string) (($invoice?->currency ?? 'BDT').' '.number_format((float) $proof->amount, 2)),
                    'reference' => (string) ($proof->reference ?: '--'),
                    'status' => (string) $proof->status,
                    'status_label' => ucfirst((string) $proof->status),
                    'submitted_at_display' => $proof->created_at?->format($dateFormat) ?? '--',
                    'has_receipt' => (bool) ($proof->attachment_url || $proof->attachment_path),
                    'can_review' => (string) $proof->status === 'pending',
                    'reviewer_name' => $proof->reviewer?->name,
                    'routes' => [
                        'receipt' => route('admin.payment-proofs.receipt', $proof),
                        'approve' => route('admin.payment-proofs.approve', $proof),
                        'reject' => route('admin.payment-proofs.reject', $proof),
                    ],
                ];
            })->all(),
        ];
    }
}
