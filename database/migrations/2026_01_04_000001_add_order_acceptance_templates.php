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
                'key' => 'order_accepted_notification',
                'name' => 'Order Accepted Notification',
                'category' => 'Admin Messages',
                'subject' => 'Order accepted #{{order_number}}',
                'body' => "Order {{order_number}} was accepted.\nClient: {{client_name}} ({{client_email}})\nOrder link: {{order_url}}\nApproved by: {{approved_by}}\n\nThank you,\n{{company_name}}",
            ],
            [
                'key' => 'order_accepted_confirmation',
                'name' => 'Order Accepted Confirmation',
                'category' => 'Client Messages',
                'subject' => 'Your order {{order_number}} is accepted',
                'body' => "Hi {{client_name}},\n\nYour order {{order_number}} for {{service_name}} has been accepted.\nLicense key: {{license_key}}\nDomain: {{license_domain}}\nInvoice: {{invoice_number}}\nView invoice: {{invoice_url}}\n\nThank you,\n{{company_name}}",
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
            'order_accepted_notification',
            'order_accepted_confirmation',
        ])->delete();
    }
};
