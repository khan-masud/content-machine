<?php

namespace App\Support\PhotoCards\Backgrounds;

use App\Contracts\CardBackgroundSource;
use App\Contracts\ImageGenerator;
use App\Data\ImageAi\ImagePromptData;
use App\Data\PhotoCards\CardBackgroundImage;
use App\Models\Post;
use App\Support\PhotoCards\PhotoCardComposer;
use App\Support\Posts\PostCategories;

/**
 * Generates a background from the card's image prompt (written by the AI
 * with the draft), styled for the post's category.
 */
class AiCardBackgroundSource implements CardBackgroundSource
{
    public function __construct(private readonly ImageGenerator $generator) {}

    public function find(Post $post, int $variation = 0): ?CardBackgroundImage
    {
        $workspace = $post->workspace;
        if ($workspace === null) {
            return null;
        }

        $card = is_array($post->photo_card) ? $post->photo_card : [];
        $subject = is_string($card['image_prompt'] ?? null) && trim($card['image_prompt']) !== ''
            ? trim($card['image_prompt'])
            : 'An editorial illustration about: '.$post->title;
        $style = $post->category !== null ? PostCategories::imageStyle($post->category) : null;

        $prompt = implode(', ', array_filter([
            $subject,
            $style,
            (string) config('photo_cards.image_prompt_suffix'),
        ]));

        $image = $this->generator->generate($workspace, new ImagePromptData(
            $prompt,
            PhotoCardComposer::backgroundWidth(),
            PhotoCardComposer::backgroundHeight(),
            $variation,
        ));

        return $image === null ? null : new CardBackgroundImage(
            $image['bytes'],
            $image['mime'],
            'ai',
            ['generator' => $image['generator'], 'prompt' => $prompt],
        );
    }
}
