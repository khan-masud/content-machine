<?php

namespace Tests\Feature\Posts;

use App\Jobs\PublishFacebookPostJob;
use App\Models\FacebookPublication;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Facebook\FakeFacebookPageClient;
use Tests\TestCase;

class PostFacebookPublicationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): Workspace
    {
        $workspace = Workspace::factory()->create(['timezone' => 'Asia/Dhaka']);
        $user = User::factory()->create(['current_team_id' => $workspace->team->id]);
        $workspace->team->members()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user);
        FakeFacebookPageClient::connect($workspace);

        return $workspace;
    }

    public function test_schedule_in_the_workspace_timezone_then_cancel(): void
    {
        Queue::fake();
        $workspace = $this->owner();
        $post = Post::factory()->for($workspace)->create(['approval_state' => 'approved']);
        $local = now('Asia/Dhaka')->addDay()->format('Y-m-d\TH:i');

        $this->post(route('posts.facebook.store', $post), ['when' => $local])->assertRedirect(route('posts.show', $post));

        $publication = FacebookPublication::query()->sole();
        $this->assertSame(FacebookPublication::SCHEDULED, $publication->state);
        $this->assertSame($local, $publication->scheduled_for?->setTimezone('Asia/Dhaka')->format('Y-m-d\TH:i'));

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.facebook.ready', true)
                ->where('post.facebook.page_name', 'Amar Page')
                ->where('post.facebook.publication.state', 'scheduled'));

        $this->post(route('posts.facebook.cancel', [$post, $publication]))->assertRedirect(route('posts.show', $post));
        $this->assertSame(FacebookPublication::CANCELLED, $publication->fresh()?->state);
    }

    public function test_publish_now_queues_and_an_unapproved_post_is_refused(): void
    {
        Queue::fake();
        $workspace = $this->owner();
        $post = Post::factory()->for($workspace)->create(['approval_state' => 'approved']);
        $draft = Post::factory()->for($workspace)->create(['approval_state' => 'pending']);

        $this->post(route('posts.facebook.store', $post))->assertRedirect(route('posts.show', $post));
        Queue::assertPushed(PublishFacebookPostJob::class);

        $this->post(route('posts.facebook.store', $draft))->assertSessionHasErrors('facebook');
    }

    public function test_uncertain_retry_needs_confirmation_and_mark_published_settles_it(): void
    {
        Queue::fake();
        $workspace = $this->owner();
        $post = Post::factory()->for($workspace)->create(['approval_state' => 'approved']);
        $publication = FacebookPublication::query()->create([
            'workspace_id' => $workspace->id,
            'post_id' => $post->id,
            'page_id' => FakeFacebookPageClient::PAGE_ID,
            'state' => FacebookPublication::UNCERTAIN,
        ]);

        $this->post(route('posts.facebook.retry', [$post, $publication]))->assertSessionHasErrors('facebook');
        $this->post(route('posts.facebook.mark-published', [$post, $publication]))->assertRedirect(route('posts.show', $post));

        $this->assertSame(FacebookPublication::PUBLISHED, $publication->fresh()?->state);
    }

    public function test_a_publication_of_another_post_404s(): void
    {
        $workspace = $this->owner();
        $post = Post::factory()->for($workspace)->create();
        $other = Post::factory()->for($workspace)->create();
        $publication = FacebookPublication::query()->create([
            'workspace_id' => $workspace->id,
            'post_id' => $other->id,
            'page_id' => '1',
            'state' => FacebookPublication::SCHEDULED,
        ]);

        $this->post(route('posts.facebook.cancel', [$post, $publication]))->assertNotFound();
    }
}
