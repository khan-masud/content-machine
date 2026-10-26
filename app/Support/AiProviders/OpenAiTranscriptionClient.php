<?php

namespace App\Support\AiProviders;

use App\Models\AiProviderCredential;
use App\Support\LinkResolution\PublicUrlGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Calls OpenAI's audio transcription endpoint directly, independent of
 * $credential->model: that field is for chat/completion models, a
 * different concern from which model transcribes audio.
 *
 * The models come from config/ai_transcription.php, picked by the base
 * URL's host: on OpenAI, gpt-4o-transcribe first (much better at Bangla),
 * then whisper-1; on Groq, its free Whisper large v3 models. The next
 * model is tried on the same credential when a call fails (non-2xx,
 * network error, or empty text). A json-format model reports no
 * language, so it is inferred from the script ("bengali" or "english",
 * whisper's naming). If every model fails, the first error is returned.
 */
final class OpenAiTranscriptionClient implements AiTranscriptionClientContract
{
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public function __construct(
        private readonly ?PublicUrlGuard $urlGuard = null,
    ) {}

    public function transcribe(AiProviderCredential $credential, string $audioContents, string $filename, string $mimeType): AiTranscriptionResult
    {
        try {
            // See HttpAiProviderVerifier::verifyOpenAi() for why this is a bare
            // "/audio/transcriptions": the base URL already carries the version.
            $endpoint = (new AiProviderBaseUrl($this->urlGuard))->resolve(
                $credential->base_url,
                self::DEFAULT_BASE_URL,
            );
        } catch (Throwable) {
            return AiTranscriptionResult::failure('Could not reach the transcription provider.');
        }

        $models = $this->modelsFor($endpoint['url']);

        if ($models === []) {
            return AiTranscriptionResult::failure('This AI provider does not transcribe audio.');
        }

        $firstFailure = null;

        foreach ($models as $model => $format) {
            $result = $this->attempt($credential, $endpoint, $model, $format, $audioContents, $filename, $mimeType);

            if ($result->successful) {
                return $result;
            }

            $firstFailure ??= $result;
        }

        return $firstFailure;
    }

    /**
     * @return array<string, string> model => response_format
     */
    private function modelsFor(string $url): array
    {
        $models = config('ai_transcription.models', []);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $chosen = is_array($models) ? ($models[$host] ?? $models['default'] ?? []) : [];

        return is_array($chosen) ? array_filter($chosen, 'is_string') : [];
    }

    /**
     * @param  array{url: string, options: array<string, mixed>}  $endpoint
     */
    private function attempt(AiProviderCredential $credential, array $endpoint, string $model, string $format, string $audioContents, string $filename, string $mimeType): AiTranscriptionResult
    {
        try {
            $response = Http::withToken($credential->api_key)
                ->timeout(60)
                ->withOptions([
                    'allow_redirects' => false,
                    ...$endpoint['options'],
                ])
                ->attach('file', $audioContents, $filename, ['Content-Type' => $mimeType])
                ->post($endpoint['url'].'/audio/transcriptions', [
                    'model' => $model,
                    'response_format' => $format,
                ]);
        } catch (Throwable) {
            return AiTranscriptionResult::failure('Could not reach the transcription provider.');
        }

        if (! $response->successful()) {
            $message = $response->json('error.message');

            return AiTranscriptionResult::failure(
                is_string($message) && $message !== '' ? $message : "The transcription provider returned an unexpected status ({$response->status()})."
            );
        }

        $text = $response->json('text');

        if (! is_string($text) || trim($text) === '') {
            return AiTranscriptionResult::failure('The transcription provider returned no text.');
        }

        $text = trim($text);
        $language = $response->json('language');

        if ($format === 'json') {
            $language = preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1 ? 'bengali' : 'english';
        }

        return AiTranscriptionResult::success(
            text: $text,
            language: is_string($language) && $language !== '' ? $language : null,
            model: $model,
        );
    }
}
