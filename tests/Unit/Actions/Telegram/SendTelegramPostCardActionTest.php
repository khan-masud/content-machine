<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendTelegramPostCardAction;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\TelegramBotConfig;
use App\Models\TelegramOutboundMessage;
use App\Models\TelegramPostRequest;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\Support\Telegram\FakeTelegramClient;
use Tests\TestCase;

class SendTelegramPostCardActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{post: Post, request: TelegramPostRequest}
     */
    private function telegramDraft(bool $withCard = true): array
    {
        Storage::fake('scratchpad');
        $workspace = Workspace::factory()->create();
        $config = TelegramBotConfig::factory()->connected()->create(['workspace_id' => $workspace->id]);
        $card = null;
        if ($withCard) {
            Storage::disk('scratchpad')->put('1/card.png', FakePhotoCardRenderer::png());
            $media = MediaAsset::factory()->for($workspace)->create(['disk' => 'scratchpad', 'path' => '1/card.png', 'mime' => 'image/png']);
            $card = ['headline' => 'H', 'media_asset_id' => $media->id];
        }
        $post = Post::factory()->for($workspace)->create(['photo_card' => $card]);
        $request = TelegramPostRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'telegram_bot_config_id' => $config->id,
            'post_id' => $post->id,
            'telegram_chat_id' => 555,
            'state' => TelegramPostRequest::AWAITING_APPROVAL,
        ]);

        return ['post' => $post, 'request' => $request];
    }

    public function test_it_sends_the_card_to_the_draft_chat(): void
    {
        ['post' => $post] = $this->telegramDraft();
        $client = new FakeTelegramClient;

        $this->assertTrue((new SendTelegramPostCardAction($client))->handle($post));

        $this->assertCount(1, $client->sentPhotos);
        $this->assertSame(555, $client->sentPhotos[0]['chatId']);
        $this->assertSame($post->human_id.'-card.png', $client->sentPhotos[0]['filename']);
        $this->assertStringContainsString('/approve '.$post->human_id, (string) $client->sentPhotos[0]['caption']);
    }

    public function test_a_render_error_is_queued_as_a_text_reply(): void
    {
        Queue::fake();
        ['post' => $post] = $this->telegramDraft(withCard: false);
        $client = new FakeTelegramClient;

        $this->assertTrue((new SendTelegramPostCardAction($client))->handle($post, 'no python'));

        $this->assertSame([], $client->sentPhotos);
        $this->assertStringContainsString('no python', TelegramOutboundMessage::query()->sole()->chunks[0]);
    }

    public function test_a_post_without_a_telegram_request_is_skipped(): void
    {
        $post = Post::factory()->create();
        $client = new FakeTelegramClient;

        $this->assertFalse((new SendTelegramPostCardAction($client))->handle($post));
        $this->assertSame([], $client->sentPhotos);
    }
}
