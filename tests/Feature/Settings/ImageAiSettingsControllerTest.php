<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\Workspace;
use App\Support\ImageAi\ImageAiConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageAiSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function member(Workspace $workspace, string $role = 'owner'): void
    {
        $user = User::factory()->create(['current_team_id' => $workspace->team->id]);
        $workspace->team->members()->attach($user->id, ['role' => $role]);
        $this->actingAs($user);
    }

    public function test_an_admin_saves_cloudflare_without_the_token_coming_back(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace);
        $account = str_repeat('b', 32);

        $this->post('/settings/image-ai', [
            'cloudflare_account_id' => $account,
            'cloudflare_api_token' => 'cf-secret',
            'pollinations_enabled' => '0',
        ])->assertRedirect(route('settings.image-ai.edit'));

        $config = ImageAiConfig::fromWorkspace($workspace->fresh());
        $this->assertSame('cf-secret', $config->cloudflareApiToken());
        $this->assertFalse($config->pollinationsEnabled());

        $this->get(route('settings.image-ai.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('workspace-settings/image-ai')
                ->where('cloudflareAccountId', $account)
                ->where('cloudflareConfigured', true)
                ->where('pollinationsEnabled', false)
                ->missing('cloudflareApiToken'));
    }

    public function test_a_bad_account_id_is_rejected_and_members_are_forbidden(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace);
        $this->post('/settings/image-ai', ['cloudflare_account_id' => 'nope'])->assertSessionHasErrors('cloudflare_account_id');

        $other = Workspace::factory()->create();
        $this->member($other, 'member');
        $this->post('/settings/image-ai', ['pollinations_enabled' => '1'])->assertForbidden();
    }
}
