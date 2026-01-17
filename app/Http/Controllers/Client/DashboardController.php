<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LicenseDomain;
use App\Models\Project;
use App\Models\Setting;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user()->customer;
        $subscriptions = $customer?->subscriptions()->with('plan.product')->get() ?? collect();
        $invoices = $customer?->invoices()->latest('issue_date')->limit(5)->get() ?? collect();
        $licenses = $customer?->licenses()
            ->with(['product', 'subscription.plan', 'domains'])
            ->get() ?? collect();
        $openInvoices = $customer
            ? $customer->invoices()
                ->whereIn('status', ['unpaid', 'overdue'])
                ->orderBy('due_date')
                ->get()
            : collect();
        $overdueInvoices = $customer
            ? $customer->invoices()
                ->where('status', 'overdue')
                ->orderBy('due_date')
                ->get()
            : collect();
        $openInvoiceCount = $openInvoices->count();
        $overdueInvoiceCount = $overdueInvoices->count();
        $openInvoiceBalance = $openInvoices->sum('total');
        $overdueInvoiceBalance = $overdueInvoices->sum('total');
        $nextOverdueInvoice = $overdueInvoices->first();
        $nextOpenInvoice = $openInvoices->first();
        $ticketOpenCount = $customer
            ? $customer->supportTickets()
                ->whereIn('status', ['open', 'customer_reply'])
                ->count()
            : 0;
        $recentTickets = $customer
            ? $customer->supportTickets()
                ->latest('updated_at')
                ->limit(4)
                ->get()
            : collect();
        $domainCount = $customer
            ? LicenseDomain::query()
                ->where('status', 'active')
                ->whereHas('license.subscription', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->id);
                })
                ->count()
            : 0;
        $expiringLicenses = $customer
            ? $customer->licenses()
                ->with('product')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [Carbon::today(), Carbon::today()->addDays(45)])
                ->orderBy('expires_at')
                ->limit(4)
                ->get()
            : collect();
        $projects = collect();
        if ($customer) {
            $projectQuery = Project::where('customer_id', $customer->id)
                ->withCount([
                    'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['todo', 'in_progress', 'blocked']),
                    'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done'),
                ]);

            if ($request->user()->isClientProject() && $request->user()->project_id) {
                $projectQuery->whereKey($request->user()->project_id);
            }

            $projects = $projectQuery
                ->latest()
                ->limit(5)
                ->get();
        }
        $maintenanceRenewal = $subscriptions
            ->where('status', 'active')
            ->filter(fn ($s) => $s->next_invoice_at)
            ->sortBy('next_invoice_at')
            ->first();

        return view('client.dashboard', [
            'customer' => $customer,
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'licenses' => $licenses,
            'serviceCount' => $subscriptions->count(),
            'domainCount' => $domainCount,
            'ticketOpenCount' => $ticketOpenCount,
            'openInvoiceCount' => $openInvoiceCount,
            'openInvoiceBalance' => $openInvoiceBalance,
            'overdueInvoiceCount' => $overdueInvoiceCount,
            'overdueInvoiceBalance' => $overdueInvoiceBalance,
            'nextOverdueInvoice' => $nextOverdueInvoice,
            'nextOpenInvoice' => $nextOpenInvoice,
            'recentTickets' => $recentTickets,
            'expiringLicenses' => $expiringLicenses,
            'currency' => strtoupper((string) Setting::getValue('currency', Currency::DEFAULT)),
            'projects' => $projects,
            'maintenanceRenewal' => $maintenanceRenewal,
        ]);
    }
}
