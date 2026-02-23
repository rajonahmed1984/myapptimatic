<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\PaymentProof;
use App\Models\Project;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplexModuleTransportContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function payment_proof_receipt_endpoint_returns_binary_response_headers(): void
    {
        Storage::fake('public');

        $admin = $this->createMasterAdmin();
        $proof = $this->createPaymentProofWithAttachment('payment-proofs/receipt-test.pdf');

        Storage::disk('public')->put($proof->attachment_path, '%PDF-1.4 test');

        $response = $this->actingAs($admin)->get(route('admin.payment-proofs.receipt', $proof));

        $response->assertOk();
        $this->assertStringNotContainsString('text/html', strtolower((string) $response->headers->get('content-type')));
        $this->assertStringContainsString('receipt-test.pdf', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function support_ticket_attachment_endpoint_returns_binary_response_headers_for_admin(): void
    {
        Storage::fake('public');

        $admin = $this->createMasterAdmin();
        $customer = Customer::query()->create(['name' => 'Support Attachment Customer']);

        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'subject' => 'Attachment contract',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $reply = SupportTicketReply::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'Attached',
            'attachment_path' => 'support-ticket-replies/ticket-proof.pdf',
            'is_admin' => true,
        ]);

        Storage::disk('public')->put($reply->attachment_path, '%PDF-1.4 support');

        $response = $this->actingAs($admin)->get(route('support-ticket-replies.attachment', $reply));

        $response->assertOk();
        $this->assertStringNotContainsString('text/html', strtolower((string) $response->headers->get('content-type')));
        $this->assertStringContainsString('ticket-proof.pdf', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function admin_project_chat_stream_endpoint_keeps_sse_headers(): void
    {
        $admin = $this->createMasterAdmin();
        $project = $this->createProjectForCustomer();

        $response = $this->actingAs($admin)->get(route('admin.projects.chat.stream', $project));

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('cache-control'));
        $this->assertSame('no', (string) $response->headers->get('x-accel-buffering'));
    }

    #[Test]
    public function client_project_chat_stream_endpoint_keeps_sse_headers(): void
    {
        $customer = Customer::query()->create(['name' => 'Client SSE Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $project = Project::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Client SSE Project',
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($client)->get(route('client.projects.chat.stream', $project));

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('cache-control'));
        $this->assertSame('no', (string) $response->headers->get('x-accel-buffering'));
    }

    private function createMasterAdmin(): User
    {
        return User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
    }

    private function createProjectForCustomer(): Project
    {
        $customer = Customer::query()->create(['name' => 'SSE Customer']);

        return Project::query()->create([
            'customer_id' => $customer->id,
            'name' => 'SSE Project',
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1500,
            'initial_payment_amount' => 200,
            'currency' => 'USD',
        ]);
    }

    private function createPaymentProofWithAttachment(string $path): PaymentProof
    {
        $customer = Customer::query()->create([
            'name' => 'Proof Attachment Customer',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'number' => 'INV-RECEIPT-'.uniqid(),
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 95,
            'late_fee' => 0,
            'total' => 95,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $gateway = PaymentGateway::query()
            ->where('driver', 'manual')
            ->orderBy('id')
            ->firstOrFail();

        $attempt = PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => 95,
            'currency' => 'USD',
            'gateway_reference' => 'ATTACH-'.uniqid(),
        ]);

        return PaymentProof::query()->create([
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'reference' => 'RECEIPT-'.uniqid(),
            'amount' => 95,
            'paid_at' => now()->toDateString(),
            'notes' => 'Attachment contract test',
            'attachment_path' => $path,
            'status' => 'pending',
        ]);
    }
}
