<?php

namespace App\Jobs;

use App\Actions\Facebook\PublishFacebookPostAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one Facebook publication. Never retried by the queue: a second
 * attempt could post twice. facebook:publish-due recovers stuck rows.
 */
class PublishFacebookPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $publicationId)
    {
        // The photo card lives on the scratchpad uploads volume.
        $this->onQueue('scratchpad');
    }

    public function handle(PublishFacebookPostAction $action): void
    {
        $action->handle($this->publicationId);
    }
}
