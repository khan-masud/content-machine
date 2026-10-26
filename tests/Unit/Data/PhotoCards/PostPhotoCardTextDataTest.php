<?php

namespace Tests\Unit\Data\PhotoCards;

use App\Data\PhotoCards\PostPhotoCardTextData;
use Tests\TestCase;

class PostPhotoCardTextDataTest extends TestCase
{
    public function test_from_array_trims_collapses_and_limits_text(): void
    {
        $data = PostPhotoCardTextData::fromArray([
            'headline' => "  বিদ্যুৎ   বিল\nবাড়ল ".str_repeat('ক', 300),
            'source' => '   ',
        ]);

        $this->assertStringStartsWith('বিদ্যুৎ বিল বাড়ল ', (string) $data->headline);
        $this->assertSame(PostPhotoCardTextData::HEADLINE_MAX, mb_strlen((string) $data->headline));
        $this->assertNull($data->source);
    }

    public function test_non_strings_become_null(): void
    {
        $data = PostPhotoCardTextData::fromArray(['headline' => ['x'], 'source' => 5]);

        $this->assertNull($data->headline);
        $this->assertNull($data->source);
    }

    public function test_own_headline_keeps_brackets_on_the_same_words_and_replaces_other_text(): void
    {
        $bracketed = PostPhotoCardTextData::fromArray(['headline' => 'বিল বাড়ল, [[কারেন্ট গেল]]', 'image_prompt' => 'A meter']);
        $this->assertSame('বিল বাড়ল, [[কারেন্ট গেল]]', $bracketed->withOwnHeadline('বিল বাড়ল,  কারেন্ট গেল')->headline);

        $rewritten = PostPhotoCardTextData::fromArray(['headline' => 'অন্য [[কথা]]', 'image_prompt' => 'A meter']);
        $own = $rewritten->withOwnHeadline('বিল বাড়ল, কারেন্ট গেল');
        $this->assertSame('বিল বাড়ল, কারেন্ট গেল', $own->headline);
        $this->assertSame('A meter', $own->imagePrompt);
    }
}
