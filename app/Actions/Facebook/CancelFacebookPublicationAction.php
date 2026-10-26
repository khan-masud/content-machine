<?php

namespace App\Actions\Facebook;

use App\Models\FacebookPublication;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a scheduled (or queued but not yet sending) Facebook publication.
 */
class CancelFacebookPublicationAction
{
    public function handle(FacebookPublication $publication): FacebookPublication
    {
        return DB::transaction(function () use ($publication): FacebookPublication {
            $locked = FacebookPublication::query()->whereKey($publication->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->state, [FacebookPublication::SCHEDULED, FacebookPublication::QUEUED], true)) {
                throw ValidationException::withMessages(['facebook' => __('Only a scheduled post can be cancelled.')]);
            }

            $locked->forceFill(['state' => FacebookPublication::CANCELLED])->save();

            Post::query()->whereKey($locked->post_id)->where('status', 'scheduled')->update(['status' => 'draft']);

            return $locked;
        });
    }
}
