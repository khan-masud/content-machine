<?php

namespace Tests\Feature\Posts;

use App\Contracts\PhotoCardRenderer;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class PostPhotoCardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwner(): Workspace
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['current_team_id' => $workspace->team->id]);
        $workspace->team->members()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user);

        return $workspace;
    }

    public function test_rendering_saves_the_text_and_the_card_is_served(): void
    {
        Storage::fake('scratchpad');
        $workspace = $this->actingAsOwner();
        $this->app->instance(PhotoCardRenderer::class, new FakePhotoCardRenderer);
        $post = Post::factory()->for($workspace)->create(['photo_card' => null]);

        $this->post(route('posts.card.store', $post), ['headline' => 'নতুন [[শিরোনাম]]', 'source' => 'সোর্স: বিবিসি'])
            ->assertRedirect(route('posts.show', $post));

        $post->refresh();
        $this->assertSame('নতুন [[শিরোনাম]]', $post->photo_card['headline'] ?? null);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.photo_card.headline', 'নতুন [[শিরোনাম]]')
                ->where('post.photo_card.source', 'সোর্স: বিবিসি')
                ->where('post.photo_card.image_url', fn ($url): bool => is_string($url) && str_contains($url, '/card?v=')));

        $this->get(route('posts.card.show', $post))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_a_render_error_comes_back_as_a_form_error(): void
    {
        $workspace = $this->actingAsOwner();
        $this->app->instance(PhotoCardRenderer::class, (new FakePhotoCardRenderer)->failWith('No python.'));
        $post = Post::factory()->for($workspace)->create();

        $this->post(route('posts.card.store', $post), ['headline' => 'X'])
            ->assertSessionHasErrors(['photo_card' => 'No python.']);
    }

    public function test_a_post_without_a_card_or_from_another_workspace_404s(): void
    {
        $workspace = $this->actingAsOwner();
        $mine = Post::factory()->for($workspace)->create(['photo_card' => null]);
        $theirs = Post::factory()->create();

        $this->get(route('posts.card.show', $mine))->assertNotFound();
        $this->get(route('posts.card.show', $theirs))->assertNotFound();
        $this->post(route('posts.card.store', $theirs), ['headline' => 'X'])->assertNotFound();
    }
}
