<?php

namespace Tests\Unit\Actions\PhotoCards;

use App\Actions\PhotoCards\RenderPostPhotoCardAction;
use App\Data\PhotoCards\PostPhotoCardTextData;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Support\PhotoCards\PhotoCardComposer;
use App\Support\PhotoCards\PhotoCardRenderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class RenderPostPhotoCardActionTest extends TestCase
{
    use RefreshDatabase;

    private FakePhotoCardRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('scratchpad');
        $this->renderer = (new FakePhotoCardRenderer)->uniquePerRender();
    }

    private function action(): RenderPostPhotoCardAction
    {
        return new RenderPostPhotoCardAction(new PhotoCardComposer, $this->renderer);
    }

    public function test_it_stores_the_card_and_references_it_from_the_post(): void
    {
        $post = Post::factory()->create([
            'approval_state' => 'pending',
            'photo_card' => ['headline' => 'Old [[headline]]', 'source' => null],
        ]);

        $rendered = $this->action()->handle($post);

        $card = $rendered->photo_card;
        $this->assertIsArray($card);
        $this->assertSame('Old [[headline]]', $card['headline']);
        $media = MediaAsset::query()->findOrFail($card['media_asset_id']);
        $this->assertSame('image/png', $media->mime);
        $this->assertSame($post->workspace_id, $media->workspace_id);
        $this->assertSame($post->human_id.'-card.png', $media->original_filename);
        Storage::disk('scratchpad')->assertExists($media->path);
        $this->assertSame(0, $post->attachments()->count());
        $this->assertSame('Old [[headline]]', $this->renderer->lastCard?->headline);
    }

    public function test_new_text_replaces_the_card_text_and_a_new_card_needs_approval_again(): void
    {
        $post = Post::factory()->create(['approval_state' => 'approved', 'approved_at' => now()]);

        $rendered = $this->action()->handle($post, new PostPhotoCardTextData('নতুন [[শিরোনাম]]', 'সোর্স: বিবিসি'));

        $this->assertSame('নতুন [[শিরোনাম]]', $rendered->photo_card['headline'] ?? null);
        $this->assertSame('সোর্স: বিবিসি', $rendered->photo_card['source'] ?? null);
        $this->assertSame('pending', $post->fresh()?->approval_state);
    }

    public function test_it_refuses_while_a_publish_is_running_and_keeps_the_old_card(): void
    {
        $post = Post::factory()->create(['publish_state' => 'running', 'photo_card' => ['headline' => 'Keep']]);

        try {
            $this->action()->handle($post, new PostPhotoCardTextData('Changed', null));
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
            $this->assertSame(['headline' => 'Keep'], $post->fresh()?->photo_card);
        }
    }

    public function test_a_render_failure_changes_nothing(): void
    {
        $this->renderer->failWith('no python');
        $post = Post::factory()->create(['photo_card' => null]);

        $this->expectException(PhotoCardRenderException::class);

        try {
            $this->action()->handle($post, new PostPhotoCardTextData('X', null));
        } finally {
            $this->assertNull($post->fresh()?->photo_card);
            $this->assertSame(0, MediaAsset::query()->count());
        }
    }
}
