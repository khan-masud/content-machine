<?php

namespace Tests\Unit\Support\ImageAi;

use App\Actions\ImageAi\SaveImageAiSettingsAction;
use App\Data\ImageAi\ImageAiSettingsData;
use App\Models\Workspace;
use App\Support\ImageAi\ImageAiConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageAiConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_allow_pollinations_and_no_cloudflare(): void
    {
        $config = ImageAiConfig::fromWorkspace(Workspace::factory()->create(['settings' => []]));

        $this->assertFalse($config->cloudflareConfigured());
        $this->assertTrue($config->pollinationsEnabled());
    }

    public function test_the_token_is_encrypted_kept_when_omitted_and_cleared_with_the_account(): void
    {
        $workspace = Workspace::factory()->create();
        $action = new SaveImageAiSettingsAction;

        $action->handle($workspace, new ImageAiSettingsData(str_repeat('a', 32), 'secret-token', true));
        $this->assertNotSame('secret-token', $workspace->fresh()?->settings['image_ai']['cloudflare_api_token'] ?? null);
        $this->assertSame('secret-token', ImageAiConfig::fromWorkspace($workspace->fresh())->cloudflareApiToken());

        $action->handle($workspace, new ImageAiSettingsData(str_repeat('a', 32), null, false));
        $config = ImageAiConfig::fromWorkspace($workspace->fresh());
        $this->assertTrue($config->cloudflareConfigured());
        $this->assertFalse($config->pollinationsEnabled());

        $action->handle($workspace, new ImageAiSettingsData(null, null, true));
        $this->assertFalse(ImageAiConfig::fromWorkspace($workspace->fresh())->cloudflareConfigured());
    }
}
