<?php

namespace App\Actions\Facebook;

use App\Actions\Telegram\QueueTelegramMessageAction;
use App\Contracts\FacebookPageClient;
use App\Data\Facebook\FacebookApiResult;
use App\Models\FacebookPublication;
use App\Models\Post;
use App\Models\TelegramPostRequest;
use App\Support\Facebook\FacebookConfig;
use App\Support\Facebook\FacebookPostContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sends one queued publication to the Facebook Page: the photo card (or the
 * post's photo) with the Facebook caption, then the first comment. The post
 * must still be approved at send time, so an edit after scheduling holds it
 * back. A lost response is recorded as `uncertain` and never re-sent
 * automatically, because the Graph API has no idempotency key.
 */
class PublishFacebookPostAction
{
    public function __construct(private readonly FacebookPageClient $client) {}

    public function handle(int $publicationId): ?FacebookPublication
    {
        $publication = $this->claim($publicationId);
        if ($publication === null) {
            return null;
        }

        $post = Post::query()->find($publication->post_id);
        $workspace = $post?->workspace;
        $config = $workspace === null ? null : FacebookConfig::fromWorkspace($workspace);

        if ($post === null || $config === null || ! $config->isReady() || $config->pageId() !== $publication->page_id) {
            return $this->finish($publication, $post, FacebookPublication::FAILED, 'The Facebook Page is no longer connected. Reconnect it in Settings → Facebook.');
        }

        if ($post->approval_state !== 'approved') {
            return $this->finish($publication, $post, FacebookPublication::FAILED, 'The post changed after it was scheduled. Approve it and publish again.');
        }

        $content = FacebookPostContent::forPost($post, $config->captionLanguage());
        $token = (string) $config->pageAccessToken();
        $photo = $content->imageContents();

        $result = $photo !== null
            ? $this->client->publishPhoto($publication->page_id, $token, $photo, $post->human_id.'.png', $content->caption)
            : $this->client->publishText($publication->page_id, $token, $content->caption);

        if (! $result->successful) {
            return $result->outcomeUnknown
                ? $this->finish($publication, $post, FacebookPublication::UNCERTAIN, 'Facebook did not confirm the post ('.$result->error.'). Check the Page, then mark it published or retry.')
                : $this->finish($publication, $post, FacebookPublication::FAILED, (string) $result->error);
        }

        $publication = $this->finish($publication, $post, FacebookPublication::PUBLISHED, null, $result);

        if ($content->firstComment !== null && $publication->facebook_post_id !== null) {
            $comment = $this->client->comment($publication->facebook_post_id, $token, $content->firstComment);
            if (! $comment->successful) {
                $publication->forceFill(['comment_error' => $comment->error])->save();
            }
        }

        return $publication;
    }

    private function claim(int $publicationId): ?FacebookPublication
    {
        return DB::transaction(function () use ($publicationId): ?FacebookPublication {
            $publication = FacebookPublication::query()->whereKey($publicationId)->lockForUpdate()->first();

            if ($publication === null || $publication->state !== FacebookPublication::QUEUED) {
                return null;
            }

            $publication->forceFill([
                'state' => FacebookPublication::PUBLISHING,
                'lease_id' => (string) Str::uuid(),
                'claimed_at' => now(),
                'error' => null,
            ])->save();

            return $publication;
        });
    }

    private function finish(
        FacebookPublication $publication,
        ?Post $post,
        string $state,
        ?string $error,
        ?FacebookApiResult $result = null,
    ): FacebookPublication {
        $facebookPostId = $result === null ? null : ($result->postId ?? $result->id);

        DB::transaction(function () use ($publication, $post, $state, $error, $facebookPostId): void {
            $publication->forceFill([
                'state' => $state,
                'error' => $error,
                'lease_id' => null,
                'facebook_post_id' => $facebookPostId,
                'permalink' => $facebookPostId === null ? null : 'https://www.facebook.com/'.$facebookPostId,
                'published_at' => $state === FacebookPublication::PUBLISHED ? now() : null,
            ])->save();

            if ($post !== null && $state === FacebookPublication::PUBLISHED) {
                $post->forceFill(['status' => 'posted'])->save();
            }

            if ($publication->telegram_post_request_id !== null && in_array($state, [FacebookPublication::PUBLISHED, FacebookPublication::FAILED], true)) {
                TelegramPostRequest::query()
                    ->whereKey($publication->telegram_post_request_id)
                    ->whereIn('state', [TelegramPostRequest::APPROVED, TelegramPostRequest::FAILED])
                    ->update([
                        'state' => $state === FacebookPublication::PUBLISHED ? TelegramPostRequest::PUBLISHED : TelegramPostRequest::FAILED,
                        'error_message' => $error,
                    ]);
            }
        });

        $this->notifyTelegram($publication, $post);

        return $publication;
    }

    private function notifyTelegram(FacebookPublication $publication, ?Post $post): void
    {
        $request = $publication->telegramPostRequest()->with('telegramBotConfig')->first();
        $config = $request?->telegramBotConfig;

        if ($request === null || $config === null || ! $config->isConnected() || $post === null) {
            return;
        }

        $text = match ($publication->state) {
            FacebookPublication::PUBLISHED => "✅ {$post->human_id} is live on Facebook: {$publication->permalink}",
            FacebookPublication::UNCERTAIN => "⚠️ {$post->human_id}: {$publication->error}",
            default => "❌ {$post->human_id} was not published to Facebook: {$publication->error}",
        };

        (new QueueTelegramMessageAction)->handle(
            $config,
            $request->telegram_chat_id,
            $text,
            'facebook-publication:'.$publication->id.':'.$publication->state,
            $request->webhook_generation,
        );
    }
}
