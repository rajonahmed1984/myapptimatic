<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\EmployeeActivityDaily;
use App\Models\EmployeeSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackEmployeeActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->track($request);

        return $next($request);
    }

    protected function track(Request $request): void
    {
        $user = Auth::guard('employee')->user();

        if (! $user) {
            return;
        }

        /** @var Employee|null $employee */
        $employee = $request->attributes->get('employee') ?? Employee::query()->where('user_id', $user->id)->first();

        if (! $employee) {
            return;
        }

        $now = now();
        $sessionId = $request->session()->getId();

        $lastUpdate = $request->session()->get('employee_activity_last_update');
        if ($lastUpdate && $lastUpdate->diffInSeconds($now) < 60) {
            return;
        }

        DB::transaction(function () use ($employee, $sessionId, $now, $request) {
            $session = EmployeeSession::query()
                ->where('employee_id', $employee->id)
                ->where('session_id', $sessionId)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                $session = EmployeeSession::query()
                    ->where('employee_id', $employee->id)
                    ->whereNull('logout_at')
                    ->latest('login_at')
                    ->lockForUpdate()
                    ->first();

                if ($session) {
                    $session->update(['session_id' => $sessionId]);
                }
            }

            if (! $session) {
                $session = EmployeeSession::create([
                    'employee_id' => $employee->id,
                    'session_id' => $sessionId,
                    'login_at' => $now,
                    'last_seen_at' => $now,
                    'active_seconds' => 0,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            $daily = EmployeeActivityDaily::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $now->toDateString())
                ->first();

            if (! $daily) {
                $daily = EmployeeActivityDaily::create([
                    'employee_id' => $employee->id,
                    'date' => $now->toDateString(),
                    'sessions_count' => 0,
                    'active_seconds' => 0,
                    'first_login_at' => $session->login_at ?? $now,
                    'last_seen_at' => $now,
                ]);
            }

            $lastSeen = $session->last_seen_at ?? $session->login_at ?? $now;
            $delta = $lastSeen->diffInSeconds($now);

            if ($delta > 0 && $delta <= 300) {
                $session->active_seconds = ($session->active_seconds ?? 0) + $delta;
                $daily->active_seconds = ($daily->active_seconds ?? 0) + $delta;
            }

            $session->last_seen_at = $now;
            $session->save();

            $daily->last_seen_at = $now;
            $daily->first_login_at = $daily->first_login_at ?? $session->login_at ?? $now;
            $daily->save();
        });

        $request->session()->put('employee_activity_last_update', $now);
    }
}
