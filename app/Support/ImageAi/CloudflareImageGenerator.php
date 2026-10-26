<?php

namespace App\Support\ImageAi;

use App\Contracts\ImageGenerator;
use App\Data\ImageAi\ImagePromptData;
use App\Models\Workspace;
use App\Support\Http\SafeRemoteImageFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Workers AI, FLUX.1 [schnell]. The free plan includes a daily
 * allowance; the model returns a square JPEG that the card crops to fit.
 */
class CloudflareImageGenerator implements ImageGenerator
{
    public const MODEL = '@cf/black-forest-labs/flux-1-schnell';

    public function generate(Workspace $workspace, ImagePromptData $prompt): ?array
    {
        $config = ImageAiConfig::fromWorkspace($workspace);
        $accountId = $config->cloudflareAccountId();
        $token = $config->cloudflareApiToken();

        if ($accountId === null || $token === null) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(60)
                ->post('https://api.cloudflare.com/client/v4/accounts/'.rawurlencode($accountId).'/ai/run/'.self::MODEL, [
                    'prompt' => mb_substr($prompt->prompt, 0, 2048),
                    'steps' => 4,
                    'seed' => $prompt->seed(),
                ]);
        } catch (Throwable) {
            Log::warning('Cloudflare image generation could not be reached.');

            return null;
        }

        $encoded = $response->json('result.image');
        if (! $response->successful() || ! is_string($encoded)) {
            Log::warning('Cloudflare image generation failed.', ['status' => $response->status()]);

            return null;
        }

        $bytes = base64_decode($encoded, true);
        $image = $bytes === false ? null : SafeRemoteImageFetcher::imageOrNull($bytes);

        return $image === null ? null : $image + ['generator' => 'cloudflare'];
    }
}
