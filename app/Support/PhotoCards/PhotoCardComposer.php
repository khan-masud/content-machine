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
 * place that decides colours, fonts, logo, and background for a card.
 */
class PhotoCardComposer
{
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

        return $this->compose(
            $workspace,
            $post->category,
            $headline,
            $source,
            $this->backgroundPath($post),
        );
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
            imageLabel: $backgroundPath === null ? null : $brand->imageLabel().' | '.$brand->name(),
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
        );
    }

    /**
     * The first image the post carries (e.g. the photo sent with /post).
     */
    private function backgroundPath(Post $post): ?string
    {
        $post->loadMissing('attachments.mediaAsset');

        foreach ($post->attachments->sortBy('position') as $attachment) {
            $media = $attachment->mediaAsset;

            if ($attachment->role === 'image' && $media !== null && str_starts_with((string) $media->mime, 'image/')) {
                return $this->readablePath($media);
            }
        }

        return null;
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
