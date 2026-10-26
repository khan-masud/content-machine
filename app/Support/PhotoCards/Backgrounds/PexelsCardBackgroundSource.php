<?php

namespace App\Support\PhotoCards\Backgrounds;

use App\Contracts\CardBackgroundSource;
use App\Contracts\PexelsProviderFactory;
use App\Data\PhotoCards\CardBackgroundImage;
use App\Models\Post;
use App\Support\Http\SafeRemoteImageFetcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A Pexels stock photo matching the card's image keywords. Needs the
 * workspace's Pexels key; each variation takes the next search result.
 */
class PexelsCardBackgroundSource implements CardBackgroundSource
{
    private const RESULTS = 8;

    public function __construct(private readonly PexelsProviderFactory $providers) {}

    public function find(Post $post, int $variation = 0): ?CardBackgroundImage
    {
        $workspace = $post->workspace;
        $provider = $workspace === null ? null : $this->providers->forWorkspace($workspace);
        if ($provider === null) {
            return null;
        }

        $card = is_array($post->photo_card) ? $post->photo_card : [];
        $query = is_string($card['image_query'] ?? null) && trim($card['image_query']) !== ''
            ? trim($card['image_query'])
            : $post->title;

        try {
            $photos = $provider->search(mb_substr($query, 0, 100), 'portrait', self::RESULTS);
            if ($photos === []) {
                return null;
            }

            $photo = $photos[$variation % count($photos)];
            $download = $provider->download($photo);
        } catch (Throwable $exception) {
            Log::warning('Pexels card background failed.', ['error' => $exception->getMessage()]);

            return null;
        }

        $image = SafeRemoteImageFetcher::imageOrNull($download['bytes']);

        return $image === null ? null : new CardBackgroundImage($image['bytes'], $image['mime'], 'pexels', [
            'pexels_id' => $photo->id,
            'photographer' => $photo->photographer,
            'pexels_url' => $photo->pexelsUrl,
        ]);
    }
}
