<?php

namespace Tests\Support\PhotoCards;

use App\Contracts\PhotoCardRenderer;
use App\Data\PhotoCards\PhotoCardData;
use App\Support\PhotoCards\PhotoCardRenderException;

/**
 * Records the card it was asked to draw and returns a 1x1 PNG, or throws
 * when failWith() is set. Lets tests run without python3/Pillow.
 */
final class FakePhotoCardRenderer implements PhotoCardRenderer
{
    public const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public ?PhotoCardData $lastCard = null;

    public int $renders = 0;

    private ?string $failure = null;

    /** Distinct bytes per render so each render stores a new asset. */
    private bool $unique = false;

    public static function png(): string
    {
        return (string) base64_decode(self::PNG_BASE64, true);
    }

    public function failWith(string $message): self
    {
        $this->failure = $message;

        return $this;
    }

    public function uniquePerRender(): self
    {
        $this->unique = true;

        return $this;
    }

    public function render(PhotoCardData $card): string
    {
        $this->lastCard = $card;
        $this->renders++;

        if ($this->failure !== null) {
            throw new PhotoCardRenderException($this->failure);
        }

        // Bytes after IEND are ignored by image readers but change the checksum.
        return self::png().($this->unique ? (string) $this->renders : '');
    }
}
