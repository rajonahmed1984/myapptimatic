<?php

namespace App\Services;

use App\Enums\MailCategory;
use App\Models\EmailTemplate;
use App\Models\EmployeePayout;
use App\Models\PayrollAuditLog;
use App\Models\Project;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\UrlResolver;
use App\Services\Mail\MailSender;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EmployeePaymentNotificationService
{
    public function __construct(
        private readonly MailSender $mailSender
    ) {
    }

    public function sendEmployeePayoutReceipt(int $employeePayoutId): void
    {
        $payout = EmployeePayout::query()
            ->with(['employee:id,name,email'])
            ->find($employeePayoutId);

        if (! $payout || ! $payout->employee || ! $payout->employee->email) {
            return;
        }

        $metadata = is_array($payout->metadata) ? $payout->metadata : [];
        if (! empty($metadata['receipt_mail_sent_at'])) {
            return;
        }

        $employee = $payout->employee;
        $companyName = Setting::getValue('company_name', config('app.name'));
        $dateFormat = config('app.date_format', 'd-m-Y');
        $paidAtDisplay = $payout->paid_at?->format($dateFormat) ?? now()->format($dateFormat);
        $isAdvance = ($metadata['type'] ?? null) === 'advance';
        $isFinal = false;

        if (! $isAdvance) {
            $payableRaw = (float) Project::query()
                ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
                ->whereNotNull('contract_employee_payable')
                ->where('contract_employee_payable', '>', 0)
                ->sum('contract_employee_payable');
            $paidTotal = (float) EmployeePayout::query()
                ->where('employee_id', $employee->id)
                ->sum('amount');
            $isFinal = max(0, $payableRaw - $paidTotal) <= 0.009;
        }

        $templateKey = $isAdvance
            ? 'employee_advance_payment_receipt'
            : ($isFinal ? 'employee_final_payslip' : 'employee_payment_receipt');
        $template = EmailTemplate::query()->where('key', $templateKey)->first();

        $subjectFallback = $isAdvance
            ? 'Advance payment received - {{company_name}}'
            : ($isFinal ? 'Final payslip - {{company_name}}' : 'Payment receipt - {{company_name}}');
        $descriptionFallback = $isAdvance
            ? 'An advance payment has been processed for you.'
            : ($isFinal ? 'Your final project payout has been completed.' : 'A payment has been processed for you.');
        $reference = trim((string) ($payout->reference ?? ''));

        $replacements = [
            '{{employee_name}}' => (string) $employee->name,
            '{{company_name}}' => (string) $companyName,
            '{{payment_amount}}' => (string) ($payout->currency.' '.number_format((float) $payout->amount, 2)),
            '{{payment_date}}' => (string) $paidAtDisplay,
            '{{payment_method}}' => strtoupper((string) ($payout->payout_method ?? 'N/A')),
            '{{payment_reference}}' => $reference !== '' ? $reference : '--',
            '{{payment_description}}' => $descriptionFallback,
        ];

        $subject = $this->applyReplacements((string) ($template?->subject ?: $subjectFallback), $replacements);
        $bodyRaw = (string) ($template?->body ?: $this->defaultBody($descriptionFallback));
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($bodyRaw, $replacements));

        $attachments = [];
        if (! $isAdvance && $isFinal) {
            $pdf = $this->buildPaymentSlipPdf([
                'slip_title' => 'Final Payslip',
                'employee_name' => (string) $employee->name,
                'employee_email' => (string) $employee->email,
                'payment_type' => 'Project Payout',
                'payment_date' => $paidAtDisplay,
                'payment_amount' => (string) ($payout->currency.' '.number_format((float) $payout->amount, 2)),
                'payment_method' => strtoupper((string) ($payout->payout_method ?? 'N/A')),
                'reference' => $reference !== '' ? $reference : '--',
                'note' => (string) ($payout->note ?: '--'),
                'company_name' => (string) $companyName,
                'generated_at' => now()->format(config('app.datetime_format', 'd-m-Y h:i A')),
            ]);

            $attachments[] = [
                'data' => $pdf,
                'filename' => 'final-payslip-'.$employee->id.'-'.$payout->id.'.pdf',
                'mimetype' => 'application/pdf',
            ];
        }

        $this->sendEmail(
            $employee->email,
            $subject,
            $bodyHtml,
            $companyName,
            $attachments
        );

        $metadata['receipt_mail_sent_at'] = now()->toDateTimeString();
        $payout->forceFill(['metadata' => $metadata])->save();
    }

    public function sendPayrollPaymentReceipt(int $payrollAuditLogId): void
    {
        $log = PayrollAuditLog::query()
            ->with(['payrollItem.employee:id,name,email', 'payrollItem.period:id,period_key'])
            ->find($payrollAuditLogId);

        if (! $log || ! $log->payrollItem || ! $log->payrollItem->employee || ! $log->payrollItem->employee->email) {
            return;
        }

        $meta = is_array($log->meta) ? $log->meta : [];
        if (! empty($meta['receipt_mail_sent_at'])) {
            return;
        }

        $employee = $log->payrollItem->employee;
        $payrollItem = $log->payrollItem;
        $companyName = Setting::getValue('company_name', config('app.name'));
        $dateFormat = config('app.date_format', 'd-m-Y');
        $paymentDate = ! empty($meta['paid_at']) ? (string) $meta['paid_at'] : now()->format('Y-m-d');
        $paidAtDisplay = date($this->phpDateFormat($dateFormat), strtotime($paymentDate));
        $periodLabel = (string) ($payrollItem->period?->period_key ?? '--');
        $paymentAmount = (float) ($meta['amount'] ?? 0);
        $remainingAfter = (float) ($meta['remaining_after'] ?? 0);
        $isFinal = $log->event === 'payment_completed' || $remainingAfter <= 0.009;
        $reference = trim((string) ($meta['reference'] ?? $payrollItem->payment_reference ?? ''));

        $templateKey = $isFinal ? 'payroll_final_payslip' : 'payroll_payment_receipt';
        $template = EmailTemplate::query()->where('key', $templateKey)->first();

        $subjectFallback = $isFinal
            ? 'Final payroll payslip - {{company_name}}'
            : 'Payroll payment receipt - {{company_name}}';
        $descriptionFallback = $isFinal
            ? 'Your payroll payment has been completed for the period {{payroll_period}}.'
            : 'A payroll payment has been processed for the period {{payroll_period}}.';

        $replacements = [
            '{{employee_name}}' => (string) $employee->name,
            '{{company_name}}' => (string) $companyName,
            '{{payroll_period}}' => $periodLabel,
            '{{payment_amount}}' => (string) ($payrollItem->currency.' '.number_format($paymentAmount, 2)),
            '{{payment_date}}' => (string) $paidAtDisplay,
            '{{payment_method}}' => (string) $this->methodFromReference($reference),
            '{{payment_reference}}' => $reference !== '' ? $reference : '--',
            '{{payment_description}}' => str_replace('{{payroll_period}}', $periodLabel, $descriptionFallback),
        ];

        $subject = $this->applyReplacements((string) ($template?->subject ?: $subjectFallback), $replacements);
        $bodyRaw = (string) ($template?->body ?: $this->defaultBody($descriptionFallback));
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($bodyRaw, $replacements));

        $attachments = [];
        if ($isFinal) {
            $pdf = $this->buildPaymentSlipPdf([
                'slip_title' => 'Payroll Payslip',
                'employee_name' => (string) $employee->name,
                'employee_email' => (string) $employee->email,
                'payment_type' => 'Payroll',
                'payment_date' => $paidAtDisplay,
                'payment_amount' => (string) ($payrollItem->currency.' '.number_format($paymentAmount, 2)),
                'payment_method' => (string) $this->methodFromReference($reference),
                'reference' => $reference !== '' ? $reference : '--',
                'note' => 'Payroll Period: '.$periodLabel,
                'company_name' => (string) $companyName,
                'generated_at' => now()->format(config('app.datetime_format', 'd-m-Y h:i A')),
            ]);

            $attachments[] = [
                'data' => $pdf,
                'filename' => 'payroll-payslip-'.$employee->id.'-'.$payrollItem->id.'.pdf',
                'mimetype' => 'application/pdf',
            ];
        }

        $this->sendEmail(
            $employee->email,
            $subject,
            $bodyHtml,
            $companyName,
            $attachments
        );

        $meta['receipt_mail_sent_at'] = now()->toDateTimeString();
        $log->forceFill(['meta' => $meta])->save();
    }

    private function sendEmail(
        string $to,
        string $subject,
        string $bodyHtml,
        string $companyName,
        array $attachments = []
    ): void {
        $this->mailSender->sendView(
            MailCategory::BILLING,
            $to,
            'emails.generic',
            [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => Branding::url(Setting::getValue('company_logo_path')),
                'portalUrl' => UrlResolver::portalUrl(),
                'portalLoginUrl' => UrlResolver::portalUrl().'/employee/login',
                'portalLoginLabel' => 'log in to the employee area',
                'bodyHtml' => new HtmlString($bodyHtml),
            ],
            $subject,
            $attachments
        );
    }

    private function defaultBody(string $description): string
    {
        return "Dear {{employee_name}},\n\n{{payment_description}}\n\n"
            ."Payment amount: {{payment_amount}}\n"
            ."Payment date: {{payment_date}}\n"
            ."Payment method: {{payment_method}}\n"
            ."Reference: {{payment_reference}}\n\n"
            ."If you need clarification, please contact Accounts.\n\n"
            ."Regards,\n{{company_name}}";
    }

    private function methodFromReference(string $reference): string
    {
        if ($reference === '') {
            return 'N/A';
        }

        $parts = explode('-', $reference, 2);
        return strtoupper(trim($parts[0] ?? $reference));
    }

    private function buildPaymentSlipPdf(array $payload): string
    {
        $html = view('hr.payslips.payment-slip', $payload)->render();
        return app('dompdf.wrapper')->loadHTML($html)->output();
    }

    private function applyReplacements(string $text, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function formatEmailBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $looksLikeHtml = Str::contains($trimmed, ['<p', '<br', '<div', '<table', '<a ', '<strong', '<em', '<ul', '<ol', '<li']);
        if ($looksLikeHtml) {
            return $trimmed;
        }

        return nl2br(e($trimmed));
    }

    private function phpDateFormat(string $format): string
    {
        return strtr($format, [
            'd' => 'd',
            'm' => 'm',
            'Y' => 'Y',
        ]);
    }
}
