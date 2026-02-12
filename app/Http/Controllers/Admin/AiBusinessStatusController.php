<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BusinessStatusSummaryService;
use App\Services\ExpenseEntryService;
use App\Services\GeminiService;
use App\Services\IncomeEntryService;
use App\Services\TaskQueryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiBusinessStatusController extends Controller
{
    public function index(
        Request $request,
        BusinessStatusSummaryService $summaryService,
        IncomeEntryService $incomeService,
        ExpenseEntryService $expenseService,
        TaskQueryService $taskQueryService
    ): View {
        [$startDate, $endDate, $projectionDays] = $this->resolvePeriod($request);

        $metrics = $summaryService->buildMetrics(
            $startDate,
            $endDate,
            $projectionDays,
            $request->user(),
            $incomeService,
            $expenseService,
            $taskQueryService
        );

        return view('admin.ai.business-status', [
            'metrics' => $metrics,
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'projection_days' => $projectionDays,
            ],
            'aiReady' => (bool) config('google_ai.api_key'),
        ]);
    }

    public function generate(
        Request $request,
        BusinessStatusSummaryService $summaryService,
        IncomeEntryService $incomeService,
        ExpenseEntryService $expenseService,
        TaskQueryService $taskQueryService,
        GeminiService $geminiService
    ): JsonResponse {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'projection_days' => ['nullable', 'integer', 'min:7', 'max:120'],
        ]);

        $startDate = ! empty($data['start_date'])
            ? Carbon::parse($data['start_date'])->startOfDay()
            : now()->subDays(30)->startOfDay();
        $endDate = ! empty($data['end_date'])
            ? Carbon::parse($data['end_date'])->endOfDay()
            : now()->endOfDay();
        $projectionDays = (int) ($data['projection_days'] ?? 30);

        try {
            $metrics = $summaryService->buildMetrics(
                $startDate,
                $endDate,
                $projectionDays,
                $request->user(),
                $incomeService,
                $expenseService,
                $taskQueryService
            );

            $summary = $summaryService->summarize($metrics, $geminiService);

            return response()->json([
                'summary' => $summary,
                'metrics' => $metrics,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function resolvePeriod(Request $request): array
    {
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : now()->subDays(30)->startOfDay();
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : now()->endOfDay();
        $projectionDays = (int) ($request->query('projection_days', 30));

        if ($projectionDays < 7) {
            $projectionDays = 7;
        } elseif ($projectionDays > 120) {
            $projectionDays = 120;
        }

        return [$startDate, $endDate, $projectionDays];
    }
}
