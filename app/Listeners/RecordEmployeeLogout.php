<?php

namespace App\Listeners;

use App\Models\Employee;
use App\Models\EmployeeSession;
use Illuminate\Auth\Events\Logout;

class RecordEmployeeLogout
{
    public function handle(Logout $event): void
    {
        if ($event->guard !== 'employee') {
            return;
        }

        if (! $event->user) {
            return;
        }

        $employee = Employee::query()
            ->where('user_id', $event->user->id)
            ->first();

        if (! $employee) {
            return;
        }

        $sessionId = session()->getId();
        $now = now();

        EmployeeSession::query()
            ->where('employee_id', $employee->id)
            ->where('session_id', $sessionId)
            ->whereNull('logout_at')
            ->update([
                'logout_at' => $now,
            ]);
    }
}
