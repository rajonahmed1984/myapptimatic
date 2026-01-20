<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
