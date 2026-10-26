<?php

namespace Tests\Unit\Support\Posts;

use App\Support\Posts\PostCategories;
use Tests\TestCase;

class PostCategoriesTest extends TestCase
{
    public function test_it_lists_the_configured_categories(): void
    {
        $this->assertSame(
            ['recent', 'politics', 'analysis', 'funny', 'sarcasm', 'techy'],
            PostCategories::keys(),
        );
        $this->assertSame('Sarcasm', PostCategories::label('sarcasm'));
        $this->assertNotSame('', PostCategories::tone('sarcasm'));
    }

    public function test_normalize_accepts_known_keys_in_any_case_only(): void
    {
        $this->assertSame('politics', PostCategories::normalize(' Politics '));
        $this->assertNull(PostCategories::normalize('gossip'));
        $this->assertNull(PostCategories::normalize(null));
        $this->assertNull(PostCategories::normalize(['politics']));
    }

    public function test_aliases_resolve_to_their_category_including_bangla(): void
    {
        $this->assertSame('techy', PostCategories::fromAlias('TECH'));
        $this->assertSame('sarcasm', PostCategories::fromAlias('satire'));
        $this->assertSame('politics', PostCategories::fromAlias('রাজনীতি'));
        $this->assertNull(PostCategories::fromAlias('news'));
        $this->assertNull(PostCategories::fromAlias(''));
    }

    public function test_split_leading_takes_only_a_first_word_category(): void
    {
        $this->assertSame(['sarcasm', 'https://example.com'], PostCategories::splitLeading('sarcasm https://example.com'));
        $this->assertSame(['funny', ''], PostCategories::splitLeading('  funny  '));
        $this->assertSame([null, 'a funny thing'], PostCategories::splitLeading('a funny thing'));
        $this->assertSame([null, null], PostCategories::splitLeading(null));
    }

    public function test_style_gives_the_card_defaults(): void
    {
        $this->assertSame(['theme' => 'dark', 'accent' => '#FACC15'], PostCategories::style('sarcasm'));
        $this->assertNull(PostCategories::style('gossip'));
    }

    public function test_options_carry_both_labels(): void
    {
        $this->assertContains(
            ['key' => 'analysis', 'label' => 'Analysis', 'label_bn' => 'বিশ্লেষণ'],
            PostCategories::options(),
        );
    }
}
