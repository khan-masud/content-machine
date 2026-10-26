<?php

namespace Tests\Unit\Support\Facebook;

use App\Support\Facebook\HttpFacebookPageClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpFacebookPageClientTest extends TestCase
{
    public function test_page_reads_id_and_name(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '123', 'name' => 'Amar Page'])]);

        $result = (new HttpFacebookPageClient)->page('123', 'tok');

        $this->assertTrue($result->successful);
        $this->assertSame('Amar Page', $result->name);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://graph.facebook.com/v23.0/123?')
            && str_contains($request->url(), 'fields=id%2Cname'));
    }

    public function test_publish_photo_uploads_the_image_to_the_page(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '999', 'post_id' => '123_456'])]);

        $result = (new HttpFacebookPageClient)->publishPhoto('123', 'tok', 'PNGBYTES', 'P-1.png', 'ক্যাপশন');

        $this->assertSame('123_456', $result->postId);
        Http::assertSent(function ($request): bool {
            $parts = collect($request->data())->keyBy('name');

            return $request->url() === 'https://graph.facebook.com/v23.0/123/photos'
                && $request->isMultipart()
                && ($parts['source']['contents'] ?? null) === 'PNGBYTES'
                && ($parts['caption']['contents'] ?? null) === 'ক্যাপশন'
                && ($parts['access_token']['contents'] ?? null) === 'tok';
        });
    }

    public function test_a_4xx_is_a_definite_failure(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => '(#200) Permissions error']], 403)]);

        $rejected = (new HttpFacebookPageClient)->publishText('123', 'tok', 'hi');

        $this->assertFalse($rejected->successful);
        $this->assertFalse($rejected->outcomeUnknown);
        $this->assertSame('(#200) Permissions error', $rejected->error);
    }

    public function test_a_5xx_publish_is_uncertain(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response('', 502)]);

        $this->assertTrue((new HttpFacebookPageClient)->publishText('123', 'tok', 'hi')->outcomeUnknown);
    }

    public function test_a_lost_connection_is_uncertain_for_publishes_only(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertTrue((new HttpFacebookPageClient)->publishText('123', 'tok', 'hi')->outcomeUnknown);
        $this->assertFalse((new HttpFacebookPageClient)->comment('1_2', 'tok', 'hi')->outcomeUnknown);
    }
}
