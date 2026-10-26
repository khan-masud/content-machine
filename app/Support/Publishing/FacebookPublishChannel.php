<?php

namespace App\Support\Publishing;

use App\Actions\Facebook\EnqueueFacebookPublishAction;
use App\Contracts\PostPublishChannel;
use App\Models\Post;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

class FacebookPublishChannel implements PostPublishChannel
{
    public function __construct(private readonly EnqueueFacebookPublishAction $enqueue) {}

    public function enqueue(Post $post, Workspace $workspace, ?CarbonImmutable $when, ?int $telegramRequestId = null): void
    {
        $this->enqueue->handle($post, $workspace, $when, null, $telegramRequestId);
    }
}
