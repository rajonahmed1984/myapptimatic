<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiagnoseDatabaseConnectionCommandTest extends TestCase
{
    public function test_diagnostics_db_connection_command_reports_success_on_testing_connection(): void
    {
        $this->artisan('diagnostics:db-connection')
            ->expectsOutputToContain('Connection: sqlite')
            ->expectsOutputToContain('DB ping: OK')
            ->assertExitCode(0);
    }

    public function test_diagnostics_db_connection_command_reports_missing_connection(): void
    {
        $this->artisan('diagnostics:db-connection --connection=missing_connection')
            ->expectsOutputToContain('Connection [missing_connection] is not configured.')
            ->assertExitCode(1);
    }
}
