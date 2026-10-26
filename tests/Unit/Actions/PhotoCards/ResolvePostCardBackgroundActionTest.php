<?php

namespace Tests\Unit\Actions\PhotoCards;

use App\Actions\PhotoCards\ResolvePostCardBackgroundAction;
use App\Contracts\CardBackgroundSource;
use App\Data\PhotoCards\CardBackgroundImage;
use App\Models\Attachment;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Support\PhotoCards\Backgrounds\CardBackgroundSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class ResolvePostCardBackgroundActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('scratchpad');
    }

    /**
     * A source that answers with the given suffix (distinct bytes) or null,
     * and records the variations it was asked for.
     */
    private function source(?string $suffix, string $key = 'ai'): CardBackgroundSource
    {
        return new class($suffix, $key) implements CardBackgroundSource
        {
            /** @var list<int> */
            public array $variations = [];

            public function __construct(private readonly ?string $suffix, private readonly string $key) {}

            public function find(Post $post, int $variation = 0): ?CardBackgroundImage
            {
                $this->variations[] = $variation;

                return $this->suffix === null
                    ? null
                    : new CardBackgroundImage(FakePhotoCardRenderer::png().$this->suffix, 'image/png', $this->key, ['k' => $this->key]);
            }
        };
    }

    public function test_the_first_source_with_an_image_wins_and_is_stored(): void
    {
        $post = Post::factory()->create(['category' => null, 'photo_card' => ['headline' => 'Keep']]);
        $empty = $this->source(null);
        $link = $this->source('link', 'link');

        $resolved = (new ResolvePostCardBackgroundAction(new CardBackgroundSources(['ai' => $empty, 'link' => $link])))->handle($post);

        $card = $resolved->photo_card ?? [];
        $this->assertSame('Keep', $card['headline']);
        $this->assertSame('link', $card['background_source']);
        $this->assertSame(['k' => 'link'], $card['background_meta']);
        $media = MediaAsset::query()->findOrFail($card['background_media_asset_id']);
        $this->assertSame($post->human_id.'-background.png', $media->original_filename);
        Storage::disk('scratchpad')->assertExists($media->path);
    }

    public function test_a_post_with_its_own_photo_is_left_alone(): void
    {
        $post = Post::factory()->create(['photo_card' => null]);
        Attachment::factory()->create([
            'attachable_type' => $post->getMorphClass(),
            'attachable_id' => $post->id,
            'role' => 'image',
        ]);
        $ai = $this->source('ai');

        (new ResolvePostCardBackgroundAction(new CardBackgroundSources(['ai' => $ai])))->handle($post);

        $this->assertSame([], $ai->variations);
        $this->assertNull($post->fresh()?->photo_card);
    }

    public function test_an_existing_background_is_kept_unless_refreshed(): void
    {
        $post = Post::factory()->create(['category' => null, 'photo_card' => null]);
        $ai = $this->source('first');
        $action = new ResolvePostCardBackgroundAction(new CardBackgroundSources(['ai' => $ai]));

        $post = $action->handle($post);
        $firstId = $post->photo_card['background_media_asset_id'] ?? null;
        $action->handle($post);

        $this->assertSame([0], $ai->variations);
        $this->assertSame($firstId, $post->fresh()?->photo_card['background_media_asset_id'] ?? null);
    }

    public function test_refresh_asks_for_the_next_variation_and_skips_the_same_image(): void
    {
        $post = Post::factory()->create(['category' => null, 'photo_card' => null]);
        $same = $this->source('same', 'link');
        $action = new ResolvePostCardBackgroundAction(new CardBackgroundSources(['link' => $same]));
        $post = $action->handle($post);

        $other = $this->source('other', 'pexels');
        $refreshed = (new ResolvePostCardBackgroundAction(new CardBackgroundSources(['link' => $same, 'pexels' => $other])))
            ->handle($post, refresh: true);

        $this->assertSame([0, 1], $same->variations);
        $this->assertSame([1], $other->variations);
        $this->assertSame('pexels', $refreshed->photo_card['background_source'] ?? null);
        $this->assertSame(1, $refreshed->photo_card['background_variation'] ?? null);
    }

    public function test_no_image_anywhere_leaves_the_card_text_only(): void
    {
        $post = Post::factory()->create(['photo_card' => ['headline' => 'Only text']]);

        $resolved = (new ResolvePostCardBackgroundAction(new CardBackgroundSources(['ai' => $this->source(null)])))->handle($post);

        $this->assertSame(['headline' => 'Only text'], $resolved->photo_card);
    }

    public function test_it_refuses_while_publishing(): void
    {
        $post = Post::factory()->create(['publish_state' => 'queued', 'photo_card' => null]);

        $this->expectException(ValidationException::class);

        (new ResolvePostCardBackgroundAction(new CardBackgroundSources(['ai' => $this->source('x')])))->handle($post);
    }
}
