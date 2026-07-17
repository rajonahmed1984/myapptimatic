<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceReportsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function finance_reports_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_finance_reports_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.reports.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Finance\\/Reports\\/Index', false);
    }

    #[Test]
    public function finance_reports_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_finance_reports_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.reports.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Finance\\/Reports\\/Index', false);
    }

    #[Test]
    public function finance_reports_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_finance_reports_index', false);
        $this->actingAs($client)
            ->get(route('admin.finance.reports.index'))
            ->assertForbidden();

        config()->set('features.admin_finance_reports_index', true);
        $this->actingAs($client)
            ->get(route('admin.finance.reports.index'))
            ->assertForbidden();
    }

    #[Test]
    public function finance_reports_cache_reuses_filtered_rows_and_fresh_query_bypasses_it(): void
    {
        config()->set('performance.finance_report_cache_seconds', 60);
        Cache::flush();

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $category = IncomeCategory::query()->create([
            'name' => 'Phase Seven Income',
            'status' => 'active',
        ]);

        Income::query()->create([
            'income_category_id' => $category->id,
            'title' => 'Cached income',
            'amount' => 100,
            'income_date' => '2026-07-10',
            'created_by' => $admin->id,
        ]);

        $filters = [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'income_sources' => ['manual'],
            'expense_sources' => ['manual'],
        ];

        $first = $this->actingAs($admin)->get(route('admin.finance.reports.index', $filters));
        $first->assertOk();
        $this->assertSame(100.0, (float) data_get($this->inertiaProps($first->getContent()), 'summary.total_income'));

        Income::query()->create([
            'income_category_id' => $category->id,
            'title' => 'New income after cache warm-up',
            'amount' => 50,
            'income_date' => '2026-07-11',
            'created_by' => $admin->id,
        ]);

        $cached = $this->actingAs($admin)->get(route('admin.finance.reports.index', $filters));
        $cached->assertOk();
        $this->assertSame(100.0, (float) data_get($this->inertiaProps($cached->getContent()), 'summary.total_income'));

        $fresh = $this->actingAs($admin)->get(route('admin.finance.reports.index', [
            ...$filters,
            'fresh' => 1,
        ]));
        $fresh->assertOk();
        $this->assertSame(150.0, (float) data_get($this->inertiaProps($fresh->getContent()), 'summary.total_income'));
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaProps(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);
        $this->assertIsArray($payload);

        $props = data_get($payload, 'props', []);
        $this->assertIsArray($props);

        return $props;
    }
}
