<?php

namespace App\Http\Controllers\Posts;

use App\Actions\Facebook\CancelFacebookPublicationAction;
use App\Actions\Facebook\EnqueueFacebookPublishAction;
use App\Actions\Facebook\MarkFacebookPublicationPublishedAction;
use App\Actions\Facebook\RetryFacebookPublicationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\PublishPostToFacebookRequest;
use App\Models\FacebookPublication;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostFacebookPublicationController extends Controller
{
    public function store(PublishPostToFacebookRequest $request, Post $post, EnqueueFacebookPublishAction $action): RedirectResponse
    {
        $workspace = $this->workspaceFor($post);
        $when = $request->filled('when')
            ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $request->string('when')->toString(), $workspace->timezone ?: 'Asia/Dhaka')
            : null;
        $user = $request->user();

        $action->handle($post, $workspace, $when ?: null, $user instanceof User ? $user : null);

        return $this->back($post, $when ? __('Scheduled for Facebook.') : __('Publishing to Facebook now.'));
    }

    public function cancel(Post $post, FacebookPublication $publication, CancelFacebookPublicationAction $action): RedirectResponse
    {
        $this->authorizePublication($post, $publication);
        $action->handle($publication);

        return $this->back($post, __('Facebook schedule cancelled.'));
    }

    public function retry(Request $request, Post $post, FacebookPublication $publication, RetryFacebookPublicationAction $action): RedirectResponse
    {
        $this->authorizePublication($post, $publication);
        $action->handle($publication, $request->boolean('confirmed_not_on_page'));

        return $this->back($post, __('Publishing to Facebook again.'));
    }

    public function markPublished(Post $post, FacebookPublication $publication, MarkFacebookPublicationPublishedAction $action): RedirectResponse
    {
        $this->authorizePublication($post, $publication);
        $action->handle($publication);

        return $this->back($post, __('Marked as published on Facebook.'));
    }

    private function workspaceFor(Post $post): Workspace
    {
        $workspace = Workspace::current();
        abort_if($workspace === null || $post->workspace_id !== $workspace->id, 404);

        return $workspace;
    }

    private function authorizePublication(Post $post, FacebookPublication $publication): void
    {
        $this->workspaceFor($post);
        abort_if($publication->post_id !== $post->id, 404);
    }

    private function back(Post $post, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('posts.show', $post);
    }
}
