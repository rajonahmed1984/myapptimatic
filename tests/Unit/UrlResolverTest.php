<?php

namespace Tests\Unit;

use App\Support\UrlResolver;
use PHPUnit\Framework\TestCase;

class UrlResolverTest extends TestCase
{
    public function test_normalize_root_url_strips_path_and_keeps_host_only(): void
    {
        $this->assertSame(
            'https://example.com',
            UrlResolver::normalizeRootUrl('https://example.com/admin/login')
        );
    }

    public function test_normalize_root_url_keeps_port_when_present(): void
    {
        $this->assertSame(
            'http://example.com:8080',
            UrlResolver::normalizeRootUrl('http://example.com:8080/employee/login')
        );
    }

    public function test_normalize_root_url_returns_null_for_invalid_values(): void
    {
        $this->assertNull(UrlResolver::normalizeRootUrl(''));
        $this->assertNull(UrlResolver::normalizeRootUrl('example.com/admin'));
        $this->assertNull(UrlResolver::normalizeRootUrl('ftp://example.com/path'));
    }
}
