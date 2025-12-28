<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('admin.invoices.index', [
            'invoices' => Invoice::query()->with('customer')->latest('issue_date')->get(),
        ]);
    }

    public function show(Invoice $invoice)
    {
        return view('admin.invoices.show', [
            'invoice' => $invoice->load(['customer', 'items']),
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice marked as paid.');
    }
}
