<?php

namespace App\Actions\Facebook;

use App\Models\FacebookPublication;
use App\Models\Post;
use App\Models\TelegramPostRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records an uncertain publication as published after the person found the
 * post on the Page, so it is never sent a second time.
 */
class MarkFacebookPublicationPublishedAction
{
    public function handle(FacebookPublication $publication, ?string $permalink = null): FacebookPublication
    {
        return DB::transaction(function () use ($publication, $permalink): FacebookPublication {
            $locked = FacebookPublication::query()->whereKey($publication->id)->lockForUpdate()->firstOrFail();

            if ($locked->state !== FacebookPublication::UNCERTAIN) {
                throw ValidationException::withMessages(['facebook' => __('Only an unconfirmed publish can be marked as published.')]);
            }

            $locked->forceFill([
                'state' => FacebookPublication::PUBLISHED,
                'error' => null,
                'permalink' => $permalink ?? $locked->permalink,
                'published_at' => now(),
            ])->save();

            Post::query()->whereKey($locked->post_id)->update(['status' => 'posted']);

            if ($locked->telegram_post_request_id !== null) {
                TelegramPostRequest::query()
                    ->whereKey($locked->telegram_post_request_id)
                    ->whereIn('state', [TelegramPostRequest::APPROVED, TelegramPostRequest::FAILED])
                    ->update(['state' => TelegramPostRequest::PUBLISHED, 'error_message' => null]);
            }

            return $locked;
        });
    }
}
