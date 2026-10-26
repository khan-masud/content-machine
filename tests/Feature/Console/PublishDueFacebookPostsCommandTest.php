<?php

namespace Tests\Feature\Console;

use App\Jobs\PublishFacebookPostJob;
use App\Models\FacebookPublication;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishDueFacebookPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function publication(array $attributes): FacebookPublication
    {
        $post = Post::factory()->create();

        $publication = FacebookPublication::query()->create(array_merge([
            'workspace_id' => $post->workspace_id,
            'post_id' => $post->id,
            'page_id' => '1',
        ], $attributes));

        if (isset($attributes['updated_at'])) {
            FacebookPublication::query()->whereKey($publication->id)->update(['updated_at' => $attributes['updated_at']]);
        }

        return $publication;
    }

    public function test_due_and_lost_posts_are_dispatched_and_stuck_ones_become_uncertain(): void
    {
        Queue::fake();
        $due = $this->publication(['state' => FacebookPublication::SCHEDULED, 'scheduled_for' => now()->subMinute()]);
        $future = $this->publication(['state' => FacebookPublication::SCHEDULED, 'scheduled_for' => now()->addHour()]);
        $lost = $this->publication(['state' => FacebookPublication::QUEUED, 'updated_at' => now()->subMinutes(30)]);
        $fresh = $this->publication(['state' => FacebookPublication::QUEUED]);
        $stuck = $this->publication(['state' => FacebookPublication::PUBLISHING, 'claimed_at' => now()->subMinutes(30)]);

        $this->artisan('facebook:publish-due')->assertSuccessful();

        $this->assertSame(FacebookPublication::QUEUED, $due->fresh()?->state);
        $this->assertSame(FacebookPublication::SCHEDULED, $future->fresh()?->state);
        $this->assertSame(FacebookPublication::UNCERTAIN, $stuck->fresh()?->state);
        $dispatched = [];
        Queue::assertPushed(PublishFacebookPostJob::class, function (PublishFacebookPostJob $job) use (&$dispatched): bool {
            $dispatched[] = $job->publicationId;

            return true;
        });
        sort($dispatched);
        $this->assertSame([$due->id, $lost->id], $dispatched);
        $this->assertNotContains($fresh->id, $dispatched);
    }
}
