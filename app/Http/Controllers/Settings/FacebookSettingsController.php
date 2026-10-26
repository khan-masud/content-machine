<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Facebook\SaveFacebookSettingsAction;
use App\Data\Facebook\FacebookSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceSettings;
use App\Http\Requests\Settings\UpdateFacebookSettingsRequest;
use App\Support\Facebook\FacebookConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FacebookSettingsController extends Controller
{
    use AuthorizesWorkspaceSettings;

    public function edit(Request $request): Response
    {
        $workspace = $this->currentWorkspace();
        $this->authorizeWorkspaceAdmin($request, $workspace);
        $config = FacebookConfig::fromWorkspace($workspace);

        return Inertia::render('workspace-settings/facebook', [
            'pageId' => $config->pageId() ?? '',
            'pageName' => $config->pageName(),
            'connected' => $config->isConnected(),
            'publishEnabled' => $config->publishEnabled(),
            'captionLanguage' => $config->captionLanguage(),
        ]);
    }

    public function update(UpdateFacebookSettingsRequest $request, SaveFacebookSettingsAction $action): RedirectResponse
    {
        $action->handle($this->currentWorkspace(), FacebookSettingsData::fromRequest($request));

        return to_route('settings.facebook.edit')->with('success', 'Facebook Page connected.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $this->authorizeWorkspaceAdmin($request, $workspace);
        FacebookConfig::disconnect($workspace);

        return to_route('settings.facebook.edit')->with('success', 'Facebook Page disconnected.');
    }
}
