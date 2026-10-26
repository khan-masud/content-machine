<?php

namespace Tests\Unit\Support\Publishing;

use App\Models\Workspace;
use App\Support\Publishing\FacebookPublishChannel;
use App\Support\Publishing\PostPublishChannels;
use App\Support\Publishing\PostsyncerPublishChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Facebook\FakeFacebookPageClient;
use Tests\TestCase;

class PostPublishChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_is_used_only_when_connected_and_switched_on(): void
    {
        $channels = app(PostPublishChannels::class);
        $workspace = Workspace::factory()->create();

        $this->assertInstanceOf(PostsyncerPublishChannel::class, $channels->forWorkspace($workspace));

        FakeFacebookPageClient::connect($workspace, enabled: false);
        $this->assertInstanceOf(PostsyncerPublishChannel::class, $channels->forWorkspace($workspace));

        FakeFacebookPageClient::connect($workspace);
        $this->assertInstanceOf(FacebookPublishChannel::class, $channels->forWorkspace($workspace));
    }
}
