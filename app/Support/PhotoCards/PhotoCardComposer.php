<?php

namespace App\Support\PhotoCards;

use App\Data\PhotoCards\PhotoCardData;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\Workspace;
use App\Support\Brand\BrandConfig;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a post (or a sample, for the brand settings preview) plus the
 * workspace brand into the PhotoCardData a renderer draws. This is the only
 * place that decides colours, fonts, logo, background, and avatar for a card.
 */
class PhotoCardComposer
{
    private readonly CardPeopleMatcher $people;

    public function __construct(?CardPeopleMatcher $people = null)
    {
        $this->people = $people ?? new CardPeopleMatcher;
    }

    public function forPost(Post $post): PhotoCardData
    {
        $workspace = $post->workspace;
        if ($workspace === null) {
            throw new PhotoCardRenderException('The post has no workspace.');
        }

        $card = is_array($post->photo_card) ? $post->photo_card : [];
        $headline = is_string($card['headline'] ?? null) && trim($card['headline']) !== ''
            ? trim($card['headline'])
            : $post->title;
        $source = is_string($card['source'] ?? null) && trim($card['source']) !== ''
            ? trim($card['source'])
            : null;

        $background = $this->background($post);

        return $this->compose(
            $workspace,
            $post->category,
            $headline,
            $source,
            $background['path'] ?? null,
            $background['source'] ?? null,
            $this->avatarPath($workspace, $card, $headline),
        );
    }

    /**
     * The card's own avatar (a photo sent with /card avatar), else the photo
     * of the library person the headline names. `avatar: false` turns it off.
     *
     * @param  array<string, mixed>  $card
     */
    private function avatarPath(Workspace $workspace, array $card, string $headline): ?string
    {
        if (($card['avatar'] ?? null) === false) {
            return null;
        }

        $mediaId = $card['avatar_media_asset_id'] ?? null;
        if (is_int($mediaId)) {
            return $this->localPath($workspace, $mediaId);
        }

        $person = $this->people->match($workspace->id, $headline);

        return $person === null ? null : $this->localPath($workspace, $person->media_asset_id);
    }

    /**
     * A short human description of where the card's background comes from.
     */
    public static function describeBackground(Post $post): ?string
    {
        if ($post->attachments()->where('role', 'image')->exists()) {
            return 'the post\'s own photo';
        }

        $card = is_array($post->photo_card) ? $post->photo_card : [];
        $meta = is_array($card['background_meta'] ?? null) ? $card['background_meta'] : [];

        return match ($card['background_source'] ?? null) {
            'ai' => 'AI image ('.(is_string($meta['generator'] ?? null) ? $meta['generator'] : 'generated').')',
            'link' => 'the source link\'s preview image',
            'pexels' => 'Pexels'.(is_string($meta['photographer'] ?? null) ? ' photo by '.$meta['photographer'] : ''),
            default => null,
        };
    }

    public static function backgroundWidth(): int
    {
        return (int) config('photo_cards.width');
    }

    public static function backgroundHeight(): int
    {
        return (int) round((int) config('photo_cards.height') * (float) config('photo_cards.background_ratio'));
    }

    public function sample(Workspace $workspace, ?string $category = null): PhotoCardData
    {
        return $this->compose(
            $workspace,
            $category,
            'ছোট শিরোনাম, আর [[গুরুত্বপূর্ণ শব্দ]] রঙিন',
            'সোর্স: Content Machine',
            null,
        );
    }

    private function compose(
        Workspace $workspace,
        ?string $category,
        string $headline,
        ?string $source,
        ?string $backgroundPath,
        ?string $backgroundSource = null,
        ?string $avatarPath = null,
    ): PhotoCardData {
        $brand = BrandConfig::fromWorkspace($workspace);
        $style = $brand->categoryStyle($category);
        /** @var array{background: string, text: string, muted: string} $theme */
        $theme = config('photo_cards.themes.'.$style['theme']);

        $logoId = $style['theme'] === 'dark'
            ? ($brand->darkLogoMediaAssetId() ?? $brand->logoMediaAssetId())
            : $brand->logoMediaAssetId();

        return new PhotoCardData(
            headline: $headline,
            source: $source,
            brandName: $brand->name(),
            website: $brand->website(),
            logoPath: $this->localPath($workspace, $logoId),
            backgroundPath: $backgroundPath,
            imageLabel: $backgroundPath === null ? null : $this->imageLabel($brand, $backgroundSource),
            colors: [
                'background' => $theme['background'],
                'text' => $theme['text'],
                'muted' => $theme['muted'],
                'accent' => $style['accent'],
                'primary' => $brand->primaryColor(),
            ],
            fonts: $this->fonts($brand->font()),
            width: (int) config('photo_cards.width'),
            height: (int) config('photo_cards.height'),
            backgroundHeight: self::backgroundHeight(),
            avatarPath: $avatarPath,
        );
    }

    /**
     * The post's own first image (e.g. the photo sent with /post), else the
     * background found for its card (AI, link preview, or Pexels).
     *
     * @return array{path: string, source: ?string}|null
     */
    private function background(Post $post): ?array
    {
        $post->loadMissing('attachments.mediaAsset');

        foreach ($post->attachments->sortBy('position') as $attachment) {
            $media = $attachment->mediaAsset;

            if ($attachment->role === 'image' && $media !== null && str_starts_with((string) $media->mime, 'image/')) {
                $path = $this->readablePath($media);

                return $path === null ? null : ['path' => $path, 'source' => null];
            }
        }

        $card = is_array($post->photo_card) ? $post->photo_card : [];
        $mediaId = $card['background_media_asset_id'] ?? null;
        $workspace = $post->workspace;
        $path = is_int($mediaId) && $workspace !== null ? $this->localPath($workspace, $mediaId) : null;

        return $path === null ? null : [
            'path' => $path,
            'source' => is_string($card['background_source'] ?? null) ? $card['background_source'] : null,
        ];
    }

    private function imageLabel(BrandConfig $brand, ?string $backgroundSource): string
    {
        $labels = (array) config('photo_cards.background_labels');
        $label = $backgroundSource !== null && is_string($labels[$backgroundSource] ?? null)
            ? $labels[$backgroundSource]
            : $brand->imageLabel();

        return $label.' | '.$brand->name();
    }

    private function localPath(Workspace $workspace, ?int $mediaAssetId): ?string
    {
        if ($mediaAssetId === null) {
            return null;
        }

        $media = MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($mediaAssetId)
            ->first();

        return $media === null ? null : $this->readablePath($media);
    }

    private function readablePath(MediaAsset $media): ?string
    {
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            return null;
        }

        return $disk->path($media->path);
    }

    /**
     * @return array{regular: array{path: string, weight: ?int}, bold: array{path: string, weight: ?int}}
     */
    private function fonts(string $font): array
    {
        /** @var array{regular: array{file: string, weight: ?int}, bold: array{file: string, weight: ?int}} $definition */
        $definition = config('photo_cards.fonts.'.$font);
        $directory = (string) config('photo_cards.font_directory');

        return [
            'regular' => ['path' => $directory.'/'.$definition['regular']['file'], 'weight' => $definition['regular']['weight']],
            'bold' => ['path' => $directory.'/'.$definition['bold']['file'], 'weight' => $definition['bold']['weight']],
        ];
    }
}
