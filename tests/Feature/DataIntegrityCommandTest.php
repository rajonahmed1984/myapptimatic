<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function integrity_command_outputs_summary_table(): void
    {
        $this->artisan('diagnostics:integrity', ['--limit' => 5])
            ->expectsOutputToContain('Tasks without project')
            ->assertExitCode(0);
    }

    #[Test]
    public function reconciled_missing_paths_are_excluded_from_integrity_counts(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/missing-avatar.png',
        ]);

        Artisan::call('diagnostics:integrity', ['--limit' => 5]);
        $before = Artisan::output();

        $this->assertMatchesRegularExpression(
            '/Users with missing avatar files\s+\|\s+1\s+\|/',
            $before
        );

        $this->artisan('diagnostics:reconcile-missing-files', ['--limit' => 10])
            ->assertExitCode(0);

        $this->assertDatabaseHas('file_reference_reconciliations', [
            'model_type' => User::class,
            'model_id' => $user->id,
            'column_name' => 'avatar_path',
            'metadata_key' => '',
            'status' => 'missing',
            'action' => 'flagged',
        ]);

        Artisan::call('diagnostics:integrity', ['--limit' => 5]);
        $after = Artisan::output();

        $this->assertMatchesRegularExpression(
            '/Users with missing avatar files\s+\|\s+0\s+\|/',
            $after
        );
    }

    #[Test]
    public function reconcile_command_can_nullify_broken_references_without_deleting_records(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/still-missing.png',
        ]);

        $this->artisan('diagnostics:reconcile-missing-files', [
            '--limit' => 10,
            '--nullify' => true,
        ])->assertExitCode(0);

        $user->refresh();

        $this->assertNull($user->avatar_path);
        $this->assertDatabaseHas('file_reference_reconciliations', [
            'model_type' => User::class,
            'model_id' => $user->id,
            'column_name' => 'avatar_path',
            'metadata_key' => '',
            'status' => 'missing',
            'action' => 'nullified',
        ]);
    }
}
