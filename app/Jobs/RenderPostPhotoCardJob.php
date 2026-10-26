<?php

namespace App\Jobs;

use App\Actions\PhotoCards\RenderPostPhotoCardAction;
use App\Actions\Telegram\SendTelegramPostCardAction;
use App\Models\Post;
use App\Support\PhotoCards\PhotoCardRenderException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Renders a freshly generated draft's photo card and, for Telegram drafts,
 * sends it to the chat. A render failure never fails the draft: the person
 * is told why and can render again from the post page.
 */
class RenderPostPhotoCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $postId,
        public readonly bool $notifyTelegram = true,
    ) {
        // The card reads the post's photo from the scratchpad uploads volume.
        $this->onQueue('scratchpad');
    }

    public function handle(RenderPostPhotoCardAction $render, SendTelegramPostCardAction $send): void
    {
        $post = Post::query()->find($this->postId);
        if ($post === null) {
            return;
        }

        try {
            $post = $render->handle($post);
        } catch (PhotoCardRenderException $exception) {
            Log::warning('Photo card render failed.', ['post_id' => $post->id, 'error' => $exception->getMessage()]);

            if ($this->notifyTelegram) {
                $send->handle($post, $exception->getMessage());
            }

            return;
        } catch (ValidationException) {
            // The post started publishing in the meantime; leave it alone.
            return;
        }

        if ($this->notifyTelegram) {
            $send->handle($post);
        }
    }
}
