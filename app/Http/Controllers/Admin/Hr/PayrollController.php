<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(): View
    {
        $periods = PayrollPeriod::query()
            ->withCount('payrollItems as items_count')
            ->orderByDesc('start_date')
            ->paginate(20);

        return view('admin.hr.payroll.index', compact('periods'));
    }

    public function generate(Request $request, PayrollService $service): RedirectResponse
    {
        $data = $request->validate([
            'period_key' => ['required', 'regex:/^\\d{4}-\\d{2}$/'],
        ]);

        $service->generatePeriod($data['period_key']);

        return back()->with('status', 'Payroll generated for '.$data['period_key']);
    }

    public function finalize(PayrollPeriod $payrollPeriod, PayrollService $service): RedirectResponse
    {
        $service->finalizePeriod($payrollPeriod);

        return back()->with('status', 'Payroll period finalized.');
    }

    public function export(PayrollPeriod $payrollPeriod): StreamedResponse
    {
        $filename = 'payroll-'.$payrollPeriod->period_key.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($payrollPeriod) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee',
                'Status',
                'Pay Type',
                'Currency',
                'Base Pay',
                'Timesheet Hours',
                'Overtime Hours',
                'Overtime Rate',
                'Bonuses',
                'Penalties',
                'Advances',
                'Deductions',
                'Gross',
                'Net',
                'Payment Reference',
                'Paid At',
            ]);

            $payrollPeriod->payrollItems()
                ->with('employee')
                ->chunk(200, function ($items) use ($handle) {
                    foreach ($items as $item) {
                        fputcsv($handle, [
                            $item->employee?->name ?? 'N/A',
                            $item->status,
                            $item->pay_type,
                            $item->currency,
                            $item->base_pay,
                            $item->timesheet_hours,
                            $item->overtime_hours,
                            $item->overtime_rate,
                            $item->bonuses ? json_encode($item->bonuses) : '',
                            $item->penalties ? json_encode($item->penalties) : '',
                            $item->advances ? json_encode($item->advances) : '',
                            $item->deductions ? json_encode($item->deductions) : '',
                            $item->gross_pay,
                            $item->net_pay,
                            $item->payment_reference,
                            optional($item->paid_at)->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, $headers);
    }
}
