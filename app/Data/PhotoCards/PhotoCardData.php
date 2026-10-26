<?php

namespace App\Data\PhotoCards;

/**
 * Everything a renderer needs to draw one card. Paths are absolute local
 * files; colours are #RRGGBB. Words wrapped in [[...]] in the headline are
 * drawn in the accent colour. An avatar is a person's photo drawn as a
 * round inset on the card.
 */
final readonly class PhotoCardData
{
    /**
     * @param  array{background: string, text: string, muted: string, accent: string, primary: string}  $colors
     * @param  array{regular: array{path: string, weight: ?int}, bold: array{path: string, weight: ?int}}  $fonts
     */
    public function __construct(
        public string $headline,
        public ?string $source,
        public string $brandName,
        public ?string $website,
        public ?string $logoPath,
        public ?string $backgroundPath,
        public ?string $imageLabel,
        public array $colors,
        public array $fonts,
        public int $width,
        public int $height,
        public int $backgroundHeight,
        public ?string $avatarPath = null,
    ) {}

    /**
     * The JSON spec resources/photocards/render.py reads.
     *
     * @return array<string, mixed>
     */
    public function toRenderSpec(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'background_height' => $this->backgroundHeight,
            'headline' => $this->headline,
            'source' => $this->source,
            'brand_name' => $this->brandName,
            'website' => $this->website,
            'logo_path' => $this->logoPath,
            'background_path' => $this->backgroundPath,
            'image_label' => $this->imageLabel,
            'colors' => $this->colors,
            'fonts' => $this->fonts,
            'avatar_path' => $this->avatarPath,
        ];
    }
}
