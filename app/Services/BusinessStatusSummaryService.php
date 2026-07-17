<?php

namespace App\Services;

use App\Models\CommissionEarning;
use App\Models\ExpenseInvoice;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BusinessStatusSummaryService
{
    public function buildMetricsCached(
        Carbon $startDate,
        Carbon $endDate,
        int $projectionDays,
        $user,
        IncomeEntryService $incomeService,
        ExpenseEntryService $expenseService,
        TaskQueryService $taskQueryService,
        bool $fresh = false
    ): array {
        $ttlSeconds = max(0, (int) config('performance.business_status_cache_seconds', 60));

        if ($fresh || $ttlSeconds === 0) {
            return $this->buildMetrics(
                $startDate,
                $endDate,
                $projectionDays,
                $user,
                $incomeService,
                $expenseService,
                $taskQueryService
            );
        }

        $cacheKey = 'business-status:metrics:v1:'.md5(json_encode([
            'user_id' => $user?->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'projection_days' => $projectionDays,
        ]));

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), fn () => $this->buildMetrics(
            $startDate,
            $endDate,
            $projectionDays,
            $user,
            $incomeService,
            $expenseService,
            $taskQueryService
        ));
    }

    public function buildMetrics(
        Carbon $startDate,
        Carbon $endDate,
        int $projectionDays,
        $user,
        IncomeEntryService $incomeService,
        ExpenseEntryService $expenseService,
        TaskQueryService $taskQueryService
    ): array {
        $taskSummary = $taskQueryService->tasksSummaryForUser($user);

        $projectStatusCounts = Project::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalProjects = (int) $projectStatusCounts->sum();

        $incomeSummary = $incomeService->summary([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'sources' => ['manual', 'system', 'carrothost'],
        ]);
        $expenseSummary = $expenseService->summary([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ]);

        $totalIncome = (float) ($incomeSummary['total'] ?? 0);
        $totalExpense = (float) ($expenseSummary['total'] ?? 0);
        $netProfit = $totalIncome - $totalExpense;

        $receivedIncome = (float) ($incomeSummary['system'] ?? 0);
        $carrotHostIncome = (float) ($incomeSummary['carrothost'] ?? 0);
        $receivedIncome += $carrotHostIncome;

        $payoutExpense = (float) ($expenseSummary['payout'] ?? 0);

        $netCashflow = $receivedIncome - $payoutExpense;

        $taxSummary = Invoice::query()
            ->whereNotNull('tax_amount')
            ->whereDate('issue_date', '>=', $startDate->toDateString())
            ->whereDate('issue_date', '<=', $endDate->toDateString())
            ->selectRaw(
                "COALESCE(SUM(subtotal), 0) as taxable_base,
                COALESCE(SUM(tax_amount), 0) as tax_amount,
                COALESCE(SUM(total), 0) as tax_gross,
                COALESCE(SUM(CASE WHEN tax_mode = 'exclusive' THEN tax_amount ELSE 0 END), 0) as tax_exclusive,
                COALESCE(SUM(CASE WHEN tax_mode = 'inclusive' THEN tax_amount ELSE 0 END), 0) as tax_inclusive"
            )
            ->first();

        $taxableBase = (float) ($taxSummary->taxable_base ?? 0);
        $taxAmount = (float) ($taxSummary->tax_amount ?? 0);
        $taxGross = (float) ($taxSummary->tax_gross ?? 0);
        $taxExclusive = (float) ($taxSummary->tax_exclusive ?? 0);
        $taxInclusive = (float) ($taxSummary->tax_inclusive ?? 0);

        $commissionSummary = CommissionEarning::query()
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status IN ('pending', 'earned', 'payable', 'paid') THEN commission_amount ELSE 0 END), 0) as commission_total,
                COALESCE(SUM(CASE WHEN status = 'payable' THEN commission_amount ELSE 0 END), 0) as commission_payable,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) as commission_paid"
            )
            ->first();
        $commissionTotal = (float) ($commissionSummary->commission_total ?? 0);
        $commissionPayable = (float) ($commissionSummary->commission_payable ?? 0);
        $commissionPaid = (float) ($commissionSummary->commission_paid ?? 0);
        $commissionOutstanding = max(0, $commissionTotal - $commissionPaid);

        $projectionEnd = now()->addDays($projectionDays)->endOfDay();
        $projectionStart = now()->startOfDay();

        $incomeDue = Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $projectionStart->toDateString())
            ->whereDate('due_date', '<=', $projectionEnd->toDateString());

        $incomeDueSummary = $incomeDue
            ->selectRaw('COALESCE(SUM(total), 0) as total_amount, COUNT(*) as total_count')
            ->first();
        $incomeDueTotal = (float) ($incomeDueSummary->total_amount ?? 0);
        $incomeDueCount = (int) ($incomeDueSummary->total_count ?? 0);

        $expenseDue = ExpenseInvoice::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $projectionStart->toDateString())
            ->whereDate('due_date', '<=', $projectionEnd->toDateString())
            ->where('status', '!=', 'paid');

        $expenseDueSummary = $expenseDue
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count')
            ->first();
        $expenseDueTotal = (float) ($expenseDueSummary->total_amount ?? 0);
        $expenseDueCount = (int) ($expenseDueSummary->total_count ?? 0);

        $overdueInvoiceSummary = Invoice::query()
            ->where('status', 'overdue')
            ->selectRaw('COALESCE(SUM(total), 0) as total_amount, COUNT(*) as total_count')
            ->first();
        $overdueInvoiceTotal = (float) ($overdueInvoiceSummary->total_amount ?? 0);
        $overdueInvoiceCount = (int) ($overdueInvoiceSummary->total_count ?? 0);

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'projection_days' => $projectionDays,
            ],
            'currency' => [
                'code' => $currencyCode,
                'symbol' => $currencySymbol,
            ],
            'tasks' => $taskSummary,
            'projects' => [
                'total' => $totalProjects,
                'by_status' => $projectStatusCounts->map(fn ($count) => (int) $count)->toArray(),
            ],
            'finance' => [
                'income_total' => $this->roundMoney($totalIncome),
                'expense_total' => $this->roundMoney($totalExpense),
                'net_profit' => $this->roundMoney($netProfit),
                'received_income' => $this->roundMoney($receivedIncome),
                'payout_expense' => $this->roundMoney($payoutExpense),
                'net_cashflow' => $this->roundMoney($netCashflow),
            ],
            'tax' => [
                'taxable_base' => $this->roundMoney($taxableBase),
                'tax_amount' => $this->roundMoney($taxAmount),
                'tax_gross' => $this->roundMoney($taxGross),
                'tax_exclusive' => $this->roundMoney($taxExclusive),
                'tax_inclusive' => $this->roundMoney($taxInclusive),
            ],
            'commission' => [
                'total_earned' => $this->roundMoney($commissionTotal),
                'payable' => $this->roundMoney($commissionPayable),
                'paid' => $this->roundMoney($commissionPaid),
                'outstanding' => $this->roundMoney($commissionOutstanding),
            ],
            'projections' => [
                'income_due_next_window' => $this->roundMoney($incomeDueTotal),
                'income_due_count' => $incomeDueCount,
                'expense_due_next_window' => $this->roundMoney($expenseDueTotal),
                'expense_due_count' => $expenseDueCount,
                'overdue_invoice_total' => $this->roundMoney($overdueInvoiceTotal),
                'overdue_invoice_count' => $overdueInvoiceCount,
            ],
        ];
    }

    public function buildPrompt(array $metrics): string
    {
        $currency = Arr::get($metrics, 'currency.code', '');
        $start = Arr::get($metrics, 'period.start_date');
        $end = Arr::get($metrics, 'period.end_date');
        $window = Arr::get($metrics, 'period.projection_days');

        $json = json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a finance and operations analyst. Write a concise business status report in Bengali.

Rules:
- Use only the provided metrics. Do not assume or invent data.
- Provide 4 sections with headings: "??????", "?????", "?????", "?????".
- Each section should have 2-4 bullet points.
- Keep it actionable and executive-friendly.
- Refer to all tax-related metrics as VAT; do not use the word "Tax" in the report.
- Mention the reporting period ($start to $end) and the projection window ($window days).
- Use the currency code when presenting amounts: $currency.

Metrics (JSON):
{$json}
PROMPT;
    }

    public function summarize(array $metrics, GeminiService $geminiService): string
    {
        $prompt = $this->buildPrompt($metrics);

        return $geminiService->generateText($prompt);
    }

    public function summarizeDashboard(array $metrics, GeminiService $geminiService): array
    {
        $currency = Arr::get($metrics, 'currency.code', '');
        $start = Arr::get($metrics, 'period.start_date');
        $end = Arr::get($metrics, 'period.end_date');
        $window = Arr::get($metrics, 'period.projection_days');
        $json = json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
You are a finance and operations analyst. Evaluate the business pulse for an admin dashboard.

Rules:
- Use only the provided metrics. Do not invent data.
- Output MUST be strict JSON only. No markdown, no code fences, no extra text.
- JSON keys: verdict, score, confidence, reason, action.
- verdict must be one of: Healthy, Watch, Critical.
- score must be an integer from 0 to 100.
- confidence must be one of: Low, Medium, High.
- reason must be one short Bengali sentence explaining the current status using the metrics.
- action must be one short Bengali sentence explaining the most important next action.
- Refer to all tax-related metrics as VAT; do not use the word "Tax" in reason or action.
- Consider the reporting period ($start to $end), the projection window ($window days), and amounts using currency code $currency where relevant.

Metrics (JSON):
{$json}
PROMPT;

        $raw = $geminiService->generateText($prompt);

        return $this->parseDashboardSummary($raw);
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    private function parseDashboardSummary(string $raw): array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text;
        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $text, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (! is_array($decoded)) {
            $recovered = $this->recoverDashboardSummaryFromText($text);
            if (
                $recovered['verdict'] !== null
                || $recovered['score'] !== null
                || $recovered['confidence'] !== null
                || $recovered['reason'] !== null
                || $recovered['action'] !== null
            ) {
                return $recovered;
            }

            return [
                'verdict' => null,
                'score' => null,
                'confidence' => null,
                'reason' => trim($text) !== '' ? trim($text) : null,
                'action' => null,
            ];
        }

        $verdict = Arr::get($decoded, 'verdict');
        $verdict = in_array($verdict, ['Healthy', 'Watch', 'Critical'], true) ? $verdict : null;

        $confidence = Arr::get($decoded, 'confidence');
        $confidence = in_array($confidence, ['Low', 'Medium', 'High'], true) ? $confidence : null;

        $score = Arr::get($decoded, 'score');
        $score = is_numeric($score) ? max(0, min(100, (int) $score)) : null;

        $reason = Arr::get($decoded, 'reason');
        $action = Arr::get($decoded, 'action');

        return [
            'verdict' => $verdict,
            'score' => $score,
            'confidence' => $confidence,
            'reason' => is_string($reason) && trim($reason) !== '' ? trim($reason) : null,
            'action' => is_string($action) && trim($action) !== '' ? trim($action) : null,
        ];
    }

    private function recoverDashboardSummaryFromText(string $text): array
    {
        $verdict = null;
        $score = null;
        $confidence = null;
        $reason = null;
        $action = null;

        if (preg_match('/"verdict"\s*:\s*"([^"]+)"/u', $text, $matches)) {
            $candidate = trim((string) ($matches[1] ?? ''));
            $verdict = in_array($candidate, ['Healthy', 'Watch', 'Critical'], true) ? $candidate : null;
        }

        if (preg_match('/"score"\s*:\s*(\d{1,3})/u', $text, $matches)) {
            $score = max(0, min(100, (int) ($matches[1] ?? 0)));
        }

        if (preg_match('/"confidence"\s*:\s*"([^"]+)"/u', $text, $matches)) {
            $candidate = trim((string) ($matches[1] ?? ''));
            $confidence = in_array($candidate, ['Low', 'Medium', 'High'], true) ? $candidate : null;
        }

        if (preg_match('/"reason"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $text, $matches)) {
            $candidate = trim((string) stripcslashes((string) ($matches[1] ?? '')));
            $reason = $candidate !== '' ? $candidate : null;
        }

        if (preg_match('/"action"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $text, $matches)) {
            $candidate = trim((string) stripcslashes((string) ($matches[1] ?? '')));
            $action = $candidate !== '' ? $candidate : null;
        }

        return [
            'verdict' => $verdict,
            'score' => $score,
            'confidence' => $confidence,
            'reason' => $reason,
            'action' => $action,
        ];
    }
}
