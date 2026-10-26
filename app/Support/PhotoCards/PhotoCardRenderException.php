<?php

namespace App\Support\PhotoCards;

use RuntimeException;

/**
 * A card could not be rendered. The message is safe to show to the person
 * who asked for the card.
 */
class PhotoCardRenderException extends RuntimeException {}
