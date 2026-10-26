<?php

namespace Tests\Unit\Support\Facebook;

use App\Models\Post;
use App\Support\Facebook\FacebookPostContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookPostContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefers_the_page_language_then_any_facebook_caption(): void
    {
        $post = Post::factory()->create(['captions' => [
            ['part' => 'English', 'lang' => 'en', 'platforms' => [['name' => 'facebook', 'title' => '', 'caption' => 'EN', 'first_comment' => 'src', 'images' => [], 'thread' => []]]],
        ]]);

        $content = FacebookPostContent::forPost($post, 'bn');

        $this->assertSame('EN', $content->caption);
        $this->assertSame('src', $content->firstComment);
        $this->assertNull($content->image);
    }

    public function test_without_captions_it_uses_the_body_then_the_title(): void
    {
        $this->assertSame('Body text', FacebookPostContent::forPost(Post::factory()->create(['captions' => null, 'body' => 'Body text']), 'bn')->caption);
        $this->assertSame('Title only', FacebookPostContent::forPost(Post::factory()->create(['captions' => null, 'body' => null, 'title' => 'Title only']), 'bn')->caption);
    }
}
