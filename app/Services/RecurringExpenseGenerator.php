<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\RecurringExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringExpenseGenerator
{
    public function generate(?Carbon $asOfDate = null, ?int $templateId = null): array
    {
        $asOf = ($asOfDate ?? now())->startOfDay();
        $created = 0;
        $processed = 0;

        $query = RecurringExpense::query()
            ->where('status', 'active')
            ->whereNotNull('next_run_date')
            ->whereDate('next_run_date', '<=', $asOf->toDateString())
            ->orderBy('next_run_date');

        if ($templateId) {
            $query->whereKey($templateId);
        }

        $query->chunkById(50, function ($templates) use (&$created, &$processed, $asOf) {
            foreach ($templates as $template) {
                $processed++;
                $created += $this->generateForTemplate($template, $asOf);
            }
        });

        return [
            'processed' => $processed,
            'created' => $created,
        ];
    }

    private function generateForTemplate(RecurringExpense $template, Carbon $asOf): int
    {
        $created = 0;

        DB::transaction(function () use ($template, $asOf, &$created) {
            $template->refresh();

            if ($template->status !== 'active' || ! $template->next_run_date) {
                return;
            }

            $nextRun = Carbon::parse($template->next_run_date)->startOfDay();
            $endDate = $template->end_date ? Carbon::parse($template->end_date)->startOfDay() : null;

            while ($nextRun->lessThanOrEqualTo($asOf)) {
                if ($endDate && $nextRun->greaterThan($endDate)) {
                    $template->update([
                        'status' => 'stopped',
                        'next_run_date' => null,
                    ]);
                    return;
                }

                $exists = Expense::query()
                    ->where('recurring_expense_id', $template->id)
                    ->whereDate('expense_date', $nextRun->toDateString())
                    ->exists();

                if (! $exists) {
                    Expense::create([
                        'category_id' => $template->category_id,
                        'recurring_expense_id' => $template->id,
                        'title' => $template->title,
                        'amount' => $template->amount,
                        'expense_date' => $nextRun->toDateString(),
                        'notes' => $template->notes,
                        'type' => 'recurring',
                        'created_by' => $template->created_by,
                    ]);
                    $created++;
                }

                $nextRun = $this->advanceDate($nextRun, $template->recurrence_type, $template->recurrence_interval);
            }

            $template->update([
                'next_run_date' => $nextRun->toDateString(),
            ]);
        });

        return $created;
    }

    private function advanceDate(Carbon $date, string $type, int $interval): Carbon
    {
        $interval = max(1, $interval);

        if ($type === 'yearly') {
            return $date->copy()->addYearsNoOverflow($interval);
        }

        return $date->copy()->addMonthsNoOverflow($interval);
    }
}
