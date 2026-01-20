<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeWorkSession;
use App\Services\EmployeeWorkSummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkSessionController extends Controller
{
    private const IDLE_CUTOFF_SECONDS = 900;

    public function start(Request $request, EmployeeWorkSummaryService $summaryService): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        if (! $employee instanceof Employee || ! $summaryService->isEligible($employee)) {
            return $this->forbiddenResponse();
        }

        $now = now();
        $session = null;

        DB::transaction(function () use (&$session, $employee, $now) {
            $session = EmployeeWorkSession::query()
                ->where('employee_id', $employee->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->lockForUpdate()
                ->first();

            if ($session && $session->work_date?->toDateString() !== $now->toDateString()) {
                $session->update([
                    'ended_at' => $now,
                    'last_activity_at' => $now,
                ]);
                $session = null;
            }

            if (! $session) {
                $session = EmployeeWorkSession::create([
                    'employee_id' => $employee->id,
                    'work_date' => $now->toDateString(),
                    'started_at' => $now,
                    'last_activity_at' => $now,
                    'active_seconds' => 0,
                ]);
            }
        });

        return response()->json([
            'ok' => true,
            'data' => $this->summaryPayload($employee, $summaryService, $session),
        ]);
    }

    public function ping(Request $request, EmployeeWorkSummaryService $summaryService): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        if (! $employee instanceof Employee || ! $summaryService->isEligible($employee)) {
            return $this->forbiddenResponse();
        }

        $now = now();
        $session = null;

        DB::transaction(function () use ($employee, $now, &$session) {
            $session = EmployeeWorkSession::query()
                ->where('employee_id', $employee->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                return;
            }

            $this->applyActivityDelta($session, $now);
            $session->save();
        });

        if (! $session) {
            return response()->json([
                'ok' => false,
                'message' => 'No active work session. Start a session first.',
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->summaryPayload($employee, $summaryService, $session),
        ]);
    }

    public function stop(Request $request, EmployeeWorkSummaryService $summaryService): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        if (! $employee instanceof Employee || ! $summaryService->isEligible($employee)) {
            return $this->forbiddenResponse();
        }

        $now = now();
        $session = null;

        DB::transaction(function () use ($employee, $now, &$session) {
            $session = EmployeeWorkSession::query()
                ->where('employee_id', $employee->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                return;
            }

            $this->applyActivityDelta($session, $now);
            $session->ended_at = $now;
            $session->last_activity_at = $now;
            $session->save();
        });

        if (! $session) {
            return response()->json([
                'ok' => false,
                'message' => 'No active work session.',
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->summaryPayload($employee, $summaryService),
        ]);
    }

    public function today(Request $request, EmployeeWorkSummaryService $summaryService): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        if (! $employee instanceof Employee || ! $summaryService->isEligible($employee)) {
            return $this->forbiddenResponse();
        }

        return response()->json([
            'ok' => true,
            'data' => $this->summaryPayload($employee, $summaryService),
        ]);
    }

    private function applyActivityDelta(EmployeeWorkSession $session, Carbon $now): void
    {
        $lastActivity = $session->last_activity_at ?? $session->started_at ?? $now;
        $delta = $now->diffInSeconds($lastActivity);

        if ($delta > 0 && $delta < self::IDLE_CUTOFF_SECONDS) {
            $session->active_seconds = (int) ($session->active_seconds ?? 0) + $delta;
        }

        $session->last_activity_at = $now;
    }

    private function summaryPayload(Employee $employee, EmployeeWorkSummaryService $summaryService, ?EmployeeWorkSession $activeSession = null): array
    {
        $now = now();
        $today = $now->toDateString();

        $activeSeconds = (int) EmployeeWorkSession::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->sum('active_seconds');

        $status = 'stopped';
        $isActive = false;

        if (! $activeSession) {
            $activeSession = EmployeeWorkSession::query()
                ->where('employee_id', $employee->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first();
        }

        if ($activeSession && $activeSession->work_date?->toDateString() === $today) {
            $isActive = true;
            $lastActivity = $activeSession->last_activity_at ?? $activeSession->started_at ?? $now;
            $delta = $now->diffInSeconds($lastActivity);

            if ($delta < self::IDLE_CUTOFF_SECONDS) {
                if ($delta > 0) {
                    $activeSeconds += $delta;
                }
                $status = 'working';
            } else {
                $status = 'idle';
            }
        }

        $requiredSeconds = $summaryService->requiredSeconds($employee);
        $salaryEstimate = $summaryService->calculateAmount($employee, $now, $activeSeconds);

        return [
            'active_seconds' => $activeSeconds,
            'required_seconds' => $requiredSeconds,
            'status' => $status,
            'is_active' => $isActive,
            'salary_estimate' => $salaryEstimate,
        ];
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Work sessions are only available for remote part-time or full-time employees.',
        ], 403);
    }
}
