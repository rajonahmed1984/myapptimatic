<?php

namespace Tests\Feature\Smoke;

use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CronBillingEndpointSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    #[Test]
    public function cron_billing_rejects_invalid_business_token_even_with_valid_signature(): void
    {
        Setting::setValue('cron_token', 'expected-cron-token');
        Artisan::shouldReceive('call')->never();

        $signedUrl = URL::signedRoute('cron.billing', [
            'token' => 'wrong-cron-token',
        ]);

        $this->get($signedUrl)
            ->assertForbidden()
            ->assertSeeText('Unauthorized')
            ->assertDontSee('data-page=');
    }

    #[Test]
    public function cron_billing_returns_success_view_for_valid_signed_request(): void
    {
        Setting::setValue('cron_token', 'expected-cron-token');
        Artisan::shouldReceive('call')
            ->once()
            ->with('billing:run')
            ->andReturn(0);

        $signedUrl = URL::signedRoute('cron.billing', [
            'token' => 'expected-cron-token',
        ]);

        $this->get($signedUrl)
            ->assertOk()
            ->assertSeeText('Cron Job Executed')
            ->assertSeeText('Cron job executed successfully.')
            ->assertDontSee('data-page=');
    }
}
