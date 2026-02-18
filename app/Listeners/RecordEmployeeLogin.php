<?php

namespace App\Listeners;

use App\Models\Employee;
use App\Models\EmployeeActivityDaily;
use App\Models\EmployeeSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class RecordEmployeeLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'employee') {
            return;
        }

        $employee = Employee::query()
            ->where('user_id', $event->user->id)
            ->first();

        if (! $employee) {
            return;
        }
        
        if (! $this->isSessionEligible($employee)) {
            return;
        }

        $now = now();
        $sessionId = session()->getId();
        $request = request();

        DB::transaction(function () use ($employee, $now, $sessionId, $request) {
            $sessionRecord = EmployeeSession::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'session_id' => $sessionId,
                ],
                [
                    'login_at' => $now,
                    'last_seen_at' => $now,
                    'active_seconds' => 0,
                    'ip_address' => $request?->ip(),
                    'user_agent' => $request?->userAgent(),
                ]
            );

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
                    'first_login_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }

            if ($daily->wasRecentlyCreated && $daily->first_login_at === null) {
                $daily->first_login_at = $now;
            }

            if ($sessionRecord->wasRecentlyCreated) {
                $daily->increment('sessions_count');
            }
            $daily->update([
                'last_seen_at' => $now,
                'first_login_at' => $daily->first_login_at ?? $now,
            ]);
        });
    }

    private function isSessionEligible(Employee $employee): bool
    {
        return in_array($employee->employment_type, ['full_time', 'part_time'], true)
            && $employee->work_mode === 'remote';
    }
}
