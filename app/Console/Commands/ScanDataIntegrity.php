<?php

namespace App\Console\Commands;

use App\Models\AccountingEntry;
use App\Models\CommissionPayout;
use App\Models\EmployeePayout;
use App\Models\Expense;
use App\Models\ExpenseInvoice;
use App\Models\PayrollItem;
use App\Models\ProjectMessage;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMessage;
use App\Models\ProjectTaskSubtask;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ScanDataIntegrity extends Command
{
    protected $signature = 'diagnostics:integrity {--limit=10}';
    protected $description = 'Scan for common relational data integrity issues.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Integrity diagnostics are disabled in production.');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));

        $rows = [];

        $rows[] = $this->summarize('Tasks without project', ProjectTask::query()->whereDoesntHave('project'), $limit);
        $rows[] = $this->summarize('Subtasks without task', ProjectTaskSubtask::query()->whereDoesntHave('task'), $limit);
        $rows[] = $this->summarize('Project messages without project', ProjectMessage::query()->whereDoesntHave('project'), $limit);
        $rows[] = $this->summarize('Task messages without task', ProjectTaskMessage::query()->whereDoesntHave('task'), $limit);

        $rows[] = $this->summarize('Invoices without customer', Invoice::query()->whereNull('customer_id'), $limit);
        $rows[] = $this->summarize('Invoices with missing project', Invoice::query()->whereNotNull('project_id')->whereDoesntHave('project'), $limit);
        $rows[] = $this->summarize('Invoices with missing customer record', Invoice::query()->whereNotNull('customer_id')->whereDoesntHave('customer'), $limit);

        $rows[] = $this->summarize('Payroll items without employee', PayrollItem::query()->whereDoesntHave('employee'), $limit);
        $rows[] = $this->summarize('Payroll items without period', PayrollItem::query()->whereDoesntHave('period'), $limit);

        $rows[] = $this->summarize('Commission payouts without sales rep', CommissionPayout::query()->whereDoesntHave('salesRep'), $limit);
        $rows[] = $this->summarize('Commission payouts without earnings', CommissionPayout::query()->whereDoesntHave('earnings'), $limit);

        $rows[] = $this->summarize('Employee payouts without employee', EmployeePayout::query()->whereDoesntHave('employee'), $limit);

        $rows[] = $this->summarize('Accounting entries missing references', AccountingEntry::query()
            ->whereNull('reference')
            ->whereNull('customer_id')
            ->whereNull('invoice_id')
            ->whereNull('payment_gateway_id'), $limit);

        $rows[] = $this->summarize('Expense invoices with invalid source type', ExpenseInvoice::query()
            ->whereNotIn('source_type', ['expense', 'payroll_item', 'employee_payout', 'commission_payout']), $limit);

        $rows[] = $this->summarize('Expense invoices missing expense source', ExpenseInvoice::query()
            ->where('source_type', 'expense')
            ->whereNotIn('source_id', Expense::query()->select('id')), $limit);

        $rows[] = $this->summarize('Expense invoices missing payroll item', ExpenseInvoice::query()
            ->where('source_type', 'payroll_item')
            ->whereNotIn('source_id', PayrollItem::query()->select('id')), $limit);

        $rows[] = $this->summarize('Expense invoices missing employee payout', ExpenseInvoice::query()
            ->where('source_type', 'employee_payout')
            ->whereNotIn('source_id', EmployeePayout::query()->select('id')), $limit);

        $rows[] = $this->summarize('Expense invoices missing commission payout', ExpenseInvoice::query()
            ->where('source_type', 'commission_payout')
            ->whereNotIn('source_id', CommissionPayout::query()->select('id')), $limit);

        $this->table(['Check', 'Count', 'Sample IDs'], $rows);

        return self::SUCCESS;
    }

    private function summarize(string $label, Builder $query, int $limit): array
    {
        $count = (clone $query)->count();
        $sample = (clone $query)->limit($limit)->pluck('id')->map(fn ($id) => (string) $id)->implode(', ');

        return [$label, $count, $sample ?: '--'];
    }
}
