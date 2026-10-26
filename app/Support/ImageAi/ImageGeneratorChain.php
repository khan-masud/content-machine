<?php

namespace App\Support\ImageAi;

use App\Contracts\ImageGenerator;
use App\Data\ImageAi\ImagePromptData;
use App\Models\Workspace;

/**
 * Tries each generator in order and returns the first image. Adding a
 * provider is a new ImageGenerator in the list bound in AppServiceProvider.
 */
class ImageGeneratorChain implements ImageGenerator
{
    /**
     * @param  list<ImageGenerator>  $generators
     */
    public function __construct(private readonly array $generators) {}

    public function generate(Workspace $workspace, ImagePromptData $prompt): ?array
    {
        foreach ($this->generators as $generator) {
            $image = $generator->generate($workspace, $prompt);

            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }
}
