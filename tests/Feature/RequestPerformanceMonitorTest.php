<?php

namespace Tests\Feature;

use App\Http\Middleware\MeasureRequestPerformance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequestPerformanceMonitorTest extends TestCase
{
    #[Test]
    public function performance_headers_are_absent_when_monitor_is_disabled(): void
    {
        config()->set('performance.enabled', false);
        Route::middleware(MeasureRequestPerformance::class)
            ->get('/_test/performance-disabled', fn () => response('disabled'));

        $this->get('/_test/performance-disabled')
            ->assertOk()
            ->assertHeaderMissing('X-Performance-Duration-Ms')
            ->assertHeaderMissing('X-Performance-Query-Count');
    }

    #[Test]
    public function enabled_monitor_reports_request_query_payload_and_memory_metrics(): void
    {
        config()->set('performance.enabled', true);
        config()->set('performance.log', false);
        Route::middleware(MeasureRequestPerformance::class)
            ->get('/_test/performance-enabled', function () {
                DB::select('select 1');

                return response('measured payload');
            });

        $response = $this->get('/_test/performance-enabled')->assertOk();

        $response->assertHeader('X-Performance-Query-Count', '1');
        $this->assertGreaterThanOrEqual(
            0,
            (float) $response->headers->get('X-Performance-Duration-Ms')
        );
        $this->assertGreaterThanOrEqual(
            0,
            (float) $response->headers->get('X-Performance-Query-Time-Ms')
        );
        $this->assertSame(
            strlen('measured payload'),
            (int) $response->headers->get('X-Performance-Payload-Bytes')
        );
        $this->assertStringContainsString(
            'app;dur=',
            (string) $response->headers->get('Server-Timing')
        );
        $this->assertStringContainsString(
            'db;dur=',
            (string) $response->headers->get('Server-Timing')
        );
    }
}
