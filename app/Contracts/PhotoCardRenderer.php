<?php

namespace App\Contracts;

use App\Data\PhotoCards\PhotoCardData;
use App\Support\PhotoCards\PhotoCardRenderException;

interface PhotoCardRenderer
{
    /**
     * Render the card and return the PNG bytes.
     *
     * @throws PhotoCardRenderException when the card cannot be drawn
     */
    public function render(PhotoCardData $card): string;
}
