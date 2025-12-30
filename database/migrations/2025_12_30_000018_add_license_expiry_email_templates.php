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

        $templates = [
            [
                'key' => 'license_expiry_notice',
                'name' => 'License Expiry Notice',
                'category' => 'Product/Service Messages',
                'subject' => 'License expiring soon - {{product_name}}',
                'body' => "Hi {{client_name}},\n\nYour license for {{product_name}} will expire on {{license_expires_at}}.\nLicense key: {{license_key}}\n\nPlease renew your license to avoid service interruption.\n\nThank you,\n{{company_name}}",
            ],
            [
                'key' => 'license_expired_notice',
                'name' => 'License Expired Notice',
                'category' => 'Product/Service Messages',
                'subject' => 'License expired - {{product_name}}',
                'body' => "Hi {{client_name}},\n\nYour license for {{product_name}} has expired.\nLicense key: {{license_key}}\n\nPlease renew your license to restore service.\n\nThank you,\n{{company_name}}",
            ],
        ];

        foreach ($templates as $template) {
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

                continue;
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->whereIn('key', ['license_expiry_notice', 'license_expired_notice'])
            ->delete();
    }
};
