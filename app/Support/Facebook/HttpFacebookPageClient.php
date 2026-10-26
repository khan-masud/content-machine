<?php

namespace App\Support\Facebook;

use App\Contracts\FacebookPageClient;
use App\Data\Facebook\FacebookApiResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Facebook Graph API for a single Page, authenticated with a Page access
 * token. Tokens go in the request body, never in a logged URL.
 */
class HttpFacebookPageClient implements FacebookPageClient
{
    public function page(string $pageId, string $pageAccessToken): FacebookApiResult
    {
        return $this->send(
            fn (): Response => Http::timeout(15)->get($this->url(rawurlencode($pageId)), [
                'fields' => 'id,name',
                'access_token' => $pageAccessToken,
            ]),
            false,
        );
    }

    public function publishPhoto(string $pageId, string $pageAccessToken, string $photoContents, string $filename, string $caption): FacebookApiResult
    {
        return $this->send(
            fn (): Response => Http::timeout(90)
                ->attach('source', $photoContents, $filename)
                ->post($this->url(rawurlencode($pageId).'/photos'), [
                    'caption' => $caption,
                    'published' => 'true',
                    'access_token' => $pageAccessToken,
                ]),
            true,
        );
    }

    public function publishText(string $pageId, string $pageAccessToken, string $message): FacebookApiResult
    {
        return $this->send(
            fn (): Response => Http::timeout(30)->asForm()->post($this->url(rawurlencode($pageId).'/feed'), [
                'message' => $message,
                'access_token' => $pageAccessToken,
            ]),
            true,
        );
    }

    public function comment(string $objectId, string $pageAccessToken, string $message): FacebookApiResult
    {
        return $this->send(
            fn (): Response => Http::timeout(30)->asForm()->post($this->url(rawurlencode($objectId).'/comments'), [
                'message' => $message,
                'access_token' => $pageAccessToken,
            ]),
            false,
        );
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.facebook.graph_version').'/'.$path;
    }

    /**
     * @param  callable(): Response  $call
     */
    private function send(callable $call, bool $isPublish): FacebookApiResult
    {
        try {
            $response = $call();
        } catch (Throwable) {
            Log::warning('Facebook Graph API could not be reached.');

            return FacebookApiResult::failure('Facebook could not be reached.', outcomeUnknown: $isPublish);
        }

        if ($response->successful()) {
            $id = $response->json('id');
            $postId = $response->json('post_id');

            return new FacebookApiResult(
                true,
                id: is_scalar($id) ? (string) $id : null,
                postId: is_scalar($postId) ? (string) $postId : null,
                name: is_string($response->json('name')) ? $response->json('name') : null,
            );
        }

        $message = $response->json('error.message');
        $error = is_string($message) && $message !== '' ? $message : 'Facebook returned HTTP '.$response->status().'.';
        Log::warning('Facebook Graph API request failed.', ['status' => $response->status(), 'error' => $error]);

        // A 5xx answer does not prove the post was not created.
        return FacebookApiResult::failure($error, outcomeUnknown: $isPublish && $response->serverError());
    }
}
