<?php

namespace App\Support\PhotoCards\Backgrounds;

use App\Contracts\CardBackgroundSource;
use App\Support\Posts\PostCategories;

/**
 * The background sources by key, in the order a category wants them tried
 * (config/post_categories.php `image_sources`, else
 * config/photo_cards.php `background_sources`).
 */
class CardBackgroundSources
{
    /**
     * @param  array<string, CardBackgroundSource>  $sources
     */
    public function __construct(private readonly array $sources) {}

    /**
     * @return array<string, CardBackgroundSource>
     */
    public function forCategory(?string $category): array
    {
        $order = ($category !== null ? PostCategories::imageSources($category) : null)
            ?? (array) config('photo_cards.background_sources');

        $ordered = [];
        foreach ($order as $key) {
            if (is_string($key) && isset($this->sources[$key])) {
                $ordered[$key] = $this->sources[$key];
            }
        }

        return $ordered;
    }
}
