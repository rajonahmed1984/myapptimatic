<?php

namespace Tests\Unit\Support;

use App\Support\PublicStorageUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicStorageUrlTest extends TestCase
{
    #[Test]
    public function it_normalizes_common_storage_path_variants(): void
    {
        $this->assertSame(
            'avatars/users/1/avatar.png',
            PublicStorageUrl::normalizePath('avatars/users/1/avatar.png')
        );

        $this->assertSame(
            'avatars/users/1/avatar.png',
            PublicStorageUrl::normalizePath('/storage/avatars/users/1/avatar.png')
        );

        $this->assertSame(
            'avatars/users/1/avatar.png',
            PublicStorageUrl::normalizePath('http://127.0.0.1:8000/storage/avatars/users/1/avatar.png')
        );
    }

    #[Test]
    public function it_generates_asset_url_from_relative_or_prefixed_path(): void
    {
        $this->assertStringEndsWith(
            '/storage/avatars/users/1/avatar.png',
            (string) PublicStorageUrl::fromPath('avatars/users/1/avatar.png')
        );

        $this->assertStringEndsWith(
            '/storage/avatars/users/1/avatar.png',
            (string) PublicStorageUrl::fromPath('/storage/avatars/users/1/avatar.png')
        );
    }

    #[Test]
    public function it_returns_null_for_invalid_or_non_public_values(): void
    {
        $this->assertNull(PublicStorageUrl::fromPath(''));
        $this->assertNull(PublicStorageUrl::fromPath('blob:http://localhost/image-123'));
        $this->assertNull(PublicStorageUrl::fromPath('data:image/png;base64,aaaa'));
    }
}
