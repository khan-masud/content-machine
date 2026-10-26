<?php

namespace Tests\Unit\Actions\Facebook;

use App\Actions\Facebook\PublishFacebookPostAction;
use App\Data\Facebook\FacebookApiResult;
use App\Models\FacebookPublication;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\TelegramBotConfig;
use App\Models\TelegramOutboundMessage;
use App\Models\TelegramPostRequest;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Facebook\FakeFacebookPageClient;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class PublishFacebookPostActionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private FakeFacebookPageClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('scratchpad');
        $this->workspace = Workspace::factory()->create();
        FakeFacebookPageClient::connect($this->workspace);
        $this->client = new FakeFacebookPageClient;
    }

    private function captions(): array
    {
        return [
            ['part' => 'English', 'lang' => 'en', 'platforms' => [['name' => 'facebook', 'title' => 'T', 'caption' => 'English caption', 'first_comment' => 'Source: x', 'images' => [], 'thread' => []]]],
            ['part' => 'Bangla', 'lang' => 'bn', 'platforms' => [['name' => 'facebook', 'title' => 'T', 'caption' => 'বাংলা ক্যাপশন', 'first_comment' => 'সোর্স: প্রথম আলো', 'images' => [], 'thread' => []]]],
        ];
    }

    private function queued(array $postAttributes = [], bool $withCard = true): FacebookPublication
    {
        $card = null;
        if ($withCard) {
            Storage::disk('scratchpad')->put('1/card.png', FakePhotoCardRenderer::png());
            $media = MediaAsset::factory()->for($this->workspace)->create(['disk' => 'scratchpad', 'path' => '1/card.png', 'mime' => 'image/png']);
            $card = ['media_asset_id' => $media->id];
        }

        $post = Post::factory()->for($this->workspace)->create(array_merge([
            'approval_state' => 'approved',
            'status' => 'scheduled',
            'captions' => $this->captions(),
            'photo_card' => $card,
        ], $postAttributes));

        return FacebookPublication::query()->create([
            'workspace_id' => $this->workspace->id,
            'post_id' => $post->id,
            'page_id' => FakeFacebookPageClient::PAGE_ID,
            'state' => FacebookPublication::QUEUED,
        ]);
    }

    public function test_it_posts_the_card_with_the_page_language_caption_then_the_first_comment(): void
    {
        $publication = $this->queued();

        $result = (new PublishFacebookPostAction($this->client))->handle($publication->id);

        $this->assertSame(['photo', 'comment'], $this->client->methods());
        $this->assertSame('বাংলা ক্যাপশন', $this->client->calls[0]['caption']);
        $this->assertSame('page-token', $this->client->calls[0]['token']);
        $this->assertSame('123_456', $this->client->calls[1]['object_id']);
        $this->assertSame('সোর্স: প্রথম আলো', $this->client->calls[1]['message']);
        $this->assertSame(FacebookPublication::PUBLISHED, $result?->state);
        $this->assertSame('https://www.facebook.com/123_456', $result->permalink);
        $this->assertSame('posted', Post::query()->find($publication->post_id)?->status);
    }

    public function test_without_any_image_it_posts_text(): void
    {
        $publication = $this->queued(withCard: false);

        (new PublishFacebookPostAction($this->client))->handle($publication->id);

        $this->assertSame('text', $this->client->methods()[0]);
        $this->assertSame('123_789', $publication->fresh()?->facebook_post_id);
    }

    public function test_a_rejected_post_fails_and_a_lost_answer_is_uncertain(): void
    {
        $failed = $this->queued();
        $this->client->publishResult = FacebookApiResult::failure('(#200) Permissions error');
        (new PublishFacebookPostAction($this->client))->handle($failed->id);
        $this->assertSame(FacebookPublication::FAILED, $failed->fresh()?->state);
        $this->assertSame('(#200) Permissions error', $failed->fresh()?->error);

        $lost = $this->queued();
        $this->client->publishResult = FacebookApiResult::failure('timeout', outcomeUnknown: true);
        (new PublishFacebookPostAction($this->client))->handle($lost->id);
        $this->assertSame(FacebookPublication::UNCERTAIN, $lost->fresh()?->state);
        $this->assertStringContainsString('Check the Page', (string) $lost->fresh()?->error);
    }

    public function test_an_edited_post_is_held_back_without_calling_facebook(): void
    {
        $publication = $this->queued(['approval_state' => 'pending']);

        (new PublishFacebookPostAction($this->client))->handle($publication->id);

        $this->assertSame([], $this->client->calls);
        $this->assertSame(FacebookPublication::FAILED, $publication->fresh()?->state);
        $this->assertStringContainsString('Approve it', (string) $publication->fresh()?->error);
    }

    public function test_only_a_queued_publication_is_sent_once(): void
    {
        $publication = $this->queued();
        $action = new PublishFacebookPostAction($this->client);

        $action->handle($publication->id);
        $this->assertNull($action->handle($publication->id));

        $this->assertSame(['photo', 'comment'], $this->client->methods());
    }

    public function test_a_failed_first_comment_keeps_the_post_published(): void
    {
        $publication = $this->queued();
        $this->client->commentResult = FacebookApiResult::failure('comment blocked');

        (new PublishFacebookPostAction($this->client))->handle($publication->id);

        $this->assertSame(FacebookPublication::PUBLISHED, $publication->fresh()?->state);
        $this->assertSame('comment blocked', $publication->fresh()?->comment_error);
    }

    public function test_telegram_drafts_are_marked_published_and_told(): void
    {
        $publication = $this->queued();
        $config = TelegramBotConfig::factory()->connected()->create(['workspace_id' => $this->workspace->id]);
        $request = TelegramPostRequest::factory()->create([
            'workspace_id' => $this->workspace->id,
            'telegram_bot_config_id' => $config->id,
            'post_id' => $publication->post_id,
            'telegram_chat_id' => 555,
            'state' => TelegramPostRequest::APPROVED,
        ]);
        $publication->forceFill(['telegram_post_request_id' => $request->id])->save();

        (new PublishFacebookPostAction($this->client))->handle($publication->id);

        $this->assertSame(TelegramPostRequest::PUBLISHED, $request->fresh()?->state);
        $this->assertStringContainsString('live on Facebook: https://www.facebook.com/123_456', TelegramOutboundMessage::query()->sole()->chunks[0]);
    }
}
