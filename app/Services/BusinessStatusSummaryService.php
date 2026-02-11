<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\CommissionEarning;
use App\Models\ExpenseInvoice;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BusinessStatusSummaryService
{
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

        $incomeEntries = $incomeService->entries([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'sources' => ['manual', 'system'],
        ]);
        $expenseEntries = $expenseService->entries([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ]);

        $totalIncome = (float) $incomeEntries->sum('amount');
        $totalExpense = (float) $expenseEntries->sum('amount');
        $netProfit = $totalIncome - $totalExpense;

        $receivedIncome = (float) AccountingEntry::query()
            ->where('type', 'payment')
            ->whereDate('entry_date', '>=', $startDate->toDateString())
            ->whereDate('entry_date', '<=', $endDate->toDateString())
            ->sum('amount');

        $payoutExpense = (float) $expenseEntries
            ->whereIn('expense_type', ['salary', 'contract_payout', 'sales_payout'])
            ->sum('amount');

        $netCashflow = $receivedIncome - $payoutExpense;

        $taxRows = Invoice::query()
            ->whereNotNull('tax_amount')
            ->whereDate('issue_date', '>=', $startDate->toDateString())
            ->whereDate('issue_date', '<=', $endDate->toDateString())
            ->get(['subtotal', 'tax_amount', 'total', 'tax_mode']);

        $taxableBase = (float) $taxRows->sum('subtotal');
        $taxAmount = (float) $taxRows->sum('tax_amount');
        $taxGross = (float) $taxRows->sum('total');
        $taxExclusive = (float) $taxRows->where('tax_mode', 'exclusive')->sum('tax_amount');
        $taxInclusive = (float) $taxRows->where('tax_mode', 'inclusive')->sum('tax_amount');

        $commissionBase = CommissionEarning::query();
        $commissionTotal = (float) (clone $commissionBase)
            ->whereIn('status', ['pending', 'earned', 'payable', 'paid'])
            ->sum('commission_amount');
        $commissionPayable = (float) (clone $commissionBase)
            ->where('status', 'payable')
            ->sum('commission_amount');
        $commissionPaid = (float) (clone $commissionBase)
            ->where('status', 'paid')
            ->sum('commission_amount');
        $commissionOutstanding = max(0, $commissionTotal - $commissionPaid);

        $projectionEnd = now()->addDays($projectionDays)->endOfDay();
        $projectionStart = now()->startOfDay();

        $incomeDue = Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $projectionStart->toDateString())
            ->whereDate('due_date', '<=', $projectionEnd->toDateString());

        $incomeDueTotal = (float) $incomeDue->sum('total');
        $incomeDueCount = (int) $incomeDue->count();

        $expenseDue = ExpenseInvoice::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $projectionStart->toDateString())
            ->whereDate('due_date', '<=', $projectionEnd->toDateString())
            ->where('status', '!=', 'paid');

        $expenseDueTotal = (float) $expenseDue->sum('amount');
        $expenseDueCount = (int) $expenseDue->count();

        $overdueInvoiceTotal = (float) Invoice::query()
            ->where('status', 'overdue')
            ->sum('total');
        $overdueInvoiceCount = (int) Invoice::query()
            ->where('status', 'overdue')
            ->count();

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

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }
}