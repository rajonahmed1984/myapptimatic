<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Currency\CurrencyService;
use App\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencySelectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can register with BDT currency (default).
     */
    public function test_user_can_register_with_bdt_currency()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company',
            'phone' => '1234567890',
            'currency' => 'BDT',
        ]);

        $this->assertAuthenticated();
        $user = User::where('email', 'test@example.com')->first();
        $this->assertEquals('BDT', $user->currency);
        $response->assertRedirect();
    }

    /**
     * Test user can register with USD currency.
     */
    public function test_user_can_register_with_usd_currency()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User USD',
            'email' => 'test.usd@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'currency' => 'USD',
        ]);

        $this->assertAuthenticated();
        $user = User::where('email', 'test.usd@example.com')->first();
        $this->assertEquals('USD', $user->currency);
    }

    /**
     * Test registration defaults to BDT when currency not provided.
     */
    public function test_registration_defaults_to_bdt_when_currency_not_provided()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test.default@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $user = User::where('email', 'test.default@example.com')->first();
        $this->assertEquals('BDT', $user->currency);
        $response->assertRedirect();
    }

    /**
     * Test registration rejects invalid currency.
     */
    public function test_registration_rejects_invalid_currency()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test.invalid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'currency' => 'EUR',
        ]);

        $response->assertSessionHasErrors('currency');
        $this->assertDatabaseMissing('users', ['email' => 'test.invalid@example.com']);
    }

    /**
     * Test only BDT and USD are allowed currencies.
     */
    public function test_only_bdt_and_usd_are_allowed()
    {
        $allowed = Currency::allowed();
        $this->assertCount(2, $allowed);
        $this->assertContains('BDT', $allowed);
        $this->assertContains('USD', $allowed);
    }

    /**
     * Test currency helper validates correctly.
     */
    public function test_currency_helper_validates_allowed_currencies()
    {
        $this->assertTrue(Currency::isAllowed('BDT'));
        $this->assertTrue(Currency::isAllowed('USD'));
        $this->assertTrue(Currency::isAllowed('bdt'));
        $this->assertTrue(Currency::isAllowed('usd'));
        $this->assertFalse(Currency::isAllowed('EUR'));
        $this->assertFalse(Currency::isAllowed('GBP'));
    }

    /**
     * Test currency symbols display correctly.
     */
    public function test_currency_symbols_display_correctly()
    {
        $this->assertEquals('৳', Currency::symbol('BDT'));
        $this->assertEquals('$', Currency::symbol('USD'));
    }

    /**
     * Test currency formatting.
     */
    public function test_currency_formatting()
    {
        $formatted = Currency::format(1000.50, 'BDT');
        $this->assertStringContainsString('৳', $formatted);
        // number_format adds thousands separator
        $this->assertStringContainsString('1,000.50', $formatted);

        $formatted = Currency::format(100.50, 'USD');
        $this->assertStringContainsString('$', $formatted);
        $this->assertStringContainsString('100.50', $formatted);
    }

    /**
     * Test converter service only converts between BDT and USD.
     */
    public function test_converter_only_allows_bdt_and_usd()
    {
        $this->fakeRates(110.0);
        $converter = new CurrencyService();

        // Valid conversions
        $this->assertIsFloat($converter->convert(100, 'BDT', 'USD'));
        $this->assertIsFloat($converter->convert(100, 'USD', 'BDT'));
        $this->assertIsFloat($converter->convert(100, 'BDT', 'BDT'));

        // Invalid conversions
        $this->expectException(\InvalidArgumentException::class);
        $converter->convert(100, 'EUR', 'BDT');
    }

    /**
     * Test converter rejects invalid source currency.
     */
    public function test_converter_rejects_invalid_source_currency()
    {
        $converter = new CurrencyService();
        $this->expectException(\InvalidArgumentException::class);
        $converter->convert(100, 'EUR', 'USD');
    }

    /**
     * Test converter rejects invalid target currency.
     */
    public function test_converter_rejects_invalid_target_currency()
    {
        $converter = new CurrencyService();
        $this->expectException(\InvalidArgumentException::class);
        $converter->convert(100, 'BDT', 'GBP');
    }

    /**
     * Test conversion rates.
     */
    public function test_conversion_rates()
    {
        $this->fakeRates(100.0);
        $converter = new CurrencyService();

        // Test BDT to USD
        $result = $converter->convert(1, 'BDT', 'USD');
        $this->assertEquals(0.01, round($result, 2));

        // Test USD to BDT
        $result = $converter->convert(1, 'USD', 'BDT');
        $this->assertEquals(100.00, $result);

        // Test same currency
        $result = $converter->convert(100, 'BDT', 'BDT');
        $this->assertEquals(100, $result);
    }

    /**
     * Test converter get rate method.
     */
    public function test_converter_get_rate()
    {
        $this->fakeRates(120.0);
        $converter = new CurrencyService();

        $rate = $converter->getRate('BDT', 'USD');
        $this->assertEqualsWithDelta(1 / 120, $rate, 0.0001);

        $rate = $converter->getRate('USD', 'BDT');
        $this->assertEquals(120.0, $rate);

        $rate = $converter->getRate('BDT', 'BDT');
        $this->assertEquals(1.0, $rate);
    }

    public function test_converter_uses_cached_rate()
    {
        $this->fakeRates(130.0);
        $converter = new CurrencyService();

        $converter->convert(10, 'USD', 'BDT');
        $converter->convert(20, 'USD', 'BDT');

        Http::assertSentCount(1);
        Cache::assertHas(config('currency.cache_key'));
    }

    private function fakeRates(float $usdBdt): void
    {
        Cache::fake();
        Http::fake([
            '*' => Http::response(['rates' => ['BDT' => $usdBdt]], 200),
        ]);
    }
}
