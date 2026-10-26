<?php

namespace Tests\Unit\Support\PhotoCards;

use App\Models\Attachment;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\Workspace;
use App\Support\Brand\BrandConfig;
use App\Support\PhotoCards\PhotoCardComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class PhotoCardComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_post_card_uses_the_category_style_text_and_fonts(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Amar Page']);
        $post = Post::factory()->for($workspace)->create([
            'title' => 'Fallback title',
            'category' => 'sarcasm',
            'photo_card' => ['headline' => 'বিল বাড়ল, [[কারেন্ট গেল]]', 'source' => 'সোর্স: প্রথম আলো'],
        ]);

        $card = (new PhotoCardComposer)->forPost($post);

        $this->assertSame('বিল বাড়ল, [[কারেন্ট গেল]]', $card->headline);
        $this->assertSame('সোর্স: প্রথম আলো', $card->source);
        $this->assertSame('Amar Page', $card->brandName);
        $this->assertSame('#0B1220', $card->colors['background']);
        $this->assertSame('#FACC15', $card->colors['accent']);
        $this->assertNull($card->backgroundPath);
        $this->assertNull($card->imageLabel);
        $this->assertSame(1080, $card->width);
        $this->assertSame(1350, $card->height);
        $this->assertFileExists($card->fonts['bold']['path']);
        $this->assertFileExists($card->fonts['regular']['path']);
    }

    public function test_the_title_is_used_when_the_card_has_no_headline(): void
    {
        $post = Post::factory()->create(['title' => 'Fallback title', 'photo_card' => null]);

        $this->assertSame('Fallback title', (new PhotoCardComposer)->forPost($post)->headline);
    }

    public function test_the_post_photo_becomes_the_background_with_a_label(): void
    {
        Storage::fake('scratchpad');
        $workspace = Workspace::factory()->create(['name' => 'Amar Page']);
        Storage::disk('scratchpad')->put('1/photo.png', FakePhotoCardRenderer::png());
        $media = MediaAsset::factory()->for($workspace)->create([
            'disk' => 'scratchpad',
            'path' => '1/photo.png',
            'mime' => 'image/png',
        ]);
        $post = Post::factory()->for($workspace)->create();
        Attachment::factory()->create([
            'attachable_type' => $post->getMorphClass(),
            'attachable_id' => $post->id,
            'media_asset_id' => $media->id,
            'role' => 'image',
        ]);

        $card = (new PhotoCardComposer)->forPost($post->fresh() ?? $post);

        $this->assertSame(Storage::disk('scratchpad')->path('1/photo.png'), $card->backgroundPath);
        $this->assertSame('প্রতীকী চিত্র | Amar Page', $card->imageLabel);
    }

    public function test_dark_cards_prefer_the_dark_logo(): void
    {
        Storage::fake('scratchpad');
        $workspace = Workspace::factory()->create();
        Storage::disk('scratchpad')->put('1/light.png', FakePhotoCardRenderer::png());
        Storage::disk('scratchpad')->put('1/dark.png', FakePhotoCardRenderer::png());
        $light = MediaAsset::factory()->for($workspace)->create(['disk' => 'scratchpad', 'path' => '1/light.png', 'mime' => 'image/png']);
        $dark = MediaAsset::factory()->for($workspace)->create(['disk' => 'scratchpad', 'path' => '1/dark.png', 'mime' => 'image/png']);
        BrandConfig::write($workspace, ['logo_media_asset_id' => $light->id, 'logo_dark_media_asset_id' => $dark->id]);
        $workspace->refresh();

        $composer = new PhotoCardComposer;

        $this->assertSame(Storage::disk('scratchpad')->path('1/dark.png'), $composer->sample($workspace, 'techy')->logoPath);
        $this->assertSame(Storage::disk('scratchpad')->path('1/light.png'), $composer->sample($workspace, 'recent')->logoPath);
    }
}
