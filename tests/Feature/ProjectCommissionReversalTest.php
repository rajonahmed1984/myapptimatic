<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CommissionEarning;
use App\Models\Customer;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCommissionReversalTest extends TestCase
{
    use RefreshDatabase;

    private SalesRepresentative $rep;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = Customer::create(['name' => 'Commission Client']);
        $this->rep = SalesRepresentative::create([
            'user_id' => User::factory()->create(['role' => Role::SALES])->id,
            'name' => 'Rep One',
            'status' => 'active',
        ]);
        $this->project = Project::create([
            'name' => 'Commission Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'BDT',
        ]);

        $sync = [$this->rep->id => ['amount' => 150]];
        $this->project->salesRepresentatives()->sync($sync);
        app(CommissionService::class)->syncProjectEarnings($this->project, $sync);
    }

    private function earning(): CommissionEarning
    {
        return CommissionEarning::query()->where('source_id', $this->project->id)->firstOrFail();
    }

    private function earned(): float
    {
        return (float) app(CommissionService::class)->computeRepBalance($this->rep->id)['total_earned'];
    }

    public function test_cancelling_project_reverses_and_reopening_restores_earning(): void
    {
        $this->assertEquals(150, $this->earned());

        $this->project->update(['status' => 'cancel']);
        $this->assertSame('reversed', $this->earning()->status);
        $this->assertEquals(0, $this->earned());

        // Opening the rep page backfills missing earnings; a cancelled
        // project must not come back through it.
        app(CommissionService::class)->ensureProjectEarningsForRepIds([$this->rep->id]);
        $this->assertEquals(0, $this->earned());

        $this->project->update(['status' => 'ongoing']);
        $this->assertSame('earned', $this->earning()->status);
        $this->assertEquals(150, $this->earned());
    }

    public function test_deleting_project_reverses_and_restoring_brings_back_earning(): void
    {
        $this->project->delete();
        $this->assertSame('reversed', $this->earning()->status);
        $this->assertEquals(0, $this->earned());

        $this->project->restore();
        $this->assertSame('earned', $this->earning()->status);
    }

    public function test_paid_earning_is_not_reversed(): void
    {
        $this->earning()->update(['status' => 'paid', 'paid_at' => now()]);

        $this->project->update(['status' => 'cancel']);

        $this->assertSame('paid', $this->earning()->status);
    }

    public function test_earnings_tab_hides_cancelled_project_commission(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->project->update(['status' => 'cancel']);

        $this->actingAs($admin)
            ->get(route('admin.sales-reps.show', ['sales_rep' => $this->rep->id, 'tab' => 'earnings']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.total_earned', fn ($v) => (float) $v === 0.0)->etc());
    }
}
