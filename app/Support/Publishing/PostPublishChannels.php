<?php

namespace App\Support\Publishing;

use App\Contracts\PostPublishChannel;
use App\Models\Workspace;
use App\Support\Facebook\FacebookConfig;

/**
 * Picks the publish channel for a workspace: the Facebook Page when it is
 * connected and switched on (Settings → Facebook), otherwise PostSyncer.
 */
class PostPublishChannels
{
    public function __construct(
        private readonly PostsyncerPublishChannel $postsyncer,
        private readonly FacebookPublishChannel $facebook,
    ) {}

    public function forWorkspace(Workspace $workspace): PostPublishChannel
    {
        return FacebookConfig::fromWorkspace($workspace)->isReady() ? $this->facebook : $this->postsyncer;
    }
}
