<?php

namespace App\Data\ImageAi;

/**
 * One image to generate. `variation` changes the seed so asking again gives
 * a different picture for the same prompt.
 */
final readonly class ImagePromptData
{
    public function __construct(
        public string $prompt,
        public int $width,
        public int $height,
        public int $variation = 0,
    ) {}

    public function seed(): int
    {
        return abs(crc32($this->prompt.'#'.$this->variation)) % 2_000_000_000;
    }
}
