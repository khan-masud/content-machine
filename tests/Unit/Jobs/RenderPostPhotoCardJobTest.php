<?php

namespace Tests\Unit\Jobs;

use App\Actions\PhotoCards\RenderPostPhotoCardAction;
use App\Actions\Telegram\SendTelegramPostCardAction;
use App\Jobs\RenderPostPhotoCardJob;
use App\Models\Post;
use App\Support\PhotoCards\PhotoCardComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\Support\Telegram\FakeTelegramClient;
use Tests\TestCase;

class RenderPostPhotoCardJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_on_the_scratchpad_queue(): void
    {
        $this->assertSame('scratchpad', (new RenderPostPhotoCardJob(1))->queue);
    }

    public function test_it_renders_the_card(): void
    {
        Storage::fake('scratchpad');
        $post = Post::factory()->create(['photo_card' => null]);

        (new RenderPostPhotoCardJob($post->id))->handle(
            new RenderPostPhotoCardAction(new PhotoCardComposer, new FakePhotoCardRenderer),
            new SendTelegramPostCardAction(new FakeTelegramClient),
        );

        $this->assertIsInt($post->fresh()?->photo_card['media_asset_id'] ?? null);
    }

    public function test_a_render_failure_does_not_throw(): void
    {
        $post = Post::factory()->create(['photo_card' => null]);

        (new RenderPostPhotoCardJob($post->id))->handle(
            new RenderPostPhotoCardAction(new PhotoCardComposer, (new FakePhotoCardRenderer)->failWith('broken')),
            new SendTelegramPostCardAction(new FakeTelegramClient),
        );

        $this->assertNull($post->fresh()?->photo_card);
    }
}
