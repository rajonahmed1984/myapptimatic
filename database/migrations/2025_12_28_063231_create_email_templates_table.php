<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('email_templates')->insert([
            [
                'key' => 'client_signup_email',
                'name' => 'Client Signup Email',
                'subject' => 'Welcome to {{company_name}}',
                'body' => "Hi {{client_name}},\n\nYour account for {{company_name}} is ready. You can sign in here: {{login_url}}.\n\nThank you,\n{{company_name}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'order_confirmation',
                'name' => 'Order Confirmation',
                'subject' => 'Order confirmed - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nWe received your order for {{service_name}}.\n\nOrder number: {{order_number}}\n\nThank you,\n{{company_name}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'email_address_verification',
                'name' => 'Email Address Verification',
                'subject' => 'Verify your email - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nPlease verify your email address by clicking this link:\n{{verification_url}}\n\nThanks,\n{{company_name}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'password_reset_confirmation',
                'name' => 'Password Reset Confirmation',
                'subject' => 'Your password was reset - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nThis is a confirmation that your password was reset.\n\nIf this was not you, contact support.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'password_reset_validation',
                'name' => 'Password Reset Validation',
                'subject' => 'Reset your password - {{company_name}}',
                'body' => "Hi {{client_name}},\n\nUse this link to reset your password:\n{{reset_url}}\n\nThis link will expire soon.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'invoice_created',
                'name' => 'Invoice Created',
                'subject' => 'Invoice {{invoice_number}} created',
                'body' => "Hi {{client_name}},\n\nA new invoice {{invoice_number}} has been created.\nTotal: {{invoice_total}}\nDue date: {{invoice_due_date}}\n\nPay here: {{payment_url}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'invoice_payment_reminder',
                'name' => 'Invoice Payment Reminder',
                'subject' => 'Reminder: invoice {{invoice_number}} due',
                'body' => "Hi {{client_name}},\n\nThis is a reminder that invoice {{invoice_number}} is due on {{invoice_due_date}}.\nTotal: {{invoice_total}}\n\nPay here: {{payment_url}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'invoice_payment_confirmation',
                'name' => 'Invoice Payment Confirmation',
                'subject' => 'Payment received for invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nWe received your payment for invoice {{invoice_number}}.\n\nThank you,\n{{company_name}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'invoice_overdue_first_notice',
                'name' => 'First Invoice Overdue Notice',
                'subject' => 'Overdue notice: invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nInvoice {{invoice_number}} is now overdue.\n\nPay here: {{payment_url}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'invoice_overdue_second_notice',
                'name' => 'Second Invoice Overdue Notice',
                'subject' => 'Second overdue notice: invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nThis is a second reminder that invoice {{invoice_number}} is overdue.\n\nPay here: {{payment_url}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'invoice_overdue_third_notice',
                'name' => 'Third Invoice Overdue Notice',
                'subject' => 'Final overdue notice: invoice {{invoice_number}}',
                'body' => "Hi {{client_name}},\n\nThis is the final reminder that invoice {{invoice_number}} is overdue.\n\nPay here: {{payment_url}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'service_suspension_notification',
                'name' => 'Service Suspension Notification',
                'subject' => 'Service suspended - {{service_name}}',
                'body' => "Hi {{client_name}},\n\nYour service {{service_name}} has been suspended due to overdue invoices.\n\nPay here to restore service: {{payment_url}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'service_unsuspension_notification',
                'name' => 'Service Unsuspension Notification',
                'subject' => 'Service restored - {{service_name}}',
                'body' => "Hi {{client_name}},\n\nYour service {{service_name}} has been restored. Thank you for your payment.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'clients_only_bounce_message',
                'name' => 'Clients Only Bounce Message',
                'subject' => 'Clients only',
                'body' => "This mailbox is for clients only. Please log in to your client area to contact support.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'closed_ticket_bounce_message',
                'name' => 'Closed Ticket Bounce Message',
                'subject' => 'Ticket closed',
                'body' => "This ticket is closed. Please open a new ticket from your client area.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'replies_only_bounce_message',
                'name' => 'Replies Only Bounce Message',
                'subject' => 'Replies only',
                'body' => "Please reply to an existing support ticket from your email.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'support_ticket_auto_close_notification',
                'name' => 'Support Ticket Auto Close Notification',
                'subject' => 'Ticket auto-closed #{{ticket_id}}',
                'body' => "Your ticket {{ticket_id}} was closed due to inactivity.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'support_ticket_feedback_request',
                'name' => 'Support Ticket Feedback Request',
                'subject' => 'How did we do on ticket #{{ticket_id}}?',
                'body' => "Please rate your support experience for ticket {{ticket_id}}.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'support_ticket_opened',
                'name' => 'Support Ticket Opened',
                'subject' => 'Support ticket opened #{{ticket_id}}',
                'body' => "We received your ticket.\n\nSubject: {{ticket_subject}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'support_ticket_opened_by_admin',
                'name' => 'Support Ticket Opened by Admin',
                'subject' => 'Support ticket opened for you #{{ticket_id}}',
                'body' => "An admin opened a ticket for you.\n\nSubject: {{ticket_subject}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'support_ticket_reply',
                'name' => 'Support Ticket Reply',
                'subject' => 'Reply on ticket #{{ticket_id}}',
                'body' => "There is a new reply on your ticket.\n\nSubject: {{ticket_subject}}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
