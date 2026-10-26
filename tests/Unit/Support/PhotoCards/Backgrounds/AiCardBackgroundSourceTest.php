<?php

namespace Tests\Unit\Support\PhotoCards\Backgrounds;

use App\Contracts\ImageGenerator;
use App\Data\ImageAi\ImagePromptData;
use App\Models\Post;
use App\Models\Workspace;
use App\Support\PhotoCards\Backgrounds\AiCardBackgroundSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class AiCardBackgroundSourceTest extends TestCase
{
    use RefreshDatabase;

    private function generator(bool $succeeds = true): ImageGenerator
    {
        return new class($succeeds) implements ImageGenerator
        {
            public ?ImagePromptData $last = null;

            public function __construct(private readonly bool $succeeds) {}

            public function generate(Workspace $workspace, ImagePromptData $prompt): ?array
            {
                $this->last = $prompt;

                return $this->succeeds ? ['bytes' => FakePhotoCardRenderer::png(), 'mime' => 'image/png', 'generator' => 'fake'] : null;
            }
        };
    }

    public function test_the_prompt_combines_the_card_idea_the_category_style_and_the_suffix(): void
    {
        $post = Post::factory()->create([
            'category' => 'sarcasm',
            'photo_card' => ['image_prompt' => 'A flying car stuck in Dhaka power lines'],
        ]);
        $generator = $this->generator();

        $image = (new AiCardBackgroundSource($generator))->find($post, 2);

        $this->assertSame('ai', $image?->source);
        $this->assertSame('fake', $image->meta['generator']);
        $prompt = (string) $generator->last?->prompt;
        $this->assertStringStartsWith('A flying car stuck in Dhaka power lines', $prompt);
        $this->assertStringContainsString('surreal satirical', $prompt);
        $this->assertStringContainsString('no text', $prompt);
        $this->assertSame([1080, 810, 2], [$generator->last->width, $generator->last->height, $generator->last->variation]);
    }

    public function test_without_an_idea_the_title_is_used_and_a_failure_returns_null(): void
    {
        $post = Post::factory()->create(['title' => 'Budget day', 'category' => null, 'photo_card' => null]);
        $generator = $this->generator(false);

        $this->assertNull((new AiCardBackgroundSource($generator))->find($post));
        $this->assertStringContainsString('Budget day', (string) $generator->last?->prompt);
    }
}
