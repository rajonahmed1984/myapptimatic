<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('employee')->user();

        if (! $user) {
            return redirect()->route('employee.login');
        }

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $employee) {
            Auth::guard('employee')->logout();
            abort(403, 'Employee access is restricted.');
        }

        // Share the resolved employee on the request for downstream consumers.
        $request->attributes->set('employee', $employee);

        return $next($request);
    }
}
