<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceObserverNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_unpaid_invoice_dispatches_notification_job_and_sends_email(): void
    {
        $customer = Customer::create([
            'name' => 'Invoice Client',
            'email' => 'invoice_client@example.com',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => '1001',
            'status' => 'unpaid',
            'issue_date' => '2026-06-09',
            'due_date' => '2026-06-16',
            'subtotal' => 150.00,
            'tax_rate_percent' => 0,
            'tax_mode' => 'exclusive',
            'tax_amount' => 0,
            'late_fee' => 0,
            'total' => 150.00,
            'currency' => 'USD',
        ]);

        // Check if an email was sent by querying the SystemLog (since array mailer writes to it in AppServiceProvider)
        $emailLogs = SystemLog::query()
            ->where('category', 'email')
            ->where('message', 'Email sent.')
            ->get();

        $this->assertNotEmpty($emailLogs);

        $matched = false;
        foreach ($emailLogs as $log) {
            $to = $log->context['to'] ?? [];
            if (in_array('invoice_client@example.com', $to, true)) {
                $matched = true;
                $this->assertStringContainsString('1001', $log->context['subject'] ?? '');
                break;
            }
        }

        $this->assertTrue($matched, 'No email sent to invoice_client@example.com found in SystemLog.');
    }

    #[Test]
    public function creating_paid_invoice_does_not_send_email(): void
    {
        $customer = Customer::create([
            'name' => 'Invoice Client 2',
            'email' => 'invoice_client2@example.com',
        ]);

        Invoice::create([
            'customer_id' => $customer->id,
            'number' => '1002',
            'status' => 'paid',
            'issue_date' => '2026-06-09',
            'due_date' => '2026-06-16',
            'subtotal' => 150.00,
            'tax_rate_percent' => 0,
            'tax_mode' => 'exclusive',
            'tax_amount' => 0,
            'late_fee' => 0,
            'total' => 150.00,
            'currency' => 'USD',
        ]);

        $emailLogs = SystemLog::query()
            ->where('category', 'email')
            ->where('message', 'Email sent.')
            ->get();

        $matchedCount = 0;
        foreach ($emailLogs as $log) {
            $to = $log->context['to'] ?? [];
            if (in_array('invoice_client2@example.com', $to, true)) {
                $matchedCount++;
            }
        }

        $this->assertSame(0, $matchedCount, 'Email was sent for a paid invoice.');
    }

    #[Test]
    public function creating_draft_invoice_does_not_send_email(): void
    {
        $customer = Customer::create([
            'name' => 'Invoice Client 3',
            'email' => 'invoice_client3@example.com',
        ]);

        Invoice::create([
            'customer_id' => $customer->id,
            'number' => '1003',
            'status' => 'draft',
            'issue_date' => '2026-06-09',
            'due_date' => '2026-06-16',
            'subtotal' => 150.00,
            'tax_rate_percent' => 0,
            'tax_mode' => 'exclusive',
            'tax_amount' => 0,
            'late_fee' => 0,
            'total' => 150.00,
            'currency' => 'USD',
        ]);

        $emailLogs = SystemLog::query()
            ->where('category', 'email')
            ->where('message', 'Email sent.')
            ->get();

        $matchedCount = 0;
        foreach ($emailLogs as $log) {
            $to = $log->context['to'] ?? [];
            if (in_array('invoice_client3@example.com', $to, true)) {
                $matchedCount++;
            }
        }

        $this->assertSame(0, $matchedCount, 'Email was sent for a draft invoice.');
    }
}
