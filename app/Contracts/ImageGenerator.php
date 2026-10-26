<?php

namespace App\Contracts;

use App\Data\ImageAi\ImagePromptData;
use App\Models\Workspace;

interface ImageGenerator
{
    /**
     * Generate an image, or return null when this generator is not
     * configured for the workspace or the provider failed.
     *
     * @return array{bytes: string, mime: string, generator: string}|null
     */
    public function generate(Workspace $workspace, ImagePromptData $prompt): ?array;
}
