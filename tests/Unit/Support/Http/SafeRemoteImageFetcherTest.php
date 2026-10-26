<?php

namespace Tests\Unit\Support\Http;

use App\Support\Http\SafeRemoteImageFetcher;
use App\Support\LinkResolution\PublicUrlGuard;
use Illuminate\Support\Facades\Http;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class SafeRemoteImageFetcherTest extends TestCase
{
    private function fetcher(string $ip = '93.184.216.34'): SafeRemoteImageFetcher
    {
        return new SafeRemoteImageFetcher(new PublicUrlGuard(fn (string $host): array => [['ip' => $ip]]));
    }

    public function test_it_downloads_a_public_image(): void
    {
        Http::fake(['news.example/*' => Http::response(FakePhotoCardRenderer::png(), 200)]);

        $this->assertSame('image/png', $this->fetcher()->fetch('https://news.example/a.png')['mime'] ?? null);
    }

    public function test_it_refuses_private_addresses_and_non_images(): void
    {
        Http::fake(['news.example/*' => Http::response('<html></html>', 200)]);

        $this->assertNull($this->fetcher('10.0.0.5')->fetch('https://news.example/a.png'));
        $this->assertNull($this->fetcher()->fetch('https://news.example/page'));
        $this->assertNull($this->fetcher()->fetch('file:///etc/passwd'));
    }

    public function test_redirects_are_checked_again(): void
    {
        Http::fake([
            'news.example/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret.png']),
        ]);

        $this->assertNull($this->fetcher()->fetch('https://news.example/a.png'));
    }
}
