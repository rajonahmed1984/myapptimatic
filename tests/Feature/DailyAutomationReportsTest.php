<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\CronActivityReportMail;
use App\Mail\LicenseSyncReportMail;
use App\Models\CronRun;
use App\Models\LicenseSyncRun;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DailyAutomationReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'sync']);
    }

    public function test_cron_activity_email_sends_and_includes_failures(): void
    {
        Mail::fake();
        User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'email' => 'admin@example.com',
        ]);

        Setting::setValue('automation_time_of_day', '10:00');
        Setting::setValue('time_zone', 'UTC');

        Carbon::setTestNow(Carbon::create(2026, 3, 10, 10, 0, 0, 'UTC'));

        CronRun::create([
            'command' => 'billing:run',
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'duration_ms' => 60000,
            'error_excerpt' => 'Failure output.',
        ]);

        $this->artisan('reports:daily')->assertExitCode(0);

        Mail::assertSent(CronActivityReportMail::class, function (CronActivityReportMail $mail) {
            $rendered = $mail->render();
            return str_contains($rendered, 'Failures') && str_contains($rendered, '1');
        });
    }

    public function test_license_sync_report_uses_sync_run_counts(): void
    {
        Mail::fake();
        User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'email' => 'admin@example.com',
        ]);

        Setting::setValue('automation_time_of_day', '10:00');
        Setting::setValue('time_zone', 'UTC');

        Carbon::setTestNow(Carbon::create(2026, 3, 10, 10, 0, 0, 'UTC'));

        LicenseSyncRun::create([
            'run_at' => now()->subMinutes(10),
            'total_checked' => 12,
            'updated_count' => 3,
            'expired_count' => 1,
            'suspended_count' => 2,
            'invalid_count' => 1,
            'domain_updates_count' => 4,
            'domain_mismatch_count' => 1,
            'api_failures_count' => 2,
            'failed_count' => 2,
            'errors_json' => [
                [
                    'license_id' => 1,
                    'license_key' => 'ABC123',
                    'customer' => 'Sample',
                    'previous_status' => 'active',
                    'new_status' => 'revoked',
                    'reason' => 'auto_expired',
                ],
            ],
        ]);

        $this->artisan('reports:daily --force')->assertExitCode(0);

        Mail::assertSent(LicenseSyncReportMail::class, function (LicenseSyncReportMail $mail) {
            $rendered = $mail->render();
            return str_contains($rendered, 'Total licenses checked')
                && str_contains($rendered, '12')
                && str_contains($rendered, 'Updated licenses')
                && str_contains($rendered, '3');
        });
    }

    public function test_license_sync_report_warns_when_no_sync_run_logged(): void
    {
        Mail::fake();
        User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'email' => 'admin@example.com',
        ]);

        Setting::setValue('automation_time_of_day', '10:00');
        Setting::setValue('time_zone', 'UTC');

        Carbon::setTestNow(Carbon::create(2026, 3, 10, 10, 0, 0, 'UTC'));

        $this->artisan('reports:daily --force')->assertExitCode(0);

        Mail::assertSent(LicenseSyncReportMail::class, function (LicenseSyncReportMail $mail) {
            return str_contains($mail->render(), 'No synchronisation run recorded today');
        });
    }

    public function test_setting_change_affects_next_run(): void
    {
        Mail::fake();
        User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'email' => 'admin@example.com',
        ]);

        Setting::setValue('time_zone', 'UTC');
        Setting::setValue('automation_time_of_day', '09:00');

        Carbon::setTestNow(Carbon::create(2026, 3, 10, 10, 0, 0, 'UTC'));

        CronRun::create([
            'command' => 'billing:run',
            'status' => 'success',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'duration_ms' => 60000,
        ]);

        $this->artisan('reports:daily')->assertExitCode(0);
        Mail::assertNothingSent();

        Setting::setValue('automation_time_of_day', '10:00');
        $this->artisan('reports:daily')->assertExitCode(0);

        Mail::assertSent(CronActivityReportMail::class);
    }
}
