<?php

namespace App\Actions\Telegram;

use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\TelegramPostRequest;
use App\Support\Telegram\TelegramPhotoSenderContract;
use Illuminate\Support\Facades\Storage;

/**
 * Sends a post's photo card to the Telegram chat that asked for the draft,
 * or, when rendering failed, tells that chat why. Only posts generated from
 * Telegram (a request still awaiting approval or approved) have a chat.
 */
class SendTelegramPostCardAction
{
    public function __construct(
        private readonly TelegramPhotoSenderContract $photoSender,
    ) {}

    public function handle(Post $post, ?string $renderError = null): bool
    {
        $request = TelegramPostRequest::query()
            ->with('telegramBotConfig')
            ->where('post_id', $post->id)
            ->whereIn('state', [TelegramPostRequest::AWAITING_APPROVAL, TelegramPostRequest::APPROVED])
            ->latest('id')
            ->first();

        $config = $request?->telegramBotConfig;
        if ($request === null || $config === null || ! $config->isConnected() || $config->bot_token === null) {
            return false;
        }

        if ($renderError !== null) {
            (new QueueTelegramMessageAction)->handle(
                $config,
                $request->telegram_chat_id,
                "⚠️ Photo card for {$post->human_id} could not be made: {$renderError}",
                'telegram:post-request:'.$request->id.':card-failure:'.md5($renderError),
                $request->webhook_generation,
            );

            return true;
        }

        $mediaId = is_array($post->photo_card) ? ($post->photo_card['media_asset_id'] ?? null) : null;
        $media = $mediaId === null ? null : MediaAsset::query()
            ->where('workspace_id', $post->workspace_id)
            ->whereKey($mediaId)
            ->first();

        if ($media === null || ! Storage::disk($media->disk)->exists($media->path)) {
            return false;
        }

        return $this->photoSender->sendPhoto(
            $config->bot_token,
            $request->telegram_chat_id,
            (string) Storage::disk($media->disk)->get($media->path),
            $post->human_id.'-card.png',
            "🖼 Photo card for {$post->human_id}\n\nSend /approve {$post->human_id} if it looks right, or edit the card on the post page.",
        )->successful;
    }
}
