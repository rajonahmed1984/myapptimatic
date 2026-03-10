<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrandingUrlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_an_absolute_logo_url_for_email_assets(): void
    {
        Setting::setValue('app_url', 'https://my.apptimatic.com');

        Storage::fake('public');
        Storage::disk('public')->put('branding/company-logo.png', 'fake-image');

        $this->assertSame(
            'https://my.apptimatic.com/branding/branding/company-logo.png',
            Branding::url('branding/company-logo.png')
        );
    }
}
