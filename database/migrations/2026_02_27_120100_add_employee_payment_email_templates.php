<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $timestamp = now();
        $templates = [
            [
                'key' => 'employee_payment_receipt',
                'name' => 'Employee Payment Receipt',
                'category' => 'HR/Payroll Messages',
                'subject' => 'Payment receipt - {{company_name}}',
                'body' => "Dear {{employee_name}},\n\n{{payment_description}}\n\nPayment amount: {{payment_amount}}\nPayment date: {{payment_date}}\nPayment method: {{payment_method}}\nReference: {{payment_reference}}\n\nRegards,\n{{company_name}}",
                'from_email' => 'billing@apptimatic.com',
            ],
            [
                'key' => 'employee_advance_payment_receipt',
                'name' => 'Employee Advance Payment Receipt',
                'category' => 'HR/Payroll Messages',
                'subject' => 'Advance payment receipt - {{company_name}}',
                'body' => "Dear {{employee_name}},\n\n{{payment_description}}\n\nPayment amount: {{payment_amount}}\nPayment date: {{payment_date}}\nPayment method: {{payment_method}}\nReference: {{payment_reference}}\n\nRegards,\n{{company_name}}",
                'from_email' => 'billing@apptimatic.com',
            ],
            [
                'key' => 'employee_final_payslip',
                'name' => 'Employee Final Payslip',
                'category' => 'HR/Payroll Messages',
                'subject' => 'Final payslip - {{company_name}}',
                'body' => "Dear {{employee_name}},\n\n{{payment_description}}\n\nPayment amount: {{payment_amount}}\nPayment date: {{payment_date}}\nPayment method: {{payment_method}}\nReference: {{payment_reference}}\n\nYour final payslip PDF is attached.\n\nRegards,\n{{company_name}}",
                'from_email' => 'billing@apptimatic.com',
            ],
            [
                'key' => 'payroll_payment_receipt',
                'name' => 'Payroll Payment Receipt',
                'category' => 'HR/Payroll Messages',
                'subject' => 'Payroll payment receipt - {{company_name}}',
                'body' => "Dear {{employee_name}},\n\n{{payment_description}}\n\nPayroll period: {{payroll_period}}\nPayment amount: {{payment_amount}}\nPayment date: {{payment_date}}\nPayment method: {{payment_method}}\nReference: {{payment_reference}}\n\nRegards,\n{{company_name}}",
                'from_email' => 'billing@apptimatic.com',
            ],
            [
                'key' => 'payroll_final_payslip',
                'name' => 'Payroll Final Payslip',
                'category' => 'HR/Payroll Messages',
                'subject' => 'Final payroll payslip - {{company_name}}',
                'body' => "Dear {{employee_name}},\n\n{{payment_description}}\n\nPayroll period: {{payroll_period}}\nPayment amount: {{payment_amount}}\nPayment date: {{payment_date}}\nPayment method: {{payment_method}}\nReference: {{payment_reference}}\n\nYour final payslip PDF is attached.\n\nRegards,\n{{company_name}}",
                'from_email' => 'billing@apptimatic.com',
            ],
        ];

        foreach ($templates as $template) {
            $existing = DB::table('email_templates')->where('key', $template['key'])->first();

            if ($existing) {
                $updates = [
                    'name' => $template['name'],
                    'category' => $template['category'],
                    'updated_at' => $timestamp,
                ];

                if (Schema::hasColumn('email_templates', 'from_email')) {
                    $updates['from_email'] = $template['from_email'];
                }

                if (trim((string) ($existing->subject ?? '')) === '') {
                    $updates['subject'] = $template['subject'];
                }
                if (trim((string) ($existing->body ?? '')) === '') {
                    $updates['body'] = $template['body'];
                }

                DB::table('email_templates')->where('id', $existing->id)->update($updates);
                continue;
            }

            $payload = [
                'key' => $template['key'],
                'name' => $template['name'],
                'category' => $template['category'],
                'subject' => $template['subject'],
                'body' => $template['body'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if (Schema::hasColumn('email_templates', 'from_email')) {
                $payload['from_email'] = $template['from_email'];
            }

            DB::table('email_templates')->insert($payload);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->whereIn('key', [
                'employee_payment_receipt',
                'employee_advance_payment_receipt',
                'employee_final_payslip',
                'payroll_payment_receipt',
                'payroll_final_payslip',
            ])
            ->delete();
    }
};
