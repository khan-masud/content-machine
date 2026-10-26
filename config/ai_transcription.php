<?php

/*
|--------------------------------------------------------------------------
| Audio transcription models
|--------------------------------------------------------------------------
|
| Which models OpenAiTranscriptionClient tries, keyed by the credential's
| base-URL host. Each entry is model => response_format, tried in order.
| "default" covers OpenAI itself and any host not listed. An empty list
| means the provider has no transcription endpoint, so the credential is
| skipped without a request.
|
| gpt-4o-transcribe only accepts response_format=json and reports no
| language; the client infers it from the script in that case.
|
*/

return [

    'models' => [
        'default' => [
            'gpt-4o-transcribe' => 'json',
            'whisper-1' => 'verbose_json',
        ],

        // Groq's free tier: Whisper large v3, then the faster turbo variant.
        'api.groq.com' => [
            'whisper-large-v3' => 'verbose_json',
            'whisper-large-v3-turbo' => 'verbose_json',
        ],

        // Gemini's OpenAI-compatible endpoint has no audio transcription.
        'generativelanguage.googleapis.com' => [],
    ],

];
