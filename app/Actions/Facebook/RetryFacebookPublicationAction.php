<?php

namespace App\Actions\Facebook;

use App\Jobs\PublishFacebookPostJob;
use App\Models\FacebookPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Queues a failed publication again. An uncertain one is only retried after
 * the person confirms they checked the Page and the post is not there.
 */
class RetryFacebookPublicationAction
{
    public function handle(FacebookPublication $publication, bool $confirmedNotOnPage = false): FacebookPublication
    {
        $retried = DB::transaction(function () use ($publication, $confirmedNotOnPage): FacebookPublication {
            $locked = FacebookPublication::query()->whereKey($publication->id)->lockForUpdate()->firstOrFail();

            if ($locked->state === FacebookPublication::UNCERTAIN && ! $confirmedNotOnPage) {
                throw ValidationException::withMessages([
                    'facebook' => __('Check the Facebook Page first. Retry only if the post is not there, or the Page gets it twice.'),
                ]);
            }

            if (! in_array($locked->state, [FacebookPublication::FAILED, FacebookPublication::UNCERTAIN], true)) {
                throw ValidationException::withMessages(['facebook' => __('Only a failed publish can be retried.')]);
            }

            $locked->forceFill(['state' => FacebookPublication::QUEUED, 'error' => null, 'scheduled_for' => null])->save();

            return $locked;
        });

        PublishFacebookPostJob::dispatch($retried->id);

        return $retried;
    }
}
