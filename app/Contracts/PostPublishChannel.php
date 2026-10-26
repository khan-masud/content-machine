<?php

namespace App\Contracts;

use App\Models\Post;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Where an approved post goes when someone says "publish" or "schedule"
 * (Telegram /post_now, /schedule): PostSyncer, or the Facebook Page.
 */
interface PostPublishChannel
{
    /**
     * Queue the post now ($when null) or for $when.
     *
     * @throws ValidationException when the post cannot be queued
     */
    public function enqueue(Post $post, Workspace $workspace, ?CarbonImmutable $when, ?int $telegramRequestId = null): void;
}
