<?php

namespace Tests\Feature\Settings;

use App\Contracts\FacebookPageClient;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Facebook\FacebookConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Facebook\FakeFacebookPageClient;
use Tests\TestCase;

class FacebookSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function member(Workspace $workspace, string $role = 'owner'): void
    {
        $user = User::factory()->create(['current_team_id' => $workspace->team->id]);
        $workspace->team->members()->attach($user->id, ['role' => $role]);
        $this->actingAs($user);
    }

    public function test_an_admin_connects_a_page_and_can_disconnect_it(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace);
        $this->app->instance(FacebookPageClient::class, new FakeFacebookPageClient);

        $this->post('/settings/facebook', [
            'page_id' => '1234567890',
            'page_access_token' => 'tok',
            'publish_enabled' => '1',
            'caption_language' => 'bn',
        ])->assertRedirect(route('settings.facebook.edit'));

        $this->assertTrue(FacebookConfig::fromWorkspace($workspace->fresh())->isReady());
        $this->get(route('settings.facebook.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('workspace-settings/facebook')
                ->where('pageName', 'Amar Page')
                ->where('connected', true)
                ->missing('pageAccessToken'));

        $this->delete('/settings/facebook')->assertRedirect(route('settings.facebook.edit'));
        $this->assertFalse(FacebookConfig::fromWorkspace($workspace->fresh())->isConnected());
    }

    public function test_bad_input_and_members_are_refused(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace);
        $this->post('/settings/facebook', ['page_id' => 'abc', 'caption_language' => 'fr'])
            ->assertSessionHasErrors(['page_id', 'caption_language']);

        $other = Workspace::factory()->create();
        $this->member($other, 'member');
        $this->post('/settings/facebook', ['page_id' => '1234567890', 'caption_language' => 'bn'])->assertForbidden();
    }
}
