<?php

namespace App\Actions\Facebook;

use App\Contracts\FacebookPageClient;
use App\Data\Facebook\FacebookSettingsData;
use App\Models\Workspace;
use App\Support\Facebook\FacebookConfig;
use Illuminate\Validation\ValidationException;

/**
 * Connects the workspace's Facebook Page. The Page id and token are checked
 * against the Graph API before they are saved, and the Page name is kept for
 * display.
 */
class SaveFacebookSettingsAction
{
    public function __construct(private readonly FacebookPageClient $client) {}

    public function handle(Workspace $workspace, FacebookSettingsData $data): void
    {
        $token = $data->pageAccessToken ?? FacebookConfig::fromWorkspace($workspace)->pageAccessToken();

        if ($token === null) {
            throw ValidationException::withMessages(['page_access_token' => __('Paste the Page access token.')]);
        }

        $page = $this->client->page($data->pageId, $token);

        if (! $page->successful || $page->id !== $data->pageId) {
            throw ValidationException::withMessages([
                'page_access_token' => __('Facebook did not accept this Page id and token: :error', ['error' => $page->error ?? 'the token belongs to another Page']),
            ]);
        }

        FacebookConfig::write($workspace, $data->pageId, $page->name, $data->pageAccessToken, $data->publishEnabled, $data->captionLanguage);
    }
}
