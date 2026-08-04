<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\OwnershipTransfer;
use App\Services\ClientNotificationService;
use App\Services\ProjectTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TransferController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $customerId = $request->user()->customer_id;
        $dateFormat = (string) config('app.date_format', 'd-m-Y');

        $transfers = OwnershipTransfer::query()
            ->where('to_customer_id', $customerId)
            ->with(['project:id,name', 'fromCustomer:id,name'])
            ->latest()
            ->get();

        return Inertia::render('Client/Transfers/Index', [
            'pageTitle' => 'Incoming Transfers',
            'transfers' => $transfers->map(function (OwnershipTransfer $transfer) use ($dateFormat) {
                return [
                    'id' => $transfer->id,
                    'project_name' => (string) ($transfer->project?->name ?? '--'),
                    'from_customer_name' => (string) ($transfer->fromCustomer?->name ?? '--'),
                    'status' => (string) $transfer->status,
                    'status_label' => ucfirst((string) $transfer->status),
                    'created_at' => $transfer->created_at?->format($dateFormat) ?? '--',
                ];
            })->values()->all(),
        ]);
    }

    public function confirm(Request $request, OwnershipTransfer $transfer): InertiaResponse
    {
        $token = (string) $request->query('token', '');
        $tokenValid = $transfer->token_hash && $token !== '' && hash_equals($transfer->token_hash, hash('sha256', $token));

        $transfer->loadMissing(['project', 'fromCustomer']);

        return Inertia::render('Client/Transfers/Confirm', [
            'pageTitle' => 'Ownership Transfer',
            'tokenValid' => $tokenValid,
            'token' => $tokenValid ? $token : null,
            'canReview' => $tokenValid && $transfer->isAcceptable() && auth()->user()->customer_id === $transfer->to_customer_id,
            'transfer' => [
                'id' => $transfer->id,
                'project_name' => (string) ($transfer->project?->name ?? '--'),
                'from_customer_name' => (string) ($transfer->fromCustomer?->name ?? '--'),
                'status' => (string) $transfer->status,
                'status_label' => ucfirst((string) $transfer->status),
                'reason' => (string) ($transfer->reason ?? ''),
            ],
            'routes' => [
                'accept' => route('client.transfers.accept', $transfer),
                'reject' => route('client.transfers.reject', $transfer),
            ],
        ]);
    }

    public function accept(
        Request $request,
        OwnershipTransfer $transfer,
        ProjectTransferService $service,
        ClientNotificationService $clientNotifications
    ): RedirectResponse {
        $this->verifyTokenOrAbort($request, $transfer);
        $this->authorize('accept', $transfer);

        abort_unless($transfer->isAcceptable(), 422, 'This transfer can no longer be accepted.');

        $service->accept($transfer, $request->user(), (string) $request->ip());
        $clientNotifications->sendTransferAccepted($transfer);

        return redirect()->route('client.transfers.index')
            ->with('status', 'Transfer accepted.');
    }

    public function reject(
        Request $request,
        OwnershipTransfer $transfer,
        ProjectTransferService $service,
        ClientNotificationService $clientNotifications
    ): RedirectResponse {
        $this->verifyTokenOrAbort($request, $transfer);
        $this->authorize('reject', $transfer);

        abort_unless($transfer->isAcceptable(), 422, 'This transfer can no longer be rejected.');

        $service->reject($transfer, $request->user(), (string) $request->ip());
        $clientNotifications->sendTransferRejected($transfer);

        return redirect()->route('client.transfers.index')
            ->with('status', 'Transfer rejected.');
    }

    private function verifyTokenOrAbort(Request $request, OwnershipTransfer $transfer): void
    {
        $token = (string) $request->input('token', '');

        abort_unless(
            $transfer->token_hash && $token !== '' && hash_equals($transfer->token_hash, hash('sha256', $token)),
            403,
            'Invalid or expired transfer token.'
        );
    }
}
