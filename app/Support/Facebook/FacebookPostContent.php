<?php

namespace App\Support\Facebook;

use App\Models\MediaAsset;
use App\Models\Post;
use App\Support\Content\NormalizeCaptions;
use Illuminate\Support\Facades\Storage;

/**
 * What a post looks like on the Facebook Page: the Facebook caption in the
 * Page's language (else any Facebook caption, the body, or the title), its
 * first comment, and the image (the photo card, else the post's own photo).
 */
final readonly class FacebookPostContent
{
    public function __construct(
        public string $caption,
        public ?string $firstComment,
        public ?MediaAsset $image,
    ) {}

    public static function forPost(Post $post, string $language): self
    {
        $facebook = null;
        $fallback = null;

        foreach (NormalizeCaptions::forDashboard($post->captions) as $group) {
            foreach ($group['platforms'] as $platform) {
                if (strtolower($platform['name']) !== 'facebook' || trim($platform['caption']) === '') {
                    continue;
                }

                $fallback ??= $platform;
                if ($group['lang'] === $language) {
                    $facebook ??= $platform;
                }
            }
        }

        $chosen = $facebook ?? $fallback;
        $caption = $chosen !== null
            ? trim($chosen['caption'])
            : (filled($post->body) ? trim((string) $post->body) : $post->title);
        $firstComment = $chosen !== null && trim($chosen['first_comment']) !== '' ? trim($chosen['first_comment']) : null;

        return new self($caption, $firstComment, self::image($post));
    }

    public function imageContents(): ?string
    {
        if ($this->image === null) {
            return null;
        }

        $disk = Storage::disk($this->image->disk);

        return $disk->exists($this->image->path) ? (string) $disk->get($this->image->path) : null;
    }

    private static function image(Post $post): ?MediaAsset
    {
        $card = is_array($post->photo_card) ? $post->photo_card : [];
        $cardId = $card['media_asset_id'] ?? null;

        if (is_int($cardId)) {
            $media = MediaAsset::query()->where('workspace_id', $post->workspace_id)->whereKey($cardId)->first();
            if ($media !== null) {
                return $media;
            }
        }

        $attachment = $post->attachments()
            ->where('role', 'image')
            ->orderBy('position')
            ->with('mediaAsset')
            ->first();

        return $attachment?->mediaAsset;
    }
}
