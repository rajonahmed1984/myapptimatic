<?php

namespace App\Http\Middleware;

use App\Services\AccessBlockService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PreventBlockedClientAccess
{
    public function __construct(
        private AccessBlockService $accessBlockService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user()?->customer;
        $blockStatus = $this->accessBlockService->invoiceBlockStatus($customer);

        View::share('clientAccessBlock', $blockStatus);

        if ($blockStatus['blocked'] && ! $this->allowsRoute($request)) {
            $invoiceLabel = $blockStatus['invoice_number'] ? "Invoice #{$blockStatus['invoice_number']}" : 'an outstanding invoice';
            $message = "Access is limited because {$invoiceLabel} is overdue. Please pay to restore full access.";

            return redirect()->route('client.dashboard')
                ->with('status', $message);
        }

        return $next($request);
    }

    private function allowsRoute(Request $request): bool
    {
        $route = $request->route();
        if (! $route) {
            return true;
        }

        $routeName = $route->getName();
        if (! $routeName) {
            return true;
        }

        $allowedPatterns = [
            'client.dashboard',
            'client.invoices.*',
            'client.requests.*',
            'client.support-tickets.*',
        ];

        foreach ($allowedPatterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
