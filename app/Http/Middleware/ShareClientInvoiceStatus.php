<?php

namespace App\Http\Middleware;

use App\Services\AccessBlockService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareClientInvoiceStatus
{
    public function __construct(
        private AccessBlockService $accessBlockService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $customer = $user?->customer;

        if ($customer) {
            $notice = $this->accessBlockService->invoiceBlockStatus($customer);
            $reason = (string) ($notice['reason'] ?? '');
            $hasDue = in_array($reason, ['invoice_due', 'invoice_overdue'], true);

            View::share('clientInvoiceNotice', [
                'has_due' => $hasDue,
                'message' => $notice['notice_message'] ?? null,
                'severity' => $notice['notice_severity'] ?? 'amber',
                'payment_url' => $notice['payment_url'] ?? null,
            ]);
        }

        return $next($request);
    }
}
