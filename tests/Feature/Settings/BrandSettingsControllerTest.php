<?php

namespace Tests\Feature\Settings;

use App\Contracts\PhotoCardRenderer;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Brand\BrandConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class BrandSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function member(Workspace $workspace, string $role = 'owner'): User
    {
        $team = $workspace->team;
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->members()->attach($user->id, ['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_page_shows_defaults_fonts_and_category_styles(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Amar Page']);
        $this->member($workspace);

        $this->get(route('settings.brand.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('workspace-settings/brand')
                ->where('brand.primary_color', '#0E7490')
                ->where('brand.has_logo', false)
                ->where('placeholders.name', 'Amar Page')
                ->has('fonts', 2)
                ->has('categories', 6)
                ->where('categories.4.key', 'sarcasm')
                ->where('categories.4.theme', 'dark'));
    }

    public function test_an_admin_saves_the_brand(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace);

        $this->post('/settings/brand', [
            'name' => 'Amar Page',
            'website' => 'amarpage.com',
            'primary_color' => '#112233',
            'font' => 'anek-bangla',
            'image_label' => 'Concept Image',
            'card_language' => 'en',
            'categories' => ['techy' => ['theme' => 'light', 'accent' => '#123456']],
        ])->assertRedirect(route('settings.brand.edit'));

        $brand = BrandConfig::fromWorkspace($workspace->fresh());
        $this->assertSame('Amar Page', $brand->name());
        $this->assertSame('#112233', $brand->primaryColor());
        $this->assertSame('en', $brand->cardLanguage());
        $this->assertSame(['theme' => 'light', 'accent' => '#123456'], $brand->categoryStyle('techy'));
    }

    public function test_invalid_input_is_rejected(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace);

        $this->post('/settings/brand', [
            'primary_color' => 'blue',
            'font' => 'comic-sans',
            'card_language' => 'fr',
            'categories' => ['gossip' => ['theme' => 'light', 'accent' => '#123456']],
        ])->assertSessionHasErrors(['primary_color', 'font', 'card_language', 'categories']);
    }

    public function test_a_member_cannot_change_the_brand(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace, 'member');

        $this->post('/settings/brand', ['primary_color' => '#112233', 'font' => 'hind-siliguri'])->assertForbidden();
        $this->get(route('settings.brand.edit'))->assertForbidden();
    }

    public function test_the_preview_returns_a_png_or_the_render_error(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace);
        $renderer = new FakePhotoCardRenderer;
        $this->app->instance(PhotoCardRenderer::class, $renderer);

        $this->get(route('settings.brand.preview', ['category' => 'politics']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->assertSame('#DC2626', $renderer->lastCard?->colors['accent']);

        $renderer->failWith('No libraqm.');
        $this->get(route('settings.brand.preview'))->assertStatus(422)->assertSee('No libraqm.');
    }
}
