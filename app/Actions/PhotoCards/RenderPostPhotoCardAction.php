<?php

namespace App\Actions\PhotoCards;

use App\Actions\Scratchpad\Concerns\ResolvesMediaAsset;
use App\Contracts\PhotoCardRenderer;
use App\Data\PhotoCards\PostPhotoCardTextData;
use App\Models\Post;
use App\Models\User;
use App\Support\PhotoCards\PhotoCardComposer;
use App\Support\PhotoCards\PhotoCardRenderException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Renders a post's photo card and stores the PNG as a MediaAsset referenced
 * from posts.photo_card. New text, when given, replaces the card's headline
 * and source. A new card on an approved post needs approval again, because
 * the card is what will be published.
 */
class RenderPostPhotoCardAction
{
    use ResolvesMediaAsset;

    public function __construct(
        private readonly PhotoCardComposer $composer,
        private readonly PhotoCardRenderer $renderer,
    ) {}

    /**
     * @throws PhotoCardRenderException
     * @throws ValidationException while the post is being published
     */
    public function handle(Post $post, ?PostPhotoCardTextData $text = null, ?User $renderedBy = null): Post
    {
        $card = is_array($post->photo_card) ? $post->photo_card : [];
        if ($text !== null) {
            $card['headline'] = $text->headline;
            $card['source'] = $text->source;
        }

        $preview = clone $post;
        $preview->photo_card = $card;
        $png = $this->renderer->render($this->composer->forPost($preview));

        $temporary = tempnam(sys_get_temp_dir(), 'cm-card-');
        if ($temporary === false) {
            throw new PhotoCardRenderException('Could not store the rendered card.');
        }

        try {
            file_put_contents($temporary, $png);
            $file = new UploadedFile($temporary, $post->human_id.'-card.png', 'image/png', null, true);

            return DB::transaction(function () use ($post, $card, $file, $renderedBy): Post {
                $lockedPost = Post::query()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();

                if ($lockedPost->isPublishInProgress() || $lockedPost->hasUncertainPublish()) {
                    throw ValidationException::withMessages([
                        'photo_card' => __('A post cannot be edited while its publish is queued, running, or uncertain.'),
                    ]);
                }

                $workspace = $lockedPost->workspace;
                if ($workspace === null) {
                    throw new PhotoCardRenderException('The post has no workspace.');
                }

                $media = $this->resolveMediaAsset($workspace, $renderedBy, $file, 'image', $lockedPost->human_id.'-card.png');
                $previousId = is_array($lockedPost->photo_card) ? ($lockedPost->photo_card['media_asset_id'] ?? null) : null;

                $lockedPost->forceFill([
                    'photo_card' => [
                        'headline' => $card['headline'] ?? null,
                        'source' => $card['source'] ?? null,
                        'media_asset_id' => $media->id,
                        'rendered_at' => now()->toIso8601String(),
                    ],
                ])->save();

                if ($previousId !== $media->id) {
                    $lockedPost->invalidateApproval();
                }

                return $lockedPost;
            });
        } finally {
            @unlink($temporary);
        }
    }
}
