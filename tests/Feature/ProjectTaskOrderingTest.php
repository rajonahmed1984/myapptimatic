<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskOrderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function project_tasks_are_ordered_by_latest_created_first(): void
    {
        $customer = Customer::create(['name' => 'Ordering Customer']);
        $project = Project::create([
            'name' => 'Ordering Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $oldest = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Oldest task',
            'status' => 'pending',
        ]);
        $oldest->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();

        $middle = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Middle task',
            'status' => 'pending',
        ]);
        $middle->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->save();

        $newest = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Newest task',
            'status' => 'pending',
        ]);
        $newest->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $admin = User::factory()->create(['role' => 'master_admin']);

        $response = $this->actingAs($admin)
            ->get(route('admin.projects.tasks.index', $project));

        $response->assertOk();
        $content = $this->extractRenderableHtml($response->getContent());
        $newestPosition = strpos($content, $newest->title);
        $middlePosition = strpos($content, $middle->title);
        $oldestPosition = strpos($content, $oldest->title);

        $this->assertNotFalse($newestPosition);
        $this->assertNotFalse($middlePosition);
        $this->assertNotFalse($oldestPosition);
        $this->assertTrue($newestPosition < $middlePosition);
        $this->assertTrue($middlePosition < $oldestPosition);
    }

    #[Test]
    public function task_start_date_defaults_to_today_on_project_create(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);

        $response = $this->actingAs($admin)
            ->get(route('admin.projects.create'));

        $response->assertOk();

        $today = now()->toDateString();
        $content = $this->extractRenderableHtml($response->getContent());
        $this->assertStringContainsString('name="tasks[0][start_date]" value="'.$today.'"', $content);
    }

    private function extractRenderableHtml(string $content): string
    {
        if (preg_match('/data-page="([^"]+)"/', $content, $matches) === 1) {
            $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
            $legacyHtml = (string) data_get($payload, 'props.content_html', '');
            if ($legacyHtml !== '') {
                return html_entity_decode($legacyHtml, ENT_QUOTES);
            }
        }

        return $content;
    }
}
