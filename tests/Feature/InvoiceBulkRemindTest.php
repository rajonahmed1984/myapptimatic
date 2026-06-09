<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceBulkRemindTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_send_bulk_reminders_for_unpaid_and_overdue_invoices(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Reminder Client',
            'email' => 'reminder_client@example.com',
        ]);

        $unpaidInvoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-9001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $overdueInvoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-9002',
            'status' => 'overdue',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'subtotal' => 200,
            'late_fee' => 0,
            'total' => 200,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        // Clear existing system logs (created via model observers or other setups)
        SystemLog::truncate();

        $payload = [
            'invoice_ids' => [$unpaidInvoice->id, $overdueInvoice->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.invoices.bulk-remind'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $unpaidInvoice->refresh();
        $overdueInvoice->refresh();

        $this->assertNotNull($unpaidInvoice->reminder_sent_at);
        $this->assertNotNull($overdueInvoice->first_overdue_reminder_sent_at);

        // Verify that emails were sent by querying the SystemLog
        $emailLogs = SystemLog::query()
            ->where('category', 'email')
            ->where('message', 'Email sent.')
            ->get();

        $this->assertNotEmpty($emailLogs);

        $sentToClientCount = 0;
        foreach ($emailLogs as $log) {
            $to = $log->context['to'] ?? [];
            if (in_array('reminder_client@example.com', $to, true)) {
                $sentToClientCount++;
            }
        }

        // We expect 2 reminder emails sent to customer (one for unpaid, one for overdue)
        $this->assertSame(2, $sentToClientCount);
    }

    #[Test]
    public function reminders_are_not_sent_for_paid_or_cancelled_invoices(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'No Reminder Client',
            'email' => 'noreminder_client@example.com',
        ]);

        $paidInvoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-9003',
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $cancelledInvoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-9004',
            'status' => 'cancelled',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 200,
            'late_fee' => 0,
            'total' => 200,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        SystemLog::truncate();

        $payload = [
            'invoice_ids' => [$paidInvoice->id, $cancelledInvoice->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.invoices.bulk-remind'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $paidInvoice->refresh();
        $cancelledInvoice->refresh();

        $this->assertNull($paidInvoice->reminder_sent_at);
        $this->assertNull($cancelledInvoice->reminder_sent_at);

        $emailLogs = SystemLog::query()
            ->where('category', 'email')
            ->where('message', 'Email sent.')
            ->get();

        $sentToClientCount = 0;
        foreach ($emailLogs as $log) {
            $to = $log->context['to'] ?? [];
            if (in_array('noreminder_client@example.com', $to, true)) {
                $sentToClientCount++;
            }
        }

        $this->assertSame(0, $sentToClientCount);
    }

    #[Test]
    public function guest_cannot_trigger_bulk_reminders(): void
    {
        $payload = [
            'invoice_ids' => [1, 2],
        ];

        $response = $this->post(route('admin.invoices.bulk-remind'), $payload);

        $response->assertRedirect('/admin/login');
    }
}
