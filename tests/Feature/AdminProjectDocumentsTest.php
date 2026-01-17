<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProjectDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_download_contract_and_proposal(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Acme Corp',
            'company_name' => 'Acme LLC',
            'email' => 'acme@example.com',
            'status' => 'active',
        ]);
        $salesRep = SalesRepresentative::create([
            'name' => 'Test Rep',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.projects.store'), [
            'name' => 'Documented Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 500,
            'currency' => 'USD',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'tasks' => [
                [
                    'title' => 'Kickoff',
                    'task_type' => 'feature',
                    'priority' => 'medium',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addDay()->toDateString(),
                    'assignee' => 'sales_rep:'.$salesRep->id,
                    'descriptions' => ['Initial task'],
                ],
            ],
            'contract_file' => UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf'),
            'proposal_file' => UploadedFile::fake()->create('proposal.docx', 150, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]);

        $response->assertRedirect();

        $project = Project::latest('id')->first();
        $this->assertNotNull($project);
        $this->assertEquals('contract.pdf', $project->contract_original_name);
        $this->assertEquals('proposal.docx', $project->proposal_original_name);
        Storage::disk('public')->assertExists($project->contract_file_path);
        Storage::disk('public')->assertExists($project->proposal_file_path);

        $contractDownload = $this->actingAs($admin)->get(route('admin.projects.download', ['project' => $project, 'type' => 'contract']));
        $contractDownload->assertOk();

        $proposalDownload = $this->actingAs($admin)->get(route('admin.projects.download', ['project' => $project, 'type' => 'proposal']));
        $proposalDownload->assertOk();
    }

    public function test_download_route_returns_404_when_file_missing(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'No Files',
            'email' => 'nofiles@example.com',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Empty Docs Project',
            'customer_id' => $customer->id,
            'type' => 'other',
            'status' => 'ongoing',
            'total_budget' => 500,
            'initial_payment_amount' => 250,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.projects.download', ['project' => $project, 'type' => 'contract']));
        $response->assertStatus(404);
    }
}
