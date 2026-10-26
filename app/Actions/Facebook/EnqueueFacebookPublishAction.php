<?php

namespace App\Actions\Facebook;

use App\Jobs\PublishFacebookPostJob;
use App\Models\FacebookPublication;
use App\Models\Post;
use App\Models\TelegramPostRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Facebook\FacebookConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Schedules an approved post for the workspace's Facebook Page, or queues it
 * now when no time is given. Scheduled posts are picked up by
 * facebook:publish-due; nothing is sent to Facebook here.
 */
class EnqueueFacebookPublishAction
{
    public function handle(
        Post $post,
        Workspace $workspace,
        ?CarbonImmutable $when = null,
        ?User $requestedBy = null,
        ?int $telegramRequestId = null,
    ): FacebookPublication {
        abort_if($post->workspace_id !== $workspace->id, 404);

        $config = FacebookConfig::fromWorkspace($workspace);
        if (! $config->isReady()) {
            throw ValidationException::withMessages([
                'facebook' => __('Connect a Facebook Page in Settings → Facebook and turn publishing on.'),
            ]);
        }

        if ($when !== null && $when->lessThan(now()->addMinute())) {
            throw ValidationException::withMessages([
                'when' => __('Pick a time at least a minute from now, or publish now.'),
            ]);
        }

        $publication = DB::transaction(function () use ($post, $config, $when, $requestedBy, $telegramRequestId): FacebookPublication {
            $locked = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($locked->approval_state !== 'approved') {
                throw ValidationException::withMessages(['facebook' => __('Approve the post before publishing it.')]);
            }

            if ($locked->isPublishInProgress() || $locked->hasUncertainPublish()) {
                throw ValidationException::withMessages(['facebook' => __('A PostSyncer publish is still in progress for this post.')]);
            }

            $existing = FacebookPublication::query()
                ->where('post_id', $locked->id)
                ->whereIn('state', [...FacebookPublication::ACTIVE_STATES, FacebookPublication::PUBLISHED])
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages(['facebook' => $existing->state === FacebookPublication::PUBLISHED
                    ? __('This post is already on Facebook.')
                    : __('This post is already scheduled or publishing. Cancel it first.')]);
            }

            $requestId = $telegramRequestId === null ? null : TelegramPostRequest::query()
                ->whereKey($telegramRequestId)
                ->where('post_id', $locked->id)
                ->value('id');

            if ($requestId !== null) {
                // A retry after a failed publish starts the request over.
                TelegramPostRequest::query()
                    ->whereKey($requestId)
                    ->where('state', TelegramPostRequest::FAILED)
                    ->update(['state' => TelegramPostRequest::APPROVED, 'error_message' => null]);
            }

            if ($when !== null) {
                $locked->forceFill(['status' => 'scheduled'])->save();
            }

            return FacebookPublication::query()->create([
                'workspace_id' => $locked->workspace_id,
                'post_id' => $locked->id,
                'telegram_post_request_id' => $requestId,
                'created_by_user_id' => $requestedBy?->id,
                'page_id' => (string) $config->pageId(),
                'state' => $when === null ? FacebookPublication::QUEUED : FacebookPublication::SCHEDULED,
                // Stored in the app timezone; Eloquent writes timestamps without an offset.
                'scheduled_for' => $when?->setTimezone((string) config('app.timezone')),
            ]);
        });

        if ($publication->state === FacebookPublication::QUEUED) {
            PublishFacebookPostJob::dispatch($publication->id);
        }

        return $publication;
    }
}
