<?php

namespace App\Support\PhotoCards\Backgrounds;

use App\Contracts\CardBackgroundSource;
use App\Data\PhotoCards\CardBackgroundImage;
use App\Models\Post;
use App\Models\TelegramPostRequest;
use App\Support\Http\SafeRemoteImageFetcher;

/**
 * The preview image (og:image / video thumbnail) of the link the draft was
 * made from. The same link always gives the same image.
 */
class LinkCardBackgroundSource implements CardBackgroundSource
{
    public function __construct(private readonly SafeRemoteImageFetcher $fetcher) {}

    public function find(Post $post, int $variation = 0): ?CardBackgroundImage
    {
        $entry = TelegramPostRequest::query()
            ->with('sourceEntry')
            ->where('post_id', $post->id)
            ->latest('id')
            ->first()
            ?->sourceEntry;

        $meta = is_array($entry?->meta) ? $entry->meta : [];
        $url = $meta['thumbnail_url'] ?? null;

        if ($entry === null || $entry->kind !== 'link' || ! is_string($url) || $url === '') {
            return null;
        }

        $image = $this->fetcher->fetch($url);

        return $image === null ? null : new CardBackgroundImage(
            $image['bytes'],
            $image['mime'],
            'link',
            ['url' => $url, 'page' => $meta['url'] ?? null],
        );
    }
}
