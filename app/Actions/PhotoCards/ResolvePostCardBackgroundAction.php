<?php

namespace App\Actions\PhotoCards;

use App\Actions\Scratchpad\Concerns\ResolvesMediaAsset;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Support\PhotoCards\Backgrounds\CardBackgroundSources;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Finds and stores a background for a post's photo card when the post has
 * no photo of its own: tries the category's sources in order (AI, link
 * preview, Pexels by default) and keeps the first image. With $refresh it
 * asks for a different image than the current one.
 */
class ResolvePostCardBackgroundAction
{
    use ResolvesMediaAsset;

    public function __construct(private readonly CardBackgroundSources $sources) {}

    /**
     * @throws ValidationException while the post is being published
     */
    public function handle(Post $post, bool $refresh = false): Post
    {
        if ($this->hasOwnImage($post)) {
            return $post;
        }

        $card = is_array($post->photo_card) ? $post->photo_card : [];
        $current = $this->currentBackground($post, $card);

        if ($current !== null && ! $refresh) {
            return $post;
        }

        $variation = (int) ($card['background_variation'] ?? 0) + ($refresh ? 1 : 0);

        foreach ($this->sources->forCategory($post->category) as $source) {
            $found = $source->find($post, $variation);

            // Asking again must not hand back the very same picture.
            if ($found === null || ($current !== null && hash('sha256', $found->bytes) === $current->checksum_sha256)) {
                continue;
            }

            $temporary = tempnam(sys_get_temp_dir(), 'cm-bg-');
            if ($temporary === false) {
                return $post;
            }

            try {
                file_put_contents($temporary, $found->bytes);
                $filename = $post->human_id.'-background.'.$found->extension();
                $file = new UploadedFile($temporary, $filename, $found->mime, null, true);

                return DB::transaction(function () use ($post, $file, $filename, $found, $variation): Post {
                    $locked = Post::query()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();

                    if ($locked->isPublishInProgress() || $locked->hasUncertainPublish()) {
                        throw ValidationException::withMessages([
                            'photo_card' => __('A post cannot be edited while its publish is queued, running, or uncertain.'),
                        ]);
                    }

                    $workspace = $locked->workspace;
                    if ($workspace === null) {
                        return $locked;
                    }

                    $media = $this->resolveMediaAsset($workspace, null, $file, 'image', $filename);
                    $card = is_array($locked->photo_card) ? $locked->photo_card : [];

                    $locked->forceFill(['photo_card' => array_merge($card, [
                        'background_media_asset_id' => $media->id,
                        'background_source' => $found->source,
                        'background_meta' => $found->meta,
                        'background_variation' => $variation,
                    ])])->save();

                    return $locked;
                });
            } finally {
                @unlink($temporary);
            }
        }

        return $post;
    }

    private function hasOwnImage(Post $post): bool
    {
        return $post->attachments()->where('role', 'image')->exists();
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function currentBackground(Post $post, array $card): ?MediaAsset
    {
        $mediaId = $card['background_media_asset_id'] ?? null;
        if (! is_int($mediaId)) {
            return null;
        }

        $media = MediaAsset::query()->where('workspace_id', $post->workspace_id)->whereKey($mediaId)->first();

        return $media !== null && Storage::disk($media->disk)->exists($media->path) ? $media : null;
    }
}
