<?php

namespace App\Http\Middleware;

use App\Models\Invoice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareClientInvoiceStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $customer = $user?->customer;

        if ($customer) {
            $openInvoices = Invoice::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->orderBy('due_date')
                ->get();

            $nextInvoice = $openInvoices->first();

            View::share('clientInvoiceNotice', [
                'has_due' => $openInvoices->isNotEmpty(),
                'overdue_count' => $openInvoices->where('status', 'overdue')->count(),
                'unpaid_count' => $openInvoices->where('status', 'unpaid')->count(),
                'next_due_date' => $nextInvoice?->due_date,
                'payment_url' => $nextInvoice ? route('client.invoices.pay', $nextInvoice) : null,
            ]);
        }

        return $next($request);
    }
}
