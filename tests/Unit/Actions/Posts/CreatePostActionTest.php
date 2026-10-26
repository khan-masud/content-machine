<?php

namespace Tests\Unit\Actions\Posts;

use App\Actions\Posts\CreatePostAction;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreatePostActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_a_normalized_category(): void
    {
        $workspace = Workspace::factory()->create();

        $post = app(CreatePostAction::class)->handle($workspace, [
            'title' => 'Budget reaction',
            'category' => 'Analysis',
        ]);

        $this->assertSame('analysis', $post->refresh()->category);
    }

    public function test_category_is_optional(): void
    {
        $workspace = Workspace::factory()->create();

        $post = app(CreatePostAction::class)->handle($workspace, ['title' => 'Untagged']);

        $this->assertNull($post->refresh()->category);
    }

    public function test_it_rejects_an_unknown_category(): void
    {
        $workspace = Workspace::factory()->create();

        $this->expectException(ValidationException::class);

        app(CreatePostAction::class)->handle($workspace, [
            'title' => 'Bad',
            'category' => 'gossip',
        ]);
    }
}
