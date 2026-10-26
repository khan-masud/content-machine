<?php

namespace App\Http\Controllers\Posts;

use App\Actions\PhotoCards\RenderPostPhotoCardAction;
use App\Data\PhotoCards\PostPhotoCardTextData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\RenderPostPhotoCardRequest;
use App\Jobs\RenderPostPhotoCardJob;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PhotoCards\PhotoCardRenderException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostPhotoCardController extends Controller
{
    /**
     * Stream the post's rendered card. The scratchpad disk is private, so the
     * dashboard loads it through this same-origin route.
     */
    public function show(Request $request, Post $post): StreamedResponse
    {
        $workspace = $this->currentWorkspace();
        abort_if($post->workspace_id !== $workspace->id, 404);

        $mediaId = is_array($post->photo_card) ? ($post->photo_card['media_asset_id'] ?? null) : null;
        $media = $mediaId === null ? null : MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($mediaId)
            ->first();
        abort_if($media === null, 404);

        return Storage::disk($media->disk)->response($media->path, $post->human_id.'-card.png', [
            'Content-Type' => $media->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(RenderPostPhotoCardRequest $request, Post $post, RenderPostPhotoCardAction $action): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        abort_if($post->workspace_id !== $workspace->id, 404);

        $user = $request->user();

        try {
            $action->handle($post, PostPhotoCardTextData::fromRequest($request), $user instanceof User ? $user : null);
        } catch (PhotoCardRenderException $exception) {
            throw ValidationException::withMessages(['photo_card' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo card rendered.')]);

        return to_route('posts.show', $post);
    }

    /**
     * Queue a new background (AI, link preview, or Pexels) and a re-render.
     */
    public function background(Request $request, Post $post): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        abort_if($post->workspace_id !== $workspace->id, 404);

        RenderPostPhotoCardJob::dispatch($post->id, notifyTelegram: false, refreshBackground: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Finding a new background. The card updates in a moment.')]);

        return to_route('posts.show', $post);
    }

    private function currentWorkspace(): Workspace
    {
        $workspace = Workspace::current();

        abort_if($workspace === null, 404, 'No current workspace.');

        return $workspace;
    }
}
