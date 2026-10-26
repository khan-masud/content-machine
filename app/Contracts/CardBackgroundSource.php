<?php

namespace App\Contracts;

use App\Data\PhotoCards\CardBackgroundImage;
use App\Models\Post;

interface CardBackgroundSource
{
    /**
     * Find a background for the post's card, or null when this source has
     * nothing (not configured, no match, provider down). A higher
     * `variation` asks for a different image than last time where possible.
     */
    public function find(Post $post, int $variation = 0): ?CardBackgroundImage;
}
