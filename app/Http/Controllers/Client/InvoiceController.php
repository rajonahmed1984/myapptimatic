<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function pay(Request $request, Invoice $invoice)
    {
        $customerId = $request->user()->customer_id;

        abort_unless($invoice->customer_id === $customerId, 403);

        return view('client.invoices.pay', [
            'invoice' => $invoice->load('items'),
            'paymentInstructions' => Setting::getValue('payment_instructions'),
        ]);
    }
}
