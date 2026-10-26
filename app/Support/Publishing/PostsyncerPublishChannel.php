<?php

namespace App\Support\Publishing;

use App\Actions\Postsyncer\EnqueuePostPublishAction;
use App\Contracts\PostPublishChannel;
use App\Models\Post;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

class PostsyncerPublishChannel implements PostPublishChannel
{
    public function __construct(private readonly EnqueuePostPublishAction $enqueue) {}

    public function enqueue(Post $post, Workspace $workspace, ?CarbonImmutable $when, ?int $telegramRequestId = null): void
    {
        $this->enqueue->handle($post, $workspace, array_filter([
            'when' => $when?->toIso8601String(),
            'confirm_ask' => true,
            'telegram_request_id' => $telegramRequestId,
        ], fn ($value): bool => $value !== null));
    }
}
