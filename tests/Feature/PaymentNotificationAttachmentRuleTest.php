<?php

namespace Tests\Feature;

use App\Enums\MailCategory;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\SalesRepresentative;
use App\Services\Mail\MailSender;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentNotificationAttachmentRuleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paid_status_sends_pdf_attachment_to_customer_and_related_sales_rep(): void
    {
        $rep = SalesRepresentative::create([
            'name' => 'Rep One',
            'email' => 'rep-one@example.test',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'name' => 'Client One',
            'email' => 'client-one@example.test',
            'default_sales_rep_id' => $rep->id,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-NOTIFY-1001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $gateway = PaymentGateway::query()->where('slug', 'manual')->firstOrFail();

        $attempt = PaymentAttempt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => 100,
            'currency' => 'USD',
            'gateway_reference' => 'MANUAL-1001',
        ]);

        $fakeSender = new class extends MailSender
        {
            /** @var array<int, array<string, mixed>> */
            public array $sent = [];

            public function sendView(
                string $category,
                array|string $to,
                string $view,
                array $data,
                string $subject,
                array $attachments = []
            ): void {
                $this->sent[] = [
                    'category' => $category,
                    'to' => $to,
                    'view' => $view,
                    'subject' => $subject,
                    'attachments' => $attachments,
                ];
            }
        };

        $this->app->instance(MailSender::class, $fakeSender);

        app(PaymentService::class)->markPaid($attempt, 'TXN-PAID-1001');

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);

        $clientMail = collect($fakeSender->sent)->first(
            fn (array $mail) => $mail['category'] === MailCategory::BILLING
                && $this->recipientMatches($mail['to'], $customer->email)
        );
        $repMail = collect($fakeSender->sent)->first(
            fn (array $mail) => $mail['category'] === MailCategory::BILLING
                && $this->recipientMatches($mail['to'], $rep->email)
        );

        $this->assertNotNull($clientMail, 'Expected client payment email to be sent.');
        $this->assertNotNull($repMail, 'Expected sales rep payment email to be sent.');
        $this->assertNotEmpty($clientMail['attachments'], 'Expected client paid mail to include PDF attachment.');
        $this->assertNotEmpty($repMail['attachments'], 'Expected sales rep paid mail to include PDF attachment.');
    }

    #[Test]
    public function non_unpaid_non_paid_status_sends_notification_without_pdf_attachment(): void
    {
        $rep = SalesRepresentative::create([
            'name' => 'Rep Two',
            'email' => 'rep-two@example.test',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'name' => 'Client Two',
            'email' => 'client-two@example.test',
            'default_sales_rep_id' => $rep->id,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-NOTIFY-1002',
            'status' => 'overdue',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'subtotal' => 150,
            'late_fee' => 0,
            'total' => 150,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $gateway = PaymentGateway::query()->where('slug', 'manual')->firstOrFail();

        $attempt = PaymentAttempt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => 150,
            'currency' => 'USD',
            'gateway_reference' => 'MANUAL-1002',
        ]);

        $fakeSender = new class extends MailSender
        {
            /** @var array<int, array<string, mixed>> */
            public array $sent = [];

            public function sendView(
                string $category,
                array|string $to,
                string $view,
                array $data,
                string $subject,
                array $attachments = []
            ): void {
                $this->sent[] = [
                    'category' => $category,
                    'to' => $to,
                    'view' => $view,
                    'subject' => $subject,
                    'attachments' => $attachments,
                ];
            }
        };

        $this->app->instance(MailSender::class, $fakeSender);

        app(PaymentService::class)->markCancelled($attempt, 'User cancelled payment.');

        $clientMail = collect($fakeSender->sent)->first(
            fn (array $mail) => $mail['category'] === MailCategory::BILLING
                && $this->recipientMatches($mail['to'], $customer->email)
        );
        $repMail = collect($fakeSender->sent)->first(
            fn (array $mail) => $mail['category'] === MailCategory::BILLING
                && $this->recipientMatches($mail['to'], $rep->email)
        );

        $this->assertNotNull($clientMail, 'Expected client payment update email to be sent.');
        $this->assertNotNull($repMail, 'Expected sales rep payment update email to be sent.');
        $this->assertEmpty($clientMail['attachments'], 'Expected non-paid/non-unpaid client mail to have no attachment.');
        $this->assertEmpty($repMail['attachments'], 'Expected non-paid/non-unpaid sales rep mail to have no attachment.');
    }

    /**
     * @param array<int, string>|string $to
     */
    private function recipientMatches(array|string $to, string $email): bool
    {
        $target = strtolower(trim($email));

        if (is_array($to)) {
            $normalized = array_map(
                static fn ($value) => strtolower(trim((string) $value)),
                $to
            );

            return in_array($target, $normalized, true);
        }

        return strtolower(trim($to)) === $target;
    }
}

