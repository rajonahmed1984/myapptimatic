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
                'key' => 'manual_payment_submission',
                'name' => 'Manual Payment Submission',
                'category' => 'Client Messages',
                'subject' => 'Manual payment received for invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nWe received your manual payment submission for invoice {{invoice_number}} ({{payment_amount}}).\nReference: {{payment_reference}}\nPayment method: {{payment_gateway}}\n\nOur team will verify the payment shortly.\n\nThanks,\n{{company_name}}",
            ],
            [
                'key' => 'client_invoice_payment_confirmation',
                'name' => 'Client Invoice Payment Confirmation',
                'category' => 'Client Messages',
                'subject' => 'Payment received for invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nThank you! Invoice {{invoice_number}} is now marked as paid.\nReference: {{payment_reference}}\nTotal: {{invoice_total}}\n\nView invoice: {{payment_url}}\n\nWarm regards,\n{{company_name}}",
            ],
        ];

        foreach ($templates as $template) {
            $exists = DB::table('email_templates')->where('key', $template['key'])->exists();

            if ($exists) {
                continue;
            }

            DB::table('email_templates')->insert([
                'key' => $template['key'],
                'name' => $template['name'],
                'category' => $template['category'],
                'subject' => $template['subject'],
                'body' => $template['body'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')->whereIn('key', [
            'manual_payment_submission',
            'client_invoice_payment_confirmation',
        ])->delete();
    }
};
