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

        $template = [
            'key' => 'order_cancelled_notification',
            'name' => 'Order Cancelled Notification',
            'category' => 'Admin Messages',
            'subject' => 'Order {{order_number}} cancelled - {{company_name}}',
            'body' => "Order {{order_number}} was cancelled.\nClient: {{client_name}} ({{client_email}})\nService: {{service_name}}\nOrder total: {{order_total}}\nOrder link: {{order_url}}",
        ];

        $existing = DB::table('email_templates')->where('key', $template['key'])->first();

        if ($existing) {
            $updates = [
                'name' => $template['name'],
                'category' => $template['category'],
            ];

            if (empty(trim((string) $existing->subject))) {
                $updates['subject'] = $template['subject'];
            }

            if (empty(trim((string) $existing->body))) {
                $updates['body'] = $template['body'];
            }

            if (! empty($updates)) {
                $updates['updated_at'] = now();
                DB::table('email_templates')->where('id', $existing->id)->update($updates);
            }

            return;
        }

        DB::table('email_templates')->insert([
            'key' => $template['key'],
            'name' => $template['name'],
            'category' => $template['category'],
            'subject' => $template['subject'],
            'body' => $template['body'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->where('key', 'order_cancelled_notification')
            ->delete();
    }
};
