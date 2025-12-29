<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentProofController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $allowed = ['pending', 'approved', 'rejected'];

        $query = PaymentProof::query()
            ->with(['invoice.customer', 'paymentGateway', 'reviewer'])
            ->latest();

        if ($status && in_array($status, $allowed, true)) {
            $query->where('status', $status);
        } else {
            $status = 'pending';
            $query->where('status', $status);
        }

        return view('admin.payment-proofs.index', [
            'paymentProofs' => $query->get(),
            'status' => $status,
        ]);
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

        return back()->with('status', 'Manual payment rejected.');
    }
}
