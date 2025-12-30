<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        if (! Schema::hasColumn('email_templates', 'category')) {
            Schema::table('email_templates', function (Blueprint $table) {
                $table->string('category')->nullable()->after('name');
            });
        }

        $timestamp = now();

        $templates = [
            [
                'key' => 'client_signup_email',
                'name' => 'Client Signup Email',
                'category' => 'General Messages',
                'subject' => 'Welcome to {{company_name}}',
                'body' => "Hi {{client_name}},\n\nYour account for {{company_name}} is ready.\nLogin: {{login_url}}\nEmail: {{client_email}}\n\nThank you,\n{{company_name}}",
            ],
            [
                'key' => 'order_confirmation',
                'name' => 'Order Confirmation',
                'category' => 'General Messages',
                'subject' => 'Order {{order_number}} confirmed - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nWe received your order for {{service_name}}.\nOrder number: {{order_number}}\nOrder total: {{order_total}}\n\nThank you,\n{{company_name}}",
            ],
            [
                'key' => 'email_address_verification',
                'name' => 'Email Address Verification',
                'category' => 'User Messages',
                'subject' => 'Verify your email - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nPlease verify your email address by clicking this link:\n{{verification_url}}\n\nThanks,\n{{company_name}}",
            ],
            [
                'key' => 'password_reset_confirmation',
                'name' => 'Password Reset Confirmation',
                'category' => 'User Messages',
                'subject' => 'Your password was reset - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nThis is a confirmation that your password was reset.\n\nIf this was not you, contact support.",
            ],
            [
                'key' => 'password_reset_validation',
                'name' => 'Password Reset Validation',
                'category' => 'User Messages',
                'subject' => 'Reset your password - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nUse this link to reset your password:\n{{reset_url}}\n\nThis link will expire soon.",
            ],
            [
                'key' => 'invoice_created',
                'name' => 'Invoice Created',
                'category' => 'Invoice Messages',
                'subject' => 'Invoice {{invoice_number}} created - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nA new invoice has been created.\nInvoice: {{invoice_number}}\nTotal: {{invoice_total}}\nDue date: {{invoice_due_date}}\n\nPay here: {{payment_url}}",
            ],
            [
                'key' => 'invoice_payment_reminder',
                'name' => 'Invoice Payment Reminder',
                'category' => 'Invoice Messages',
                'subject' => 'Reminder: invoice {{invoice_number}} due {{invoice_due_date}}',
                'body' => "Hi {{client_name}},\n\nThis is a reminder that invoice {{invoice_number}} is due on {{invoice_due_date}}.\nTotal: {{invoice_total}}\n\nPay here: {{payment_url}}",
            ],
            [
                'key' => 'invoice_overdue_first_notice',
                'name' => 'First Invoice Overdue Notice',
                'category' => 'Invoice Messages',
                'subject' => 'Overdue notice: invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nInvoice {{invoice_number}} is now overdue.\n\nPay here: {{payment_url}}",
            ],
            [
                'key' => 'invoice_overdue_second_notice',
                'name' => 'Second Invoice Overdue Notice',
                'category' => 'Invoice Messages',
                'subject' => 'Second overdue notice: invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nThis is a second reminder that invoice {{invoice_number}} is overdue.\n\nPay here: {{payment_url}}",
            ],
            [
                'key' => 'invoice_overdue_third_notice',
                'name' => 'Third Invoice Overdue Notice',
                'category' => 'Invoice Messages',
                'subject' => 'Final overdue notice: invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nThis is the final reminder that invoice {{invoice_number}} is overdue.\n\nPay here: {{payment_url}}",
            ],
            [
                'key' => 'invoice_payment_confirmation',
                'name' => 'Invoice Payment Confirmation',
                'category' => 'Invoice Messages',
                'subject' => 'Payment received for invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nWe received your payment for invoice {{invoice_number}}.\n\nThank you,\n{{company_name}}",
            ],
            [
                'key' => 'invoice_refund_confirmation',
                'name' => 'Invoice Refund Confirmation',
                'category' => 'Invoice Messages',
                'subject' => 'Refund issued for invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nA refund was issued for invoice {{invoice_number}}.\nRefund amount: {{refund_amount}}\n\nIf you have questions, contact support.",
            ],
            [
                'key' => 'support_ticket_auto_close_notification',
                'name' => 'Support Ticket Auto Close Notification',
                'category' => 'Support Messages',
                'subject' => 'Ticket auto-closed #{{ticket_id}}',
                'body' => "Your ticket {{ticket_id}} was closed due to inactivity.\n\nIf you still need help, reply to reopen or open a new ticket.",
            ],
            [
                'key' => 'support_ticket_feedback_request',
                'name' => 'Support Ticket Feedback Request',
                'category' => 'Support Messages',
                'subject' => 'How did we do on ticket #{{ticket_id}}?',
                'body' => "Please rate your support experience for ticket {{ticket_id}}.\n\nThank you,\n{{company_name}}",
            ],
            [
                'key' => 'support_ticket_opened',
                'name' => 'Support Ticket Opened',
                'category' => 'Support Messages',
                'subject' => 'Support ticket opened #{{ticket_id}}',
                'body' => "We received your ticket.\nSubject: {{ticket_subject}}\n\nWe will get back to you shortly.",
            ],
            [
                'key' => 'support_ticket_opened_by_admin',
                'name' => 'Support Ticket Opened by Admin',
                'category' => 'Support Messages',
                'subject' => 'Support ticket opened for you #{{ticket_id}}',
                'body' => "An admin opened a ticket for you.\nSubject: {{ticket_subject}}\n\nWe will update you shortly.",
            ],
            [
                'key' => 'support_ticket_reply',
                'name' => 'Support Ticket Reply',
                'category' => 'Support Messages',
                'subject' => 'Reply on ticket #{{ticket_id}}',
                'body' => "There is a new reply on your ticket.\nSubject: {{ticket_subject}}\n\nLogin to view and respond: {{ticket_url}}",
            ],
            [
                'key' => 'default_notification_message',
                'name' => 'Default Notification Message',
                'category' => 'Notification Messages',
                'subject' => 'Notification from {{company_name}}',
                'body' => "Hi {{client_name}},\n\n{{message_body}}\n\nThanks,\n{{company_name}}",
            ],
            [
                'key' => 'cancellation_request_confirmation',
                'name' => 'Cancellation Request Confirmation',
                'category' => 'Product/Service Messages',
                'subject' => 'Cancellation request received - {{service_name}}',
                'body' => "Hi {{client_name}},\n\nWe received your cancellation request for {{service_name}}.\nRequest ID: {{request_id}}\n\nWe will review and update you shortly.",
            ],
            [
                'key' => 'service_welcome_email',
                'name' => 'Product/Service Welcome Email',
                'category' => 'Product/Service Messages',
                'subject' => 'Welcome to {{service_name}}',
                'body' => "Hi {{client_name}},\n\nWelcome to {{service_name}}.\nLogin: {{login_url}}\nService details: {{service_details}}\n\nThank you,\n{{company_name}}",
            ],
            [
                'key' => 'service_suspension_notification',
                'name' => 'Product/Service Suspension Notification',
                'category' => 'Product/Service Messages',
                'subject' => 'Service suspended - {{service_name}}',
                'body' => "Hi {{client_name}},\n\nYour service {{service_name}} has been suspended due to overdue invoices.\n\nPay here to restore service: {{payment_url}}",
            ],
            [
                'key' => 'service_unsuspension_notification',
                'name' => 'Product/Service Unsuspension Notification',
                'category' => 'Product/Service Messages',
                'subject' => 'Service restored - {{service_name}}',
                'body' => "Hi {{client_name}},\n\nYour service {{service_name}} has been restored. Thank you for your payment.",
            ],
            [
                'key' => 'admin_password_reset_confirmation',
                'name' => 'Admin Password Reset Confirmation',
                'category' => 'Admin Messages',
                'subject' => 'Admin password reset confirmation - {{company_name}}',
                'body' => "Hello {{admin_name}},\n\nThis is a confirmation that your admin password was reset.\n\nIf this was not you, contact support immediately.",
            ],
            [
                'key' => 'admin_password_reset_validation',
                'name' => 'Admin Password Reset Validation',
                'category' => 'Admin Messages',
                'subject' => 'Reset your admin password - {{company_name}}',
                'body' => "Hello {{admin_name}},\n\nUse this link to reset your admin password:\n{{reset_url}}\n\nThis link will expire soon.",
            ],
            [
                'key' => 'new_cancellation_request',
                'name' => 'New Cancellation Request',
                'category' => 'Admin Messages',
                'subject' => 'New cancellation request - {{service_name}}',
                'body' => "A new cancellation request was submitted.\nClient: {{client_name}} ({{client_email}})\nService: {{service_name}}\nRequest ID: {{request_id}}",
            ],
            [
                'key' => 'new_order_notification',
                'name' => 'New Order Notification',
                'category' => 'Admin Messages',
                'subject' => 'New order {{order_number}} - {{company_name}}',
                'body' => "A new order was placed.\nClient: {{client_name}} ({{client_email}})\nService: {{service_name}}\nOrder number: {{order_number}}\nOrder total: {{order_total}}",
            ],
            [
                'key' => 'service_unsuspension_failed',
                'name' => 'Service Unsuspension Failed',
                'category' => 'Admin Messages',
                'subject' => 'Service unsuspension failed - {{service_name}}',
                'body' => "Service unsuspension failed.\nClient: {{client_name}} ({{client_email}})\nService: {{service_name}}\nReason: {{error_message}}",
            ],
            [
                'key' => 'service_unsuspension_successful',
                'name' => 'Service Unsuspension Successful',
                'category' => 'Admin Messages',
                'subject' => 'Service unsuspended - {{service_name}}',
                'body' => "Service unsuspended successfully.\nClient: {{client_name}} ({{client_email}})\nService: {{service_name}}",
            ],
            [
                'key' => 'support_ticket_change_notification',
                'name' => 'Support Ticket Change Notification',
                'category' => 'Admin Messages',
                'subject' => 'Support ticket updated #{{ticket_id}}',
                'body' => "Support ticket updated.\nTicket: {{ticket_id}}\nSubject: {{ticket_subject}}\nStatus: {{ticket_status}}",
            ],
            [
                'key' => 'affiliate_monthly_referrals_report',
                'name' => 'Affiliate Monthly Referrals Report',
                'category' => 'Affiliate Messages',
                'subject' => 'Affiliate referrals report - {{month}}',
                'body' => "Hi {{affiliate_name}},\n\nHere is your monthly referrals report for {{month}}.\nTotal referrals: {{referral_count}}\nTotal earnings: {{referral_earnings}}\n\nThank you,\n{{company_name}}",
            ],
            [
                'key' => 'clients_only_bounce_message',
                'name' => 'Clients Only Bounce Message',
                'category' => 'System Messages',
                'subject' => 'Clients only',
                'body' => "This mailbox is for clients only. Please log in to your client area to contact support.",
            ],
            [
                'key' => 'closed_ticket_bounce_message',
                'name' => 'Closed Ticket Bounce Message',
                'category' => 'System Messages',
                'subject' => 'Ticket closed',
                'body' => "This ticket is closed. Please open a new ticket from your client area.",
            ],
            [
                'key' => 'replies_only_bounce_message',
                'name' => 'Replies Only Bounce Message',
                'category' => 'System Messages',
                'subject' => 'Replies only',
                'body' => "Please reply to an existing support ticket from your email.",
            ],
        ];

        foreach ($templates as $template) {
            $existing = DB::table('email_templates')->where('key', $template['key'])->first();

            if ($existing) {
                $subject = isset($existing->subject) ? trim((string) $existing->subject) : '';
                $body = isset($existing->body) ? trim((string) $existing->body) : '';

                $updates = [
                    'name' => $template['name'],
                    'category' => $template['category'],
                ];

                if ($subject === '') {
                    $updates['subject'] = $template['subject'];
                }

                if ($body === '') {
                    $updates['body'] = $template['body'];
                }

                if (! empty($updates)) {
                    $updates['updated_at'] = $timestamp;
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
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates') && Schema::hasColumn('email_templates', 'category')) {
            Schema::table('email_templates', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
