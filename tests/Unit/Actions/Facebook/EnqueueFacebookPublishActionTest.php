<?php

namespace Tests\Unit\Actions\Facebook;

use App\Actions\Facebook\EnqueueFacebookPublishAction;
use App\Jobs\PublishFacebookPostJob;
use App\Models\FacebookPublication;
use App\Models\Post;
use App\Models\TelegramBotConfig;
use App\Models\TelegramPostRequest;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\Facebook\FakeFacebookPageClient;
use Tests\TestCase;

class EnqueueFacebookPublishActionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->workspace = Workspace::factory()->create();
        FakeFacebookPageClient::connect($this->workspace);
    }

    private function approvedPost(array $attributes = []): Post
    {
        return Post::factory()->for($this->workspace)->create(array_merge(['approval_state' => 'approved', 'status' => 'draft'], $attributes));
    }

    private function assertRefused(callable $call, string $field = 'facebook'): void
    {
        try {
            $call();
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_publish_now_queues_a_job(): void
    {
        $post = $this->approvedPost();

        $publication = (new EnqueueFacebookPublishAction)->handle($post, $this->workspace);

        $this->assertSame(FacebookPublication::QUEUED, $publication->state);
        $this->assertSame(FakeFacebookPageClient::PAGE_ID, $publication->page_id);
        Queue::assertPushed(PublishFacebookPostJob::class, fn (PublishFacebookPostJob $job): bool => $job->publicationId === $publication->id);
    }

    public function test_a_time_schedules_without_a_job_and_marks_the_post_scheduled(): void
    {
        $post = $this->approvedPost();
        $when = CarbonImmutable::now()->addHour();

        $publication = (new EnqueueFacebookPublishAction)->handle($post, $this->workspace, $when);

        $this->assertSame(FacebookPublication::SCHEDULED, $publication->state);
        $this->assertTrue($publication->scheduled_for?->equalTo($when->startOfSecond()));
        $this->assertSame('scheduled', $post->fresh()?->status);
        Queue::assertNotPushed(PublishFacebookPostJob::class);
    }

    public function test_it_refuses_unready_unapproved_duplicate_and_past(): void
    {
        $action = new EnqueueFacebookPublishAction;

        $this->assertRefused(fn () => $action->handle($this->approvedPost(['approval_state' => 'pending']), $this->workspace));
        $this->assertRefused(fn () => $action->handle($this->approvedPost(), $this->workspace, CarbonImmutable::now()->subMinute()), 'when');

        $post = $this->approvedPost();
        $action->handle($post, $this->workspace);
        $this->assertRefused(fn () => $action->handle($post, $this->workspace));

        $published = $this->approvedPost();
        FacebookPublication::query()->create(['workspace_id' => $this->workspace->id, 'post_id' => $published->id, 'page_id' => '1', 'state' => FacebookPublication::PUBLISHED]);
        $this->assertRefused(fn () => $action->handle($published, $this->workspace));

        FakeFacebookPageClient::connect($this->workspace, enabled: false);
        $this->assertRefused(fn () => $action->handle($this->approvedPost(), $this->workspace));
    }

    public function test_a_failed_telegram_request_is_restarted_and_linked(): void
    {
        $post = $this->approvedPost();
        $config = TelegramBotConfig::factory()->connected()->create(['workspace_id' => $this->workspace->id]);
        $request = TelegramPostRequest::factory()->create([
            'workspace_id' => $this->workspace->id,
            'telegram_bot_config_id' => $config->id,
            'post_id' => $post->id,
            'state' => TelegramPostRequest::FAILED,
        ]);

        $publication = (new EnqueueFacebookPublishAction)->handle($post, $this->workspace, null, null, $request->id);

        $this->assertSame($request->id, $publication->telegram_post_request_id);
        $this->assertSame(TelegramPostRequest::APPROVED, $request->fresh()?->state);
    }
}
