<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesRepReferralFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private SalesRepresentative $rep;

    private Customer $referred;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => Role::SALES]);
        $this->rep = SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => 'Osman',
            'email' => $user->email,
            'status' => 'active',
            'project_commission_percentage' => 35,
        ]);
        $this->referred = Customer::create([
            'name' => 'Referred Client',
            'company_name' => 'Tower Ltd',
            'status' => 'active',
            'referred_by_sales_rep_id' => $this->rep->id,
            'default_sales_rep_id' => $this->rep->id,
        ]);
        $this->admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
    }

    #[Test]
    public function the_new_project_form_knows_who_referred_each_customer_and_each_reps_percentage(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.projects.create', ['customer_id' => $this->referred->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('customers.0.referred_by_sales_rep_id', $this->rep->id)
                ->where('salesReps.0.project_commission_percentage', 35)
                ->where('form.customer_id', (string) $this->referred->id));
    }

    #[Test]
    public function the_rep_dashboard_lists_who_signed_up_through_the_link(): void
    {
        $this->actingAs($this->rep->user, 'sales')
            ->get(route('rep.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('referral.customers_count', 1)
                ->where('referral.customers.0.name', 'Tower Ltd'));
    }

    #[Test]
    public function the_admin_customer_page_shows_who_referred_the_customer(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $this->referred))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('referred_by.name', 'Osman'));

        $plain = Customer::create(['name' => 'Walk-in', 'status' => 'active']);
        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $plain))
            ->assertInertia(fn ($page) => $page->where('referred_by', null));
    }
}
