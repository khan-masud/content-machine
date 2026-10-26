<?php

namespace App\Data\PhotoCards;

/**
 * A background found for a photo card, before it is stored. `source` is
 * the CardBackgroundSource key (ai, link, pexels); `meta` carries credit
 * details such as the generator or the Pexels photographer.
 */
final readonly class CardBackgroundImage
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $bytes,
        public string $mime,
        public string $source,
        public array $meta = [],
    ) {}

    public function extension(): string
    {
        return match ($this->mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
