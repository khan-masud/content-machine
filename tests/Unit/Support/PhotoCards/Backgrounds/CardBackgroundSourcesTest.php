<?php

namespace Tests\Unit\Support\PhotoCards\Backgrounds;

use App\Contracts\CardBackgroundSource;
use App\Data\PhotoCards\CardBackgroundImage;
use App\Models\Post;
use App\Support\PhotoCards\Backgrounds\CardBackgroundSources;
use Tests\TestCase;

class CardBackgroundSourcesTest extends TestCase
{
    private function sources(): CardBackgroundSources
    {
        $none = new class implements CardBackgroundSource
        {
            public function find(Post $post, int $variation = 0): ?CardBackgroundImage
            {
                return null;
            }
        };

        return new CardBackgroundSources(['ai' => $none, 'link' => clone $none, 'pexels' => clone $none]);
    }

    public function test_the_default_order_is_ai_then_link_then_pexels(): void
    {
        $this->assertSame(['ai', 'link', 'pexels'], array_keys($this->sources()->forCategory(null)));
        $this->assertSame(['ai', 'link', 'pexels'], array_keys($this->sources()->forCategory('sarcasm')));
    }

    public function test_politics_prefers_real_photos(): void
    {
        $this->assertSame(['link', 'pexels', 'ai'], array_keys($this->sources()->forCategory('politics')));
    }

    public function test_unknown_keys_in_config_are_ignored(): void
    {
        config(['photo_cards.background_sources' => ['ai', 'midjourney']]);

        $this->assertSame(['ai'], array_keys($this->sources()->forCategory(null)));
    }
}
