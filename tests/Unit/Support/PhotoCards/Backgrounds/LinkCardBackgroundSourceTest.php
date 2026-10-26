<?php

namespace Tests\Unit\Support\PhotoCards\Backgrounds;

use App\Models\Post;
use App\Models\ScratchpadEntry;
use App\Models\TelegramBotConfig;
use App\Models\TelegramPostRequest;
use App\Support\Http\SafeRemoteImageFetcher;
use App\Support\PhotoCards\Backgrounds\LinkCardBackgroundSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class LinkCardBackgroundSourceTest extends TestCase
{
    use RefreshDatabase;

    private function fetcher(): SafeRemoteImageFetcher
    {
        return new class extends SafeRemoteImageFetcher
        {
            /** @var list<string> */
            public array $urls = [];

            public function fetch(string $url): ?array
            {
                $this->urls[] = $url;

                return ['bytes' => FakePhotoCardRenderer::png(), 'mime' => 'image/png'];
            }
        };
    }

    private function postFrom(string $kind, array $meta): Post
    {
        $config = TelegramBotConfig::factory()->connected()->create();
        $entry = ScratchpadEntry::factory()->create(['workspace_id' => $config->workspace_id, 'kind' => $kind, 'meta' => $meta]);
        $post = Post::factory()->create(['workspace_id' => $config->workspace_id]);
        TelegramPostRequest::factory()->create([
            'workspace_id' => $config->workspace_id,
            'telegram_bot_config_id' => $config->id,
            'source_scratchpad_entry_id' => $entry->id,
            'post_id' => $post->id,
            'state' => TelegramPostRequest::AWAITING_APPROVAL,
        ]);

        return $post;
    }

    public function test_it_uses_the_source_links_preview_image(): void
    {
        $post = $this->postFrom('link', ['url' => 'https://news.example/a', 'thumbnail_url' => 'https://news.example/a.jpg']);
        $fetcher = $this->fetcher();

        $image = (new LinkCardBackgroundSource($fetcher))->find($post);

        $this->assertSame('link', $image?->source);
        $this->assertSame(['https://news.example/a.jpg'], $fetcher->urls);
        $this->assertSame('https://news.example/a', $image->meta['page']);
    }

    public function test_non_link_sources_and_posts_without_telegram_have_none(): void
    {
        $fetcher = $this->fetcher();

        $this->assertNull((new LinkCardBackgroundSource($fetcher))->find($this->postFrom('text', [])));
        $this->assertNull((new LinkCardBackgroundSource($fetcher))->find(Post::factory()->create()));
        $this->assertSame([], $fetcher->urls);
    }
}
