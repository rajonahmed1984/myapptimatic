<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SupportTicket;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'customerCount' => Customer::count(),
            'productCount' => Product::count(),
            'subscriptionCount' => Subscription::count(),
            'licenseCount' => License::count(),
            'pendingInvoiceCount' => Invoice::where('status', 'unpaid')->count(),
            'overdueCount' => Invoice::where('status', 'overdue')->count(),
            'openTicketCount' => SupportTicket::where('status', 'open')->count(),
            'customerReplyTicketCount' => SupportTicket::where('status', 'customer_reply')->count(),
        ]);
    }
}
