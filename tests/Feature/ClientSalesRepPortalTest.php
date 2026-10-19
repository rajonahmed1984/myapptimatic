<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientSalesRepPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'recaptcha.enabled' => false,
            'recaptcha.site_key' => null,
            'recaptcha.secret_key' => null,
            'recaptcha.project_id' => null,
            'recaptcha.api_key' => null,
        ]);
    }

    public function test_admin_can_make_a_client_a_sales_rep_without_changing_the_client_role(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route('admin.sales-reps.store'), [
                'user_id' => $client->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.sales-reps.index'));

        $this->assertSame(Role::CLIENT, $client->fresh()->role);
        $this->assertDatabaseHas('sales_representatives', [
            'user_id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
        ]);

        // A login account carries at most one rep profile.
        $this->actingAs($admin)
            ->post(route('admin.sales-reps.store'), [
                'user_id' => $client->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_editing_a_client_linked_rep_leaves_the_client_login_alone(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $client = $this->client(['email' => 'client-login@example.test']);
        $rep = $this->repFor($client);
        $passwordHash = $client->password;

        $this->actingAs($admin)
            ->put(route('admin.sales-reps.update', $rep), [
                'name' => 'Renamed Rep',
                'email' => 'rep-contact@example.test',
                'status' => 'active',
                'user_password' => 'new-password-123',
                'user_password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('admin.sales-reps.edit', $rep));

        $client->refresh();
        $this->assertSame(Role::CLIENT, $client->role);
        $this->assertSame('client-login@example.test', $client->email);
        $this->assertSame($passwordHash, $client->password);
        $this->assertSame('Renamed Rep', $rep->fresh()->name);
    }

    public function test_client_rep_signs_in_as_client_and_switches_between_portals(): void
    {
        $client = $this->client(['password' => 'secret-password']);
        $this->repFor($client);

        $this->from(route('login'))
            ->post(route('login.attempt'), ['email' => $client->email, 'password' => 'secret-password'])
            ->assertRedirect(route('client.dashboard', [], false));
        $this->assertAuthenticatedAs($client, 'web');
        $this->assertGuest('sales');

        $this->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('Switch to Sales Portal');

        $this->post(route('portal.switch', 'sales'))->assertRedirect(route('rep.dashboard'));
        $this->assertAuthenticatedAs($client, 'sales');

        $this->get(route('rep.dashboard'))
            ->assertOk()
            ->assertSee('Rep\\/Dashboard\\/Index', false)
            ->assertSee('Switch to Client Portal');

        // The client side stays signed in the whole time.
        $this->post(route('portal.switch', 'client'))->assertRedirect(route('client.dashboard'));
        // A real request starts on the default guard; in a test the auth manager
        // keeps EnsureSalesRep's shouldUse('sales') from the previous request.
        Auth::shouldUse('web');
        $this->get(route('client.dashboard'))->assertOk()->assertSee('Client\\/Dashboard\\/Index', false);
    }

    public function test_clients_without_an_active_rep_profile_cannot_switch_to_sales(): void
    {
        $client = $this->client();

        $this->actingAs($client)->post(route('portal.switch', 'sales'))->assertForbidden();

        $this->repFor($client, 'inactive');
        $this->actingAs($client)->post(route('portal.switch', 'sales'))->assertForbidden();
        $this->assertGuest('sales');

        $this->actingAs($client)->get(route('client.dashboard'))->assertDontSee('Switch to Sales Portal');
    }

    public function test_client_rep_is_a_sales_rep_only_inside_the_sales_portal(): void
    {
        $client = $this->client();
        $rep = $this->repFor($client);

        $ownProject = $this->project($client->customer_id);
        $repProject = $this->project(Customer::create(['name' => 'Referred Customer'])->id);
        $repProject->salesRepresentatives()->sync([$rep->id]);

        Auth::guard('web')->setUser($client);
        Auth::guard('sales')->setUser($client);

        Auth::shouldUse('web');
        $this->assertTrue($client->isClient());
        $this->assertFalse($client->isSales());
        $this->assertTrue(Gate::forUser($client)->allows('view', $ownProject));
        $this->assertFalse(Gate::forUser($client)->allows('view', $repProject));

        Auth::shouldUse('sales');
        $this->assertFalse($client->isClient());
        $this->assertTrue($client->isSales());
        $this->assertTrue(Gate::forUser($client)->allows('view', $repProject));
        $this->assertFalse(Gate::forUser($client)->allows('view', $ownProject));
    }

    public function test_sales_forgot_password_resets_the_client_login_for_a_client_rep(): void
    {
        Notification::fake();
        $client = $this->client();
        $this->repFor($client);

        $this->post(route('sales.password.email'), ['email' => $client->email])->assertSessionHas('status');

        $this->assertTrue(DB::table('password_reset_tokens')->where('email', $client->email)->exists());
        $this->assertFalse(DB::table('password_reset_tokens_sales')->where('email', $client->email)->exists());
    }

    private function client(array $attributes = []): User
    {
        $customer = Customer::create(['name' => 'Client Company']);

        return User::factory()->create(array_merge([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ], $attributes));
    }

    private function repFor(User $user, string $status = 'active'): SalesRepresentative
    {
        return SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $status,
        ]);
    }

    private function project(int $customerId): Project
    {
        return Project::create([
            'name' => 'Project '.$customerId,
            'customer_id' => $customerId,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
    }
}
