<?php

namespace Tests\Unit\Support\ImageAi;

use App\Contracts\ImageGenerator;
use App\Data\ImageAi\ImagePromptData;
use App\Models\Workspace;
use App\Support\ImageAi\CloudflareImageGenerator;
use App\Support\ImageAi\ImageAiConfig;
use App\Support\ImageAi\ImageGeneratorChain;
use App\Support\ImageAi\PollinationsImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class ImageGeneratorsTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT = '0123456789abcdef0123456789abcdef';

    private function prompt(int $variation = 0): ImagePromptData
    {
        return new ImagePromptData('A quiet street', 1080, 810, $variation);
    }

    public function test_cloudflare_returns_the_decoded_image(): void
    {
        $workspace = Workspace::factory()->create();
        ImageAiConfig::write($workspace, self::ACCOUNT, 'cf-token', false);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'result' => ['image' => FakePhotoCardRenderer::PNG_BASE64]])]);

        $image = (new CloudflareImageGenerator)->generate($workspace->fresh(), $this->prompt());

        $this->assertSame('image/png', $image['mime'] ?? null);
        $this->assertSame('cloudflare', $image['generator']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/accounts/'.self::ACCOUNT.'/ai/run/@cf/black-forest-labs/flux-1-schnell')
            && $request->hasHeader('Authorization', 'Bearer cf-token')
            && $request['prompt'] === 'A quiet street'
            && is_int($request['seed']));
    }

    public function test_cloudflare_is_skipped_when_unconfigured_or_failing(): void
    {
        $workspace = Workspace::factory()->create();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false], 429)]);

        $this->assertNull((new CloudflareImageGenerator)->generate($workspace, $this->prompt()));
        Http::assertNothingSent();

        ImageAiConfig::write($workspace, self::ACCOUNT, 'cf-token', false);
        $this->assertNull((new CloudflareImageGenerator)->generate($workspace->fresh(), $this->prompt()));
    }

    public function test_a_cloudflare_failure_logs_cloudflares_reason(): void
    {
        $workspace = Workspace::factory()->create();
        ImageAiConfig::write($workspace, self::ACCOUNT, 'cf-token', false);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['code' => 10000, 'message' => 'Authentication error']]], 403)]);
        Log::spy();

        $this->assertNull((new CloudflareImageGenerator)->generate($workspace->fresh(), $this->prompt()));

        Log::shouldHaveReceived('warning')->with('Cloudflare image generation failed.', ['status' => 403, 'error' => 'Authentication error']);
    }

    public function test_pollinations_downloads_an_image_and_can_be_turned_off(): void
    {
        $workspace = Workspace::factory()->create();
        Http::fake(['image.pollinations.ai/*' => Http::response(FakePhotoCardRenderer::png(), 200, ['Content-Type' => 'image/png'])]);

        $image = (new PollinationsImageGenerator)->generate($workspace, $this->prompt());

        $this->assertSame('pollinations', $image['generator'] ?? null);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://image.pollinations.ai/prompt/A%20quiet%20street?')
            && str_contains($request->url(), 'width=1080'));

        ImageAiConfig::write($workspace, null, null, false);
        $this->assertNull((new PollinationsImageGenerator)->generate($workspace->fresh(), $this->prompt()));
    }

    public function test_pollinations_rejects_non_image_bytes(): void
    {
        $workspace = Workspace::factory()->create();
        Http::fake(['image.pollinations.ai/*' => Http::response('<html>rate limited</html>', 200)]);

        $this->assertNull((new PollinationsImageGenerator)->generate($workspace, $this->prompt()));
    }

    public function test_the_chain_returns_the_first_image(): void
    {
        $workspace = Workspace::factory()->create();
        $none = new class implements ImageGenerator
        {
            public function generate(Workspace $workspace, ImagePromptData $prompt): ?array
            {
                return null;
            }
        };
        $some = new class implements ImageGenerator
        {
            public function generate(Workspace $workspace, ImagePromptData $prompt): ?array
            {
                return ['bytes' => 'x', 'mime' => 'image/png', 'generator' => 'second'];
            }
        };

        $this->assertSame('second', (new ImageGeneratorChain([$none, $some]))->generate($workspace, $this->prompt())['generator'] ?? null);
        $this->assertNull((new ImageGeneratorChain([$none]))->generate($workspace, $this->prompt()));
    }

    public function test_a_new_variation_changes_the_seed(): void
    {
        $this->assertNotSame($this->prompt(0)->seed(), $this->prompt(1)->seed());
        $this->assertSame($this->prompt(1)->seed(), $this->prompt(1)->seed());
    }
}
