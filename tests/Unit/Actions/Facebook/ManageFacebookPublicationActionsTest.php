<?php

namespace Tests\Unit\Actions\Facebook;

use App\Actions\Facebook\CancelFacebookPublicationAction;
use App\Actions\Facebook\MarkFacebookPublicationPublishedAction;
use App\Actions\Facebook\RetryFacebookPublicationAction;
use App\Jobs\PublishFacebookPostJob;
use App\Models\FacebookPublication;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManageFacebookPublicationActionsTest extends TestCase
{
    use RefreshDatabase;

    private function publication(string $state, string $postStatus = 'scheduled'): FacebookPublication
    {
        $post = Post::factory()->create(['status' => $postStatus]);

        return FacebookPublication::query()->create([
            'workspace_id' => $post->workspace_id,
            'post_id' => $post->id,
            'page_id' => '1',
            'state' => $state,
            'error' => 'old error',
        ]);
    }

    public function test_cancel_stops_a_scheduled_post_and_returns_it_to_draft(): void
    {
        $publication = $this->publication(FacebookPublication::SCHEDULED);

        (new CancelFacebookPublicationAction)->handle($publication);

        $this->assertSame(FacebookPublication::CANCELLED, $publication->fresh()?->state);
        $this->assertSame('draft', Post::query()->find($publication->post_id)?->status);

        $this->expectException(ValidationException::class);
        (new CancelFacebookPublicationAction)->handle($this->publication(FacebookPublication::PUBLISHING));
    }

    public function test_retry_requeues_a_failure_but_an_uncertain_one_only_when_confirmed(): void
    {
        Queue::fake();
        $failed = $this->publication(FacebookPublication::FAILED);
        (new RetryFacebookPublicationAction)->handle($failed);
        $this->assertSame(FacebookPublication::QUEUED, $failed->fresh()?->state);
        $this->assertNull($failed->fresh()?->error);
        Queue::assertPushed(PublishFacebookPostJob::class, 1);

        $uncertain = $this->publication(FacebookPublication::UNCERTAIN);
        try {
            (new RetryFacebookPublicationAction)->handle($uncertain);
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
            $this->assertSame(FacebookPublication::UNCERTAIN, $uncertain->fresh()?->state);
        }

        (new RetryFacebookPublicationAction)->handle($uncertain, confirmedNotOnPage: true);
        $this->assertSame(FacebookPublication::QUEUED, $uncertain->fresh()?->state);
    }

    public function test_mark_published_settles_an_uncertain_post(): void
    {
        $uncertain = $this->publication(FacebookPublication::UNCERTAIN);

        (new MarkFacebookPublicationPublishedAction)->handle($uncertain, 'https://www.facebook.com/1_2');

        $this->assertSame(FacebookPublication::PUBLISHED, $uncertain->fresh()?->state);
        $this->assertSame('https://www.facebook.com/1_2', $uncertain->fresh()?->permalink);
        $this->assertSame('posted', Post::query()->find($uncertain->post_id)?->status);

        $this->expectException(ValidationException::class);
        (new MarkFacebookPublicationPublishedAction)->handle($this->publication(FacebookPublication::FAILED));
    }
}
