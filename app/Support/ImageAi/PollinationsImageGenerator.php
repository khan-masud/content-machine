<?php

namespace App\Support\ImageAi;

use App\Concerns\ReadsBodyWithinLimit;
use App\Contracts\ImageGenerator;
use App\Data\ImageAi\ImagePromptData;
use App\Models\Workspace;
use App\Support\Http\SafeRemoteImageFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pollinations.ai: free, no key, rate limited and sometimes slow. Used as the
 * fallback when Cloudflare is not set up or fails.
 */
class PollinationsImageGenerator implements ImageGenerator
{
    use ReadsBodyWithinLimit;

    public function generate(Workspace $workspace, ImagePromptData $prompt): ?array
    {
        if (! ImageAiConfig::fromWorkspace($workspace)->pollinationsEnabled()) {
            return null;
        }

        $url = 'https://image.pollinations.ai/prompt/'.rawurlencode(mb_substr($prompt->prompt, 0, 1500)).'?'.http_build_query([
            'width' => $prompt->width,
            'height' => $prompt->height,
            'seed' => $prompt->seed(),
            'model' => 'flux',
            'nologo' => 'true',
        ]);

        try {
            $response = Http::timeout(90)->withOptions(['stream' => true])->get($url);
        } catch (Throwable) {
            Log::warning('Pollinations image generation could not be reached.');

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Pollinations image generation failed.', ['status' => $response->status()]);

            return null;
        }

        $bytes = $this->readBodyWithinLimit($response, SafeRemoteImageFetcher::MAX_BYTES);
        $image = $bytes === null ? null : SafeRemoteImageFetcher::imageOrNull($bytes);

        return $image === null ? null : $image + ['generator' => 'pollinations'];
    }
}
