<?php

namespace App\Jobs;

use App\Actions\PhotoCards\RenderPostPhotoCardAction;
use App\Actions\PhotoCards\ResolvePostCardBackgroundAction;
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
use Throwable;

/**
 * Finds a background (when the post has no photo), renders the photo card,
 * and, for Telegram drafts, sends it to the chat. Neither step can fail the
 * draft: without a background the card is text only, and a render failure
 * is reported so the person can try again.
 */
class RenderPostPhotoCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // AI image providers can take a minute; the render itself is quick.
    public int $timeout = 240;

    public function __construct(
        public readonly int $postId,
        public readonly bool $notifyTelegram = true,
        public readonly bool $refreshBackground = false,
    ) {
        // The card reads the post's photo from the scratchpad uploads volume.
        $this->onQueue('scratchpad');
    }

    public function handle(
        ResolvePostCardBackgroundAction $background,
        RenderPostPhotoCardAction $render,
        SendTelegramPostCardAction $send,
    ): void {
        $post = Post::query()->find($this->postId);
        if ($post === null) {
            return;
        }

        try {
            $post = $background->handle($post, $this->refreshBackground);
        } catch (ValidationException) {
            return;
        } catch (Throwable $exception) {
            // A broken image provider must not cost the person their card.
            Log::warning('Photo card background failed.', ['post_id' => $post->id, 'error' => $exception->getMessage()]);
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
